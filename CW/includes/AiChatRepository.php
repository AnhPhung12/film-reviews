<?php
require_once __DIR__ . '/DatabaseConnection.php'; // Load Singleton PDO connector

/**
 * AiChatRepository.php — Persistence Repository for Cine AI Chat.
 * Purpose: Stores and retrieves persistent AI chat conversations and messages for logged-in users.
 * Connections:
 *   - Used by: ai_chat.php (AJAX chat endpoint).
 *   - Connects to DB via: DatabaseConnection::getInstance() (Singleton PDO).
 * OOP/Patterns: Repository Pattern with idempotent schema initialization (ensureTables()).
 * Data Structures & Algorithms:
 *   - Relational one-to-many tables (`ai_conversations` -> `ai_chat_messages`) with cascading deletes.
 *   - Truncates first user message to 65 chars to auto-generate readable conversation titles.
 */
class AiChatRepository
{
    private PDO $db; // Shared PDO database connection
    private static bool $tablesChecked = false; // Request-scoped guard so DDL runs at most once

    /** Accepts an optional PDO instance (for DI/testing) or uses the shared Singleton connection. */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DatabaseConnection::getInstance()->getConnection(); // Inject PDO or fall back to Singleton
        $this->ensureTables(); // Guarantee AI chat tables exist before any query runs
    }

    /** Creates AI chat tables if they do not yet exist in the database. */
    private function ensureTables(): void
    {
        if (self::$tablesChecked) { // Skip DDL if already verified during this request
            return;
        }
        // Parent table storing per-user conversation threads
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS ai_conversations (
                id INT(11) NOT NULL AUTO_INCREMENT,
                reviewer_id INT(11) NOT NULL,
                title VARCHAR(150) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_ai_conv_reviewer (reviewer_id, updated_at),
                CONSTRAINT fk_ai_conv_reviewer FOREIGN KEY (reviewer_id) REFERENCES reviewer (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        // Child table storing chronological user/assistant messages per conversation
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS ai_chat_messages (
                id INT(11) NOT NULL AUTO_INCREMENT,
                conversation_id INT(11) NOT NULL,
                role ENUM('user','assistant') NOT NULL,
                content TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_ai_msg_conv (conversation_id, id),
                CONSTRAINT fk_ai_msg_conv FOREIGN KEY (conversation_id) REFERENCES ai_conversations (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        self::$tablesChecked = true; // Mark schema as verified for the remainder of the request
    }

    /** Lists a user's recent conversations ordered by most recently updated. */
    public function listConversations(int $userId, int $limit = 30): array
    {
        $st = $this->db->prepare("
            SELECT id, title, created_at, updated_at
            FROM ai_conversations
            WHERE reviewer_id = ?
            ORDER BY updated_at DESC, id DESC
            LIMIT ?
        "); // Prepare query for user's conversation list
        $st->bindValue(1, $userId, PDO::PARAM_INT); // Bind reviewer ID as integer
        $st->bindValue(2, $limit, PDO::PARAM_INT); // Bind row limit as integer
        $st->execute(); // Execute prepared statement
        return $st->fetchAll(); // Return matching conversation rows
    }

    /** Fetches a single conversation only if it belongs to the given user (ownership check). */
    public function getConversation(int $conversationId, int $userId): ?array
    {
        $st = $this->db->prepare("
            SELECT id, title, created_at, updated_at
            FROM ai_conversations
            WHERE id = ? AND reviewer_id = ?
        "); // Prepare ownership-scoped lookup
        $st->execute([$conversationId, $userId]); // Execute with conversation and user IDs
        return $st->fetch() ?: null; // Return row or null if not found/not owned
    }

    /** Returns all messages for an owned conversation in chronological order. */
    public function getMessages(int $conversationId, int $userId): array
    {
        $conv = $this->getConversation($conversationId, $userId); // Verify user owns this conversation
        if (!$conv) { // Return empty array if conversation does not exist or is unauthorized
            return [];
        }
        $st = $this->db->prepare("
            SELECT role, content, created_at
            FROM ai_chat_messages
            WHERE conversation_id = ?
            ORDER BY id ASC
        "); // Prepare chronological message query
        $st->execute([$conversationId]); // Fetch all messages for this thread
        return $st->fetchAll(); // Return ordered message list
    }

    /** Creates a new conversation thread with a normalized title derived from the first message. */
    public function createConversation(int $userId, string $firstMessage): int
    {
        // Collapse whitespace and cap title length at 65 characters
        $title = trim(preg_replace('/\s+/', ' ', $firstMessage)); // Normalize whitespace into single spaces
        if (mb_strlen($title) > 65) { // Truncate long prompts to fit sidebar UI
            $title = mb_substr($title, 0, 62) . '...'; // Keep 62 chars plus ellipsis
        }
        if ($title === '') { // Fallback title if message was whitespace-only
            $title = 'Film Chat';
        }
        $st = $this->db->prepare("INSERT INTO ai_conversations (reviewer_id, title) VALUES (?, ?)"); // Prepare thread insert
        $st->execute([$userId, $title]); // Insert new conversation row
        return (int)$this->db->lastInsertId(); // Return newly generated conversation ID
    }

    /** Appends a message to a conversation and bumps its updated_at timestamp. */
    public function appendMessage(int $conversationId, string $role, string $content): void
    {
        $st = $this->db->prepare("INSERT INTO ai_chat_messages (conversation_id, role, content) VALUES (?, ?, ?)"); // Prepare message insert
        $st->execute([$conversationId, $role, $content]); // Store user or assistant message
        $this->db->prepare("UPDATE ai_conversations SET updated_at = NOW() WHERE id = ?")->execute([$conversationId]); // Touch parent timestamp for sorting
    }

    /** Deletes a conversation owned by the given user (cascades to ai_chat_messages). */
    public function deleteConversation(int $conversationId, int $userId): bool
    {
        $st = $this->db->prepare("DELETE FROM ai_conversations WHERE id = ? AND reviewer_id = ?"); // Prepare ownership-checked delete
        $st->execute([$conversationId, $userId]); // Execute deletion
        return $st->rowCount() > 0; // Return true only if a row was actually deleted
    }
}
