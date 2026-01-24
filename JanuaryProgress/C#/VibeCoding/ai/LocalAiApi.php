<?php
// LocalAIApi — proxy client for the Responses API.
// Usage (async: auto-polls status until ready):
//   require_once __DIR__ . '/ai/LocalAIApi.php';
//   $response = LocalAIApi::createResponse([
//       'input' => [
//           ['role' => 'system', 'content' => 'You are a helpful assistant.'],
//           ['role' => 'user', 'content' => 'Tell me a bedtime story.'],
//       ],
//   ]);
//   if (!empty($response['success'])) {
//       // response['data'] contains full payload, e.g.:
//       // {
//       //   "id": "resp_xxx",
//       //   "status": "completed",
//       //   "output": [
//       //     {"type": "reasoning", "summary": []},
//       //     {"type": "message", "content": [{"type": "output_text", "text": "Your final answer here."}]}
//       //   ]
//       // }
//       $decoded = LocalAIApi::decodeJsonFromResponse($response); // or inspect $response['data'] / extractText(...)
//   }
// Poll settings override:
//   LocalAIApi::createResponse($payload, ['poll_interval' => 5, 'poll_timeout' => 300]);

class LocalAIApi
{
    /** @var array<string,mixed>|null */
    private static ?array $configCache = null;

    /**
     * Signature compatible with the OpenAI Responses API.
     *
     * @param array<string,mixed> $params Request body (model, input, text, reasoning, metadata, etc.).
     * @param array<string,mixed> $options Extra options (timeout, verify_tls, headers, path, project_uuid).
     * @return array{
     *   success:bool,
     *   status?:int,
     *   data?:mixed,
     *   error?:string,
     *   response?:mixed,
     *   message?:string
     * }
     */
    public static function createResponse(array $params, array $options = []): array
    {
        $cfg = self::config();
        $payload = $params;

        if (empty($payload['input']) || !is_array($payload['input'])) {
            return [
                'success' => false,
                'error'   => 'input_missing',
                'message' => 'Parameter "input" is required and must be an array.',
            ];
        }

        if (!isset($payload['model']) || $payload['model'] === '') {
            $payload['model'] = $cfg['default_model'];
        }

        $initial = self::request($options['path'] ?? null, $payload, $options);
        if (empty($initial['success'])) {
            return $initial;
        }

        // Async flow: if backend returns ai_request_id, poll status until ready
        $data = $initial['data'] ?? null;
        if (is_array($data) && isset($data['ai_request_id'])) {
            $aiRequestId = $data['ai_request_id'];
            $pollTimeout = isset($options['poll_timeout']) ? (int) $options['poll_timeout'] : 300; // seconds
            $pollInterval = isset($options['poll_interval']) ? (int) $options['poll_interval'] : 5; // seconds
            return self::awaitResponse($aiRequestId, [
                'timeout' => $pollTimeout,
                'interval' => $pollInterval,
                'headers' => $options['headers'] ?? [],
                'timeout_per_call' => $options['timeout'] ?? null,
            ]);
        }

        return $initial;
    }

    /**
     * Snake_case alias for createResponse (matches the provided example).
     *
     * @param array<string,mixed> $params
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function create_response(array $params, array $options = []): array
    {
        return self::createResponse($params, $options);
    }

    /**
     * Perform a raw request to the AI proxy.
     *
     * @param string $path Endpoint (may be an absolute URL).
     * @param array<string,mixed> $payload JSON payload.
     * @param array<string,mixed> $options Additional request options.
     * @return array<string,mixed>
     */
    public static function request(?string $path = null, array $payload = [], array $options = []): array
    {
        $cfg = self::config();

        $projectUuid = $cfg['project_uuid'];
        if (empty($projectUuid)) {
            return [
                'success' => false,
                'error'   => 'project_uuid_missing',
                'message' => 'PROJECT_UUID is not defined; aborting AI request.',
            ];
        }

        $defaultPath = $cfg['responses_path'] ?? null;
        $resolvedPath = $path ?? ($options['path'] ?? $defaultPath);
        if (empty($resolvedPath)) {
            return [
                'success' => false,
                'error'   => 'project_id_missing',
                'message' => 'PROJECT_ID is not defined; cannot resolve AI proxy endpoint.',
            ];
        }

        $url = self::buildUrl($resolvedPath, $cfg['base_url']);
        $baseTimeout = isset($cfg['timeout']) ? (int) $cfg['timeout'] : 30;
        $timeout = isset($options['timeout']) ? (int) $options['timeout'] : $baseTimeout;
        if ($timeout <= 0) {
            $timeout = 30;
        }

        $baseVerifyTls = array_key_exists('verify_tls', $cfg) ? (bool) $cfg['verify_tls'] : true;
        $verifyTls = array_key_exists('verify_tls', $options)
            ? (bool) $options['verify_tls']
            : $baseVerifyTls;

        $projectHeader = $cfg['project_header'];

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $headers[] = $projectHeader . ': ' . $projectUuid;
        if (!empty($options['headers']) && is_array($options['headers'])) {
            foreach ($options['headers'] as $header) {
                if (is_string($header) && $header !== '') {
                    $headers[] = $header;
                }
            }
        }

        if (!empty($projectUuid) && !array_key_exists('project_uuid', $payload)) {
            $payload['project_uuid'] = $projectUuid;
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return [
                'success' => false,
                'error'   => 'json_encode_failed',
                'message' => 'Failed to encode request body to JSON.',
            ];
        }

        return self::sendCurl($url, 'POST', $body, $headers, $timeout, $verifyTls);
    }

    /**
     * Poll AI request status until ready or timeout.
     *
     * @param int|string $aiRequestId
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function awaitResponse($aiRequestId, array $options = []): array
    {
        $cfg = self::config();

        $timeout = isset($options['timeout']) ? (int) $options['timeout'] : 300; // seconds
        $interval = isset($options['interval']) ? (int) $options['interval'] : 5; // seconds
        if ($interval <= 0) {
            $interval = 5;
        }
        $perCallTimeout = isset($options['timeout_per_call']) ? (int) $options['timeout_per_call'] : null;

        $deadline = time() + max($timeout, $interval);
        $headers = $options['headers'] ?? [];

        while (true) {
            $statusResp = self::fetchStatus($aiRequestId, [
                'headers' => $headers,
                'timeout' => $perCallTimeout,
            ]);
            if (!empty($statusResp['success'])) {
                $data = $statusResp['data'] ?? [];
                if (is_array($data)) {
                    $statusValue = $data['status'] ?? null;
                    if ($statusValue === 'success') {
                        return [
                            'success' => true,
                            'status'  => 200,
                            'data'    => $data['response'] ?? $data,
                        ];
                    }
                    if ($statusValue === 'failed') {
                        return [
                            'success' => false,
                            'status'  => 500,
                            'error'   => isset($data['error']) ? (string)$data['error'] : 'AI request failed',
                            'data'    => $data,
                        ];
                    }
                }
            } else {
                return $statusResp;
            }

            if (time() >= $deadline) {
                return [
                    'success' => false,
                    'error'   => 'timeout',
                    'message' => 'Timed out waiting for AI response.',
                ];
            }
            sleep($interval);
        }
    }

    /**
     * Fetch status for queued AI request.
     *
     * @param int|string $aiRequestId
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function fetchStatus($aiRequestId, array $options = []): array
    {
        $cfg = self::config();
        $projectUuid = $cfg['project_uuid'];
        if (empty($projectUuid)) {
            return [
                'success' => false,
                'error'   => 'project_uuid_missing',
                'message' => 'PROJECT_UUID is not defined; aborting status check.',
            ];
        }

        $statusPath = self::resolveStatusPath($aiRequestId, $cfg);
        $url = self::buildUrl($statusPath, $cfg['base_url']);

        $baseTimeout = isset($cfg['timeout']) ? (int) $cfg['timeout'] : 30;
        $timeout = isset($options['timeout']) ? (int) $options['timeout'] : $baseTimeout;
        if ($timeout <= 0) {
            $timeout = 30;
        }

        $baseVerifyTls = array_key_exists('verify_tls', $cfg) ? (bool) $cfg['verify_tls'] : true;
        $verifyTls = array_key_exists('verify_tls', $options)
            ? (bool) $options['verify_tls']
            : $baseVerifyTls;

        $projectHeader = $cfg['project_header'];
        $headers = [
            'Accept: application/json',
            $projectHeader . ': ' . $projectUuid,
        ];
        if (!empty($options['headers']) && is_array($options['headers'])) {
            foreach ($options['headers'] as $header) {
                if (is_string($header) && $header !== '') {
                    $headers[] = $header;
                }
            }
        }

        return self::sendCurl($url, 'GET', null, $headers, $timeout, $verifyTls);
    }

    /**
     * Extract plain text from a Responses API payload.
     *
     * @param array<string,mixed> $response Result of LocalAIApi::createResponse|request.
     * @return string
     */
    public static function extractText(array $response): string
    {
        $payload = $response['data'] ?? $response;
        if (!is_array($payload)) {
            return '';
        }

        if (!empty($payload['output']) && is_array($payload['output'])) {
            $combined = '';
            foreach ($payload['output'] as $item) {
                if (!isset($item['content']) || !is_array($item['content'])) {
                    continue;
                }
                foreach ($item['content'] as $block) {
                    if (is_array($block) && ($block['type'] ?? '') === 'output_text' && !empty($block['text'])) {
                        $combined .= $block['text'];
                    }
                }
            }
            if ($combined !== '') {
                return $combined;
            }
        }

        if (!empty($payload['choices'][0]['message']['content'])) {
            return (string) $payload['choices'][0]['message']['content'];
        }

        return '';
    }

    /**
     * Attempt to decode JSON emitted by the model (handles markdown fences).
     *
     * @param array<string,mixed> $response
     * @return array<string,mixed>|null
     */
    public static function decodeJsonFromResponse(array $response): ?array
    {
        $text = self::extractText($response);
        if ($text === '') {
            return null;
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $stripped = preg_replace('/^```json|```$/m', '', trim($text));
        if ($stripped !== null && $stripped !== $text) {
            $decoded = json_decode($stripped, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Load configuration from ai/config.php.
     *
     * @return array<string,mixed>
     */
    private static function config(): array
    {
        if (self::$configCache === null) {
            $configPath = __DIR__ . '/config.php';
            if (!file_exists($configPath)) {
                throw new RuntimeException('AI config file not found: ai/config.php');
            }
            $cfg = require $configPath;
            if (!is_array($cfg)) {
                throw new RuntimeException('Invalid AI config format: expected array');
            }
            self::$configCache = $cfg;
        }

        return self::$configCache;
    }

    /**
     * Build an absolute URL from base_url and a path.
     */
    private static function buildUrl(string $path, string $baseUrl): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return $baseUrl;
        }
        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }
        if ($trimmed[0] === '/') {
            return $baseUrl . $trimmed;
        }
        return $baseUrl . '/' . $trimmed;
    }

    /**
     * Resolve status path based on configured responses_path and ai_request_id.
     *
     * @param int|string $aiRequestId
     * @param array<string,mixed> $cfg
     * @return string
     */
    private static function resolveStatusPath($aiRequestId, array $cfg): string
    {
        $basePath = $cfg['responses_path'] ?? '';
        $trimmed = rtrim($basePath, '/');
        if ($trimmed === '') {
            return '/ai-request/' . rawurlencode((string)$aiRequestId) . '/status';
        }
        if (substr($trimmed, -11) !== '/ai-request') {
            $trimmed .= '/ai-request';
        }
        return $trimmed . '/' . rawurlencode((string)$aiRequestId) . '/status';
    }

    /**
     * Shared CURL sender for GET/POST requests.
     *
     * @param string $url
     * @param string $method
     * @param string|null $body
     * @param array<int,string> $headers
     * @param int $timeout
     * @param bool $verifyTls
     * @return array<string,mixed>
     */
    private static function sendCurl(string $url, string $method, ?string $body, array $headers, int $timeout, bool $verifyTls): array
    {
        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'error'   => 'curl_missing',
                'message' => 'PHP cURL extension is missing. Install or enable it on the VM.',
            ];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifyTls);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifyTls ? 2 : 0);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);

        $upper = strtoupper($method);
        if ($upper === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?? '');
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch) ?: 'Unknown cURL error';
            curl_close($ch);
            return [
                'success' => false,
                'error'   => 'curl_error',
                'message' => $error,
            ];
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = null;
        if ($responseBody !== '' && $responseBody !== null) {
            $decoded = json_decode($responseBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $decoded = null;
            }
        }

        if ($status >= 200 && $status < 300) {
            return [
                'success' => true,
                'status'  => $status,
                'data'    => $decoded ?? $responseBody,
            ];
        }

        $errorMessage = 'AI proxy request failed';
        if (is_array($decoded)) {
            $errorMessage = $decoded['error'] ?? $decoded['message'] ?? $errorMessage;
        } elseif (is_string($responseBody) && $responseBody !== '') {
            $errorMessage = $responseBody;
        }

        return [
            'success'  => false,
            'status'   => $status,
            'error'    => $errorMessage,
            'response' => $decoded ?? $responseBody,
        ];
    }
}

// Legacy alias for backward compatibility with the previous class name.
if (!class_exists('OpenAIService')) {
    class_alias(LocalAIApi::class, 'OpenAIService');
}
