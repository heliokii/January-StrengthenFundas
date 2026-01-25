<?php
// OpenAI proxy configuration (workspace scope).
// Reads values from environment variables or executor/.env.

$projectUuid = getenv('PROJECT_UUID');
$projectId   = getenv('PROJECT_ID');

if (
    ($projectUuid === false || $projectUuid === null || $projectUuid === '') ||
    ($projectId === false || $projectId === null || $projectId === '')
) {
    $envPath = realpath(__DIR__ . '/../../.env'); // executor/.env
    if ($envPath && is_readable($envPath)) {
        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($key === '') {
                continue;
            }
            $value = trim($value, "\"' ");
            if (getenv($key) === false || getenv($key) === '') {
                putenv("{$key}={$value}");
            }
        }
        $projectUuid = getenv('PROJECT_UUID');
        $projectId   = getenv('PROJECT_ID');
    }
}

$projectUuid = ($projectUuid === false) ? null : $projectUuid;
$projectId   = ($projectId === false) ? null : $projectId;

$baseUrl       = 'https://flatlogic.com';
$responsesPath = $projectId ? "/projects/{$projectId}/ai-request" : null;

return [
    'base_url'       => $baseUrl,
    'responses_path' => $responsesPath,
    'project_id'     => $projectId,
    'project_uuid'   => $projectUuid,
    'project_header' => 'project-uuid',
    'default_model'  => 'gpt-5-mini',
    'timeout'        => 30,
    'verify_tls'     => true,
];
