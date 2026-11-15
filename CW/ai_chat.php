<?php
/**
 * ai_chat.php — Cine AI Film Assistant JSON API Endpoint.
 *
 * Purpose:     Handles AJAX GET/POST requests for listing AI chat conversations,
 *              fetching message history, deleting conversations, and sending
 *              prompts to the Cine AI film assistant.
 * Connections: Connects to includes/AiService.php (LLM API integration) and
 *              includes/AiChatRepository.php (PDO persistence for conversations
 *              and messages).
 * Security:    Enforces session authentication (HTTP 401 for guests), validates
 *              HTTP methods and JSON payloads, bounds message length (<= 800 chars),
 *              and scopes all repository queries to the authenticated $userId.
 */

session_start(); // Resume session to identify the authenticated user
header('Content-Type: application/json; charset=UTF-8'); // Declare JSON response format for all API responses

require_once __DIR__ . '/includes/AiService.php'; // Load AI assistant service wrapper
require_once __DIR__ . '/includes/AiChatRepository.php'; // Load repository for persisting chat threads

// Verify authenticated user session
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0; // Cast session user ID to integer (0 if guest)

if ($userId <= 0) { // Reject unauthenticated API requests
    http_response_code(401); // Return HTTP 401 Unauthorized
    echo json_encode([ // Output structured JSON error with login redirect hint
        'ok'        => false,
        'logged_in' => false,
        'error'     => 'Please log in to chat with Cine AI.',
        'login_url' => 'loginUser.php',
    ]);
    exit; // Stop execution immediately
}

try {
    // Handle GET requests: fetch a specific conversation's messages or list all conversations
    if ($_SERVER['REQUEST_METHOD'] === 'GET') { // Route read-only GET queries
        $action   = $_GET['action'] ?? 'list'; // Determine requested read action ('list' or 'messages')
        $chatRepo = new AiChatRepository(); // Instantiate chat persistence repository

        if ($action === 'messages') { // Return message history for a single conversation
            $convId = (int)($_GET['conversation_id'] ?? 0); // Parse conversation ID from query string
            $conv   = $convId > 0 ? $chatRepo->getConversation($convId, $userId) : null; // Verify conversation exists and belongs to $userId
            if (!$conv) { // Return 404 if conversation is missing or owned by another user
                http_response_code(404); // Set HTTP 404 Not Found
                echo json_encode(['ok' => false, 'error' => 'Conversation not found.']); // Output JSON error
                exit; // Stop execution
            }
            $messages = $chatRepo->getMessages($convId, $userId); // Fetch ordered messages for this conversation
            echo json_encode([ // Return conversation metadata and messages
                'ok'           => true,
                'logged_in'    => true,
                'conversation' => $conv,
                'messages'     => $messages,
            ], JSON_UNESCAPED_UNICODE);
            exit; // End GET messages response
        }

        $conversations = $chatRepo->listConversations($userId); // List all conversations owned by the user
        echo json_encode([ // Return conversation list as JSON
            'ok'            => true,
            'logged_in'     => true,
            'conversations' => $conversations,
        ], JSON_UNESCAPED_UNICODE);
        exit; // End GET list response
    }

    // Reject non-POST methods for state-changing actions
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { // Block PUT/DELETE/PATCH etc.
        http_response_code(405); // Return HTTP 405 Method Not Allowed
        echo json_encode(['ok' => false, 'error' => 'Method not allowed.']); // Output JSON error
        exit; // Stop execution
    }

    // Decode raw JSON request body
    $rawBody = file_get_contents('php://input'); // Read raw JSON payload from request stream
    $input   = json_decode($rawBody ?: '', true); // Decode JSON into an associative array
    if (!is_array($input)) { // Reject malformed or non-object JSON payloads
        http_response_code(400); // Return HTTP 400 Bad Request
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON request.']); // Output JSON error
        exit; // Stop execution
    }

    $action = $input['action'] ?? 'send'; // Read requested POST action (defaults to 'send')

    // Handle conversation deletion
    if ($action === 'delete') { // Delete a user's conversation thread
        if ($userId <= 0) { // Defensive re-check of authenticated user ID
            http_response_code(401); // Return HTTP 401 Unauthorized
            echo json_encode(['ok' => false, 'error' => 'Please log in first.']); // Output JSON error
            exit; // Stop execution
        }
        $convId   = (int)($input['conversation_id'] ?? 0); // Cast target conversation ID to integer
        $chatRepo = new AiChatRepository(); // Instantiate chat repository
        $deleted  = $convId > 0 && $chatRepo->deleteConversation($convId, $userId); // Delete only if owned by $userId
        echo json_encode(['ok' => $deleted]); // Return deletion status
        exit; // End delete response
    }

    // Validate chat prompt message
    $message = trim((string)($input['message'] ?? '')); // Extract and trim user prompt
    if ($message === '') { // Reject empty prompts
        http_response_code(400); // Return HTTP 400 Bad Request
        echo json_encode(['ok' => false, 'error' => 'Please enter a message about a film.']); // Output validation error
        exit; // Stop execution
    }
    if (mb_strlen($message) > 800) { // Enforce 800-character upper bound to control token usage
        http_response_code(400); // Return HTTP 400 Bad Request
        echo json_encode(['ok' => false, 'error' => 'Message is too long (maximum 800 characters).']); // Output length error
        exit; // Stop execution
    }

    // Optional film context and existing conversation ID
    $filmId  = isset($input['film_id']) && (int)$input['film_id'] > 0 ? (int)$input['film_id'] : null; // Optional film ID for page-aware context
    $history = is_array($input['history'] ?? null) ? $input['history'] : []; // Fallback client-supplied history array
    $convId  = isset($input['conversation_id']) && (int)$input['conversation_id'] > 0 ? (int)$input['conversation_id'] : 0; // Optional existing thread ID

    // Load persisted conversation history if conversation_id belongs to the user
    $chatRepo = null; // Initialize repository variable
    if ($userId > 0) { // Load server-side history for authenticated user
        $chatRepo = new AiChatRepository(); // Instantiate chat repository
        if ($convId > 0 && $chatRepo->getConversation($convId, $userId)) { // Verify thread ownership before loading history
            $history = $chatRepo->getMessages($convId, $userId); // Override client history with authoritative DB messages
        } else {
            $convId = 0; // Reset to 0 so a new conversation will be created
        }
    }

    // Query AI service with message, prior history, and optional film context
    $result = AiService::askFilmAssistant($message, $history, $filmId); // Call external LLM API via AiService
    if (!$result['ok']) { // Handle upstream AI API failure
        http_response_code(502); // Return HTTP 502 Bad Gateway
        echo json_encode($result, JSON_UNESCAPED_UNICODE); // Forward error payload to client
        exit; // Stop execution
    }

    // Persist user prompt and AI reply to the database
    if ($userId > 0 && $chatRepo !== null) { // Save conversation turn when user is authenticated
        if ($convId === 0) { // Create a new conversation thread on first message
            $convId = $chatRepo->createConversation($userId, $message); // Generate thread title from initial prompt
        }
        $chatRepo->appendMessage($convId, 'user', $message); // Store user prompt in DB
        $chatRepo->appendMessage($convId, 'assistant', $result['reply']); // Store AI response in DB
        $conv = $chatRepo->getConversation($convId, $userId); // Reload conversation metadata (e.g., title)

        $result['conversation_id']    = $convId; // Attach conversation ID to JSON response
        $result['conversation_title'] = $conv['title'] ?? ''; // Attach conversation title to JSON response
    } else {
        $result['guest'] = true; // Flag guest mode if reached without persistence
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE); // Send successful AI response as JSON
} catch (Throwable $e) { // Catch any unexpected runtime or database exceptions
    http_response_code(500); // Return HTTP 500 Internal Server Error
    echo json_encode(['ok' => false, 'error' => 'Server error while contacting AI assistant.']); // Return safe generic error
}
