<?php
ini_set('display_errors', 0); // Disable for production
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/ai/LocalAIApi.php';
require_once __DIR__ . '/db/config.php';
require_once __DIR__ . '/lib/jwt.php';

// --- Utility Functions ---

function send_error($statusCode, $message, $log_message = null) {
    http_response_code($statusCode);
    if ($log_message) {
        error_log($log_message);
    }
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function get_authenticated_user_id() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        return null;
    }
    $auth_header = $headers['Authorization'];
    list($type, $token) = explode(' ', $auth_header, 2);

    if (strcasecmp($type, 'Bearer') !== 0 || !$token) {
        return null;
    }

    try {
        $decoded = jwt_decode($token, JWT_SECRET);
        return $decoded['user_id'] ?? null;
    } catch (Exception $e) {
        return null;
    }
}

// --- Request Routing ---

$request_method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$json_data = null;

if ($request_method === 'POST') {
    $input = file_get_contents('php://input');
    $json_data = json_decode($input, true);
    $action = $json_data['action'] ?? $action; // Allow action in POST body
}

switch ($action) {
    case 'register':
        if ($request_method === 'POST') handle_register($json_data);
        else send_error(405, 'Method Not Allowed');
        break;
    case 'login':
        if ($request_method === 'POST') handle_login($json_data);
        else send_error(405, 'Method Not Allowed');
        break;
    case 'summarize':
    case 'explain':
    case 'flashcards':
        if ($request_method === 'POST') process_ai_action($action, $json_data);
        else send_error(405, 'Method Not Allowed');
        break;
    case 'history':
        if ($request_method === 'GET') get_study_history();
        else send_error(405, 'Method Not Allowed');
        break;
    case 'flashcards_for_session':
        if ($request_method === 'GET') get_session_details($_GET['session_id'] ?? null);
        else send_error(405, 'Method Not Allowed');
        break;
    default:
        send_error(400, 'Invalid action specified.');
}

// --- Route Handlers ---

function handle_register($data) {
    $name = trim($data['name'] ?? '');
    $email = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $data['password'] ?? '';

    if (empty($name) || !$email || empty($password)) {
        send_error(400, 'Name, valid email, and password are required.');
    }
    if (strlen($password) < 8) {
        send_error(400, 'Password must be at least 8 characters long.');
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id FROM User WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            send_error(409, 'An account with this email already exists.');
        }

        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO User (name, email, password_hash) VALUES (?, ?, ?)");
        if ($stmt->execute([$name, $email, $password_hash])) {
            echo json_encode(['success' => true, 'message' => 'Registration successful. You can now log in.']);
        } else {
            send_error(500, 'Registration failed due to a server error.', 'DB insert failed for user.');
        }
    } catch (PDOException $e) {
        send_error(500, 'Database error during registration.', 'PDO Error: ' . $e->getMessage());
    }
}

function handle_login($data) {
    $email = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $data['password'] ?? '';

    if (!$email || empty($password)) {
        send_error(400, 'Valid email and password are required.');
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id, password_hash FROM User WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $token = jwt_encode(['user_id' => $user['id'], 'exp' => time() + (60*60*24)], JWT_SECRET); // 24hr expiration
            echo json_encode(['success' => true, 'token' => $token]);
        } else {
            send_error(401, 'Invalid email or password.');
        }
    } catch (PDOException $e) {
        send_error(500, 'Database error during login.', 'PDO Error: ' . $e->getMessage());
    }
}

function process_ai_action($action, $data) {
    $user_id = get_authenticated_user_id();
    if (!$user_id) {
        send_error(401, 'Authentication required.');
    }

    $inputText = trim($data['text'] ?? '');
    if (empty($inputText) || strlen($inputText) > 5000) { // Basic validation
        send_error(400, 'Input text cannot be empty and must be less than 5000 characters.');
    }
    
    // Basic Rate Limiting (Example: 5 requests per 60 seconds)
    session_start();
    $rate_limit_key = 'ai_requests_' . $user_id;
    if (!isset($_SESSION[$rate_limit_key])) {
        $_SESSION[$rate_limit_key] = [];
    }
    $now = time();
    $_SESSION[$rate_limit_key] = array_filter($_SESSION[$rate_limit_key], function($timestamp) use ($now) {
        return ($now - $timestamp) < 60;
    });
    if (count($_SESSION[$rate_limit_key]) >= 5) {
        send_error(429, 'Too many requests. Please wait a minute before trying again.');
    }
    $_SESSION[$rate_limit_key][] = $now;

    $prompts = [
        'summarize' => 'You are an expert academic assistant. Summarize the following text concisely, focusing on the key takeaways.',
        'explain' => 'You are a patient and clear tutor. Explain the following concept or text in simple, easy-to-understand terms.',
        'flashcards' => 'You are a helpful study-bot. Generate a valid JSON array of objects, where each object has a "question" and "answer" key. The content should be based on the provided text, suitable for flashcards.',
    ];

    if (!isset($prompts[$action])) {
        send_error(400, 'Invalid AI action specified.');
    }
    $system_prompt = $prompts[$action];

    try {
        $ai_response = LocalAIApi::createResponse(['input' => [['role' => 'system', 'content' => $system_prompt], ['role' => 'user', 'content' => $inputText]]]);
        
        if (empty($ai_response['success'])) {
            send_error(502, 'The AI service is currently unavailable. Please try again later.', 'AI API Error: ' . ($ai_response['error'] ?? 'Unknown error'));
        }

        $responseText = LocalAIApi::extractText($ai_response);
        if (!$responseText) {
            send_error(500, 'Could not process the AI's response.', 'Failed to extract text from AI response.');
        }
        
        $final_response = save_session_and_get_response($user_id, $action, $inputText, json_encode($ai_response));
        echo json_encode($final_response);

    } catch (Exception $e) {
        send_error(500, 'An unexpected error occurred while processing your request.', 'AI Action Exception: ' . $e->getMessage());
    }
}

function save_session_and_get_response($user_id, $action, $inputText, $ai_raw_response) {
    $pdo = db();
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO StudySession (user_id, input_text, action_type, ai_response) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $inputText, $action, $ai_raw_response]);
        $sessionId = $pdo->lastInsertId();
        
        // The raw AI response is now stored, the frontend will parse it.
        // For flashcards, we still need to parse to save them individually for other potential uses.
        if ($action === 'flashcards') {
            $ai_data = json_decode($ai_raw_response, true);
            $flashcard_text = LocalAIApi::extractText($ai_data);
            $flashcards = json_decode($flashcard_text, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($flashcards)) {
                $flashcard_stmt = $pdo->prepare("INSERT INTO Flashcard (study_session_id, user_id, question, answer) VALUES (?, ?, ?, ?)");
                foreach ($flashcards as $card) {
                    if (isset($card['question']) && isset($card['answer'])) {
                        $flashcard_stmt->execute([$sessionId, $user_id, $card['question'], $card['answer']]);
                    }
                }
            } else {
                // Log the error but don't fail the whole request, the raw response is still saved.
                error_log("Invalid flashcard JSON from AI for session $sessionId");
            }
        }

        $pdo->commit();
        
        $parsed_response = json_decode($ai_raw_response, true);
        $text_content = LocalAIApi::extractText($parsed_response);
        $data_to_return = ($action === 'flashcards') ? json_decode($text_content, true) : $text_content;

        return ['success' => true, 'data' => $data_to_return];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        send_error(500, 'Could not save study session due to a database error.', 'DB Save Error: ' . $e->getMessage());
    }
}

function get_study_history() {
    $user_id = get_authenticated_user_id();
    if (!$user_id) {
        send_error(401, 'Authentication required.');
    }
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id, input_text, action_type, created_at FROM StudySession WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([$user_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    } catch (PDOException $e) {
        send_error(500, 'Could not retrieve study history.', 'DB History Error: ' . $e->getMessage());
    }
}

function get_session_details($sessionId) {
    $user_id = get_authenticated_user_id();
    if (!$user_id) {
        send_error(401, 'Authentication required.');
    }
    if (empty($sessionId) || !is_numeric($sessionId)) {
        send_error(400, 'Invalid session ID.');
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT ai_response, action_type FROM StudySession WHERE id = ? AND user_id = ?");
        $stmt->execute([$sessionId, $user_id]);
        $session = $stmt->fetch();

        if (!$session) {
             send_error(404, 'Study session not found or you do not have permission to view it.');
        }

        echo json_encode(['success' => true, 'data' => $session]);

    } catch (PDOException $e) {
        send_error(500, 'Could not retrieve session details.', 'DB Session Detail Error: ' . $e->getMessage());
    }
}