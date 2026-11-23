<?php
require_once __DIR__ . '/DataBaseFunctions.php'; // Load CinemaRepository for grounding prompts in live DB data

/**
 * AiService.php — External AI API Integration (Gemini / OpenAI-compatible).
 * Purpose: Powers the Cine AI Assistant by grounding LLM replies in live MySQL film & review data.
 * Connections:
 *   - Called by: ai_chat.php (AJAX endpoint), paired with AiChatRepository (chat history persistence).
 *   - Reads API credentials from: ai_config.php; fetches film context from: CinemaRepository.
 * OOP/Patterns: Static Service Facade with provider-strategy dispatch (Gemini vs. OpenAI-compatible).
 * Data Structures & Algorithms:
 *   1. Builds a system prompt by injecting serialized film/review context from the DB (RAG-lite).
 *   2. Sanitizes conversation history (bounded array slice of last 8 turns + role allowlist) to prevent prompt bloat.
 *   3. Dispatches HTTPS POST JSON payload via PHP cURL (CURLOPT_POST) and decodes JSON response.
 */
class AiService
{
    /** Main entry point: prepares context/history and routes the request to the configured AI provider. */
    public static function askFilmAssistant(string $userMessage, array $history = [], ?int $filmId = null): array
    {
        $cfg = self::loadConfig(); // Load AI provider configuration array
        $provider = strtolower(trim((string)($cfg['provider'] ?? 'gemini'))); // Normalize selected provider name
        $apiKey = $provider === 'openai'
            ? trim((string)($cfg['openai_api_key'] ?? ''))
            : trim((string)($cfg['gemini_api_key'] ?? ''));

        // Fallback to local database film catalog when no external AI key is configured (e.g. local grading)
        if ($apiKey === '') {
            return self::localCatalogFallback($userMessage, $filmId);
        }

        if (!function_exists('curl_init')) { // Fail gracefully if PHP cURL extension is unavailable
            return ['ok' => false, 'reply' => '', 'error' => 'PHP cURL extension is not enabled.'];
        }

        // Ground the model with live catalog data and clean the recent chat turns
        $systemPrompt = self::buildSystemPrompt($userMessage, $filmId); // Build catalog-aware system prompt
        $cleanHistory = self::sanitizeHistory($history); // Keep last 8 valid turns to cap token usage

        if ($provider === 'openai') { // Route to OpenAI-compatible endpoint when configured
            return self::callOpenAiCompatible($cfg, $systemPrompt, $cleanHistory, $userMessage);
        }

        return self::callGemini($cfg, $systemPrompt, $cleanHistory, $userMessage); // Default to Google Gemini API
    }

    /** Provides an offline film-catalog response when no external AI API key is configured. */
    private static function localCatalogFallback(string $userMessage, ?int $filmId = null): array
    {
        $repo  = new CinemaRepository();
        $films = $repo->getAllFilms();
        $lower = mb_strtolower($userMessage);

        $matched = null;
        if ($filmId !== null && $filmId > 0) {
            $matched = $repo->getFilmById($filmId);
        }

        if (!$matched) {
            foreach ($films as $f) {
                $title = trim((string)($f['title'] ?? ''));
                if ($title !== '' && mb_stripos($lower, mb_strtolower($title)) !== false) {
                    $matched = $repo->getFilmById((int)$f['id']) ?: $f;
                    break;
                }
            }
        }

        if ($matched) {
            $title    = $matched['title'] ?? 'Untitled';
            $year     = !empty($matched['release_year']) ? " ({$matched['release_year']})" : '';
            $genre    = $matched['genre_name'] ?? 'Unknown';
            $country  = $matched['country'] ?? '';
            $dur      = !empty($matched['duration_min']) ? $matched['duration_min'] . ' min' : '';
            $cast     = trim((string)($matched['cast'] ?? ''));
            $synopsis = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string)($matched['synopsis'] ?? '')), ENT_QUOTES, 'UTF-8')));

            $metaParts = array_filter([$genre, $country, $dur]);
            $lines = ["🎬 **{$title}{$year}**"];
            if ($metaParts) {
                $lines[] = implode(' • ', $metaParts);
            }
            if ($cast !== '') {
                $lines[] = "Starring: {$cast}";
            }
            $lines[] = '';
            $lines[] = $synopsis !== '' ? $synopsis : 'No plot synopsis is stored for this film yet.';

            $reviews = $repo->getReviewsByFilm((int)$matched['id']);
            if (!empty($reviews)) {
                $avg = number_format(array_sum(array_column($reviews, 'rating')) / count($reviews), 1);
                $lines[] = '';
                $lines[] = "⭐ Cine Rating: {$avg}/5 (" . count($reviews) . ' review' . (count($reviews) > 1 ? 's' : '') . ')';
            }

            $lines[] = '';
            $lines[] = '(Local Catalog Mode: Gemini API key not set — showing summary from Cine database.)';

            return ['ok' => true, 'reply' => implode("\n", $lines), 'error' => ''];
        }

        $sampleTitles = array_slice(array_map(fn($f) => $f['title'] . (!empty($f['release_year']) ? " ({$f['release_year']})" : ''), $films), 0, 8);
        $reply = "Hi! I'm running in **Local Catalog Mode** (no Gemini API key configured).\n\n"
            . "Ask me about any film in Cine's database, for example:\n• "
            . (!empty($sampleTitles) ? implode("\n• ", $sampleTitles) : 'No films found in database.');

        return ['ok' => true, 'reply' => $reply, 'error' => ''];
    }

    /** Loads AI provider settings from ai_config.php. */
    private static function loadConfig(): array
    {
        $path = __DIR__ . '/ai_config.php'; // Resolve path to local AI config file
        return is_file($path) ? (array)(require $path) : []; // Return config array or empty fallback
    }

    /** Constructs the system prompt with Cine's film catalog and optional focused film reviews. */
    private static function buildSystemPrompt(string $userMessage, ?int $filmId): string
    {
        $repo = new CinemaRepository(); // Instantiate repository to query film catalog
        $films = $repo->getAllFilms(); // Fetch all films with ratings and review counts

        $catalogLines = []; // Accumulator for formatted film summary lines
        $matchedFilmId = $filmId; // Track explicit or auto-detected film ID for deeper context
        $lowerMsg = mb_strtolower($userMessage); // Lowercase prompt for case-insensitive title matching

        // Serialize each film into a compact line and auto-detect film mentions in the user prompt
        foreach ($films as $f) {
            $title = $f['title'] ?? ''; // Film title
            $year = !empty($f['release_year']) ? $f['release_year'] : 'N/A'; // Release year or fallback
            $genre = $f['genre_name'] ?? 'Unknown'; // Genre label
            $dur = !empty($f['duration_min']) ? $f['duration_min'] . ' min' : 'N/A'; // Runtime in minutes
            $avg = !empty($f['avg_rating']) ? number_format((float)$f['avg_rating'], 1) . '/5' : 'No ratings yet'; // Formatted average rating
            $count = (int)($f['review_count'] ?? 0); // Total community reviews
            $catalogLines[] = "- [ID {$f['id']}] {$title} ({$year}) | Genre: {$genre} | Duration: {$dur} | Cine Rating: {$avg} ({$count} reviews)"; // Append compact catalog entry

            if ($matchedFilmId === null && $title !== '' && mb_stripos($lowerMsg, mb_strtolower($title)) !== false) { // Auto-focus film if mentioned by name
                $matchedFilmId = (int)$f['id'];
            }
        }

        $catalogText = !empty($catalogLines) // Join catalog lines or show empty-database placeholder
            ? implode("\n", $catalogLines)
            : '(No films currently in the database)';

        // If a specific film is in focus, attach up to 5 recent community review snippets
        $focusSection = ''; // Extra context block for focused film
        if ($matchedFilmId !== null && $matchedFilmId > 0) { // Only query details when a valid film ID is known
            $film = $repo->getFilmById($matchedFilmId); // Fetch full film record
            if ($film) {
                $reviews = $repo->getReviewsByFilm($matchedFilmId); // Fetch community reviews for this film
                $reviewSnippets = []; // Store truncated review excerpts
                foreach (array_slice($reviews, 0, 5) as $r) { // Cap at 5 most recent reviews
                    $snippet = mb_substr(trim((string)($r['text'] ?? '')), 0, 180); // Truncate review text to 180 chars
                    $reviewSnippets[] = "  * {$r['fullname']} ({$r['rating']}/5): \"{$snippet}\""; // Format reviewer attribution and rating
                }
                $reviewsBlock = !empty($reviewSnippets) // Combine review snippets or note none exist
                    ? implode("\n", $reviewSnippets)
                    : '  * No community reviews yet.';

                $focusSection = "\n\nFocused Film on Cine:\n" // Build focused film metadata block
                    . "Title: {$film['title']}\n"
                    . "Genre: " . ($film['genre_name'] ?? 'Unknown') . "\n"
                    . "Release Year: " . ($film['release_year'] ?? 'N/A') . "\n"
                    . "Duration: " . (!empty($film['duration_min']) ? $film['duration_min'] . ' min' : 'N/A') . "\n"
                    . "Recent Cine User Reviews:\n{$reviewsBlock}";
            }
        }

        return <<<PROMPT
You are Cine AI, a warm, engaging film-lover assistant built into the Cine Film Review website.

Your job:
1. Default to clear, natural English. However, if the user writes in another language or explicitly asks you to translate or reply in another language (e.g. Vietnamese), fulfill their request in that language.
2. When the user asks about any film (whether it is in Cine's catalog or any other movie), tell and summarize the story/plot of that film clearly and vividly:
   - Setting & premise
   - Main character(s) and their goal/conflict
   - Tone & themes (keep major ending twists spoiler-free unless the user specifically asks for spoilers or the full ending).
3. If the film exists in Cine's catalog below, mention its Cine details (genre, release year, duration, current Cine rating) and encourage the user to check out or write a review on Cine.
4. If the user asks for recommendations, suggest great matching films (prioritizing Cine's catalog if relevant, plus other well-known titles).
5. Keep answers concise, warm, and easy to read in a small chat box (avoid using markdown heading symbols like ### or divider lines like ---).

Cine Film Catalog:
{$catalogText}{$focusSection}
PROMPT; // Return complete instruction + RAG context prompt
    }

    /** Keeps at most the last 8 valid messages and truncates each to 1500 chars. */
    private static function sanitizeHistory(array $history): array
    {
        $clean = []; // Sanitized turn list
        foreach (array_slice($history, -8) as $item) { // Retain only the 8 most recent turns
            if (!is_array($item)) { // Skip malformed entries
                continue;
            }
            $role = ($item['role'] ?? '') === 'assistant' ? 'assistant' : 'user'; // Restrict role to allowlisted values
            $text = trim((string)($item['content'] ?? '')); // Normalize message content
            if ($text !== '') { // Ignore empty messages
                $clean[] = ['role' => $role, 'content' => mb_substr($text, 0, 1500)]; // Cap message length to prevent prompt bloat
            }
        }
        return $clean; // Return sanitized conversation history
    }

    /** Formats and dispatches a chat request to the Google Gemini generateContent API. */
    private static function callGemini(array $cfg, string $systemPrompt, array $history, string $userMessage): array
    {
        $apiKey = trim((string)($cfg['gemini_api_key'] ?? '')); // Read Gemini API key from config
        $model = trim((string)($cfg['gemini_model'] ?? 'gemini-3.5-flash-lite')) ?: 'gemini-3.5-flash-lite'; // Read model name with fallback

        if ($apiKey === '') { // Abort early if API key is missing
            return [
                'ok'    => false,
                'reply' => '',
                'error' => 'Gemini API key is not configured yet. Please add your key in CW/includes/ai_config.php.',
            ];
        }

        // Map internal 'assistant' role to Gemini's 'model' role
        $contents = []; // Gemini conversation turns array
        foreach ($history as $msg) { // Convert each sanitized history turn
            $contents[] = [
                'role'  => $msg['role'] === 'assistant' ? 'model' : 'user', // Map assistant -> model
                'parts' => [['text' => $msg['content']]], // Wrap text in Gemini parts structure
            ];
        }
        $contents[] = [ // Append current user message as the final turn
            'role'  => 'user',
            'parts' => [['text' => $userMessage]],
        ];

        $payload = [ // Build Gemini generateContent JSON request body
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature'     => 0.7, // Balanced creativity
                'maxOutputTokens' => 900, // Keep replies concise for chat widget
            ],
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . urlencode($apiKey); // Construct Gemini REST endpoint URL
        $res = self::postJson($url, $payload, ['Content-Type: application/json']); // Send POST request via cURL
        if (!$res['ok']) { // Propagate network or HTTP error
            return ['ok' => false, 'reply' => '', 'error' => $res['error']];
        }

        // Concatenate text parts from the first candidate response
        $data = $res['data']; // Decoded JSON response
        $parts = $data['candidates'][0]['content']['parts'] ?? []; // Extract parts array from top candidate
        $text = ''; // Accumulator for response text
        foreach ($parts as $part) { // Join all text parts
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
        }
        $text = trim($text); // Trim surrounding whitespace
        if ($text === '') { // Handle empty response or API-level error payload
            $apiErr = $data['error']['message'] ?? 'Empty response from Gemini API.';
            return ['ok' => false, 'reply' => '', 'error' => $apiErr];
        }

        return ['ok' => true, 'reply' => $text, 'error' => '']; // Return successful assistant reply
    }

    /** Formats and dispatches a chat request to an OpenAI-compatible /chat/completions endpoint. */
    private static function callOpenAiCompatible(array $cfg, string $systemPrompt, array $history, string $userMessage): array
    {
        $apiKey = trim((string)($cfg['openai_api_key'] ?? '')); // Read OpenAI-compatible API key
        $baseUrl = rtrim(trim((string)($cfg['openai_base_url'] ?? 'https://api.openai.com/v1')), '/'); // Normalize base URL without trailing slash
        $model = trim((string)($cfg['openai_model'] ?? 'gpt-4o-mini')) ?: 'gpt-4o-mini'; // Read model name with fallback

        if ($apiKey === '') { // Abort early if API key is missing
            return [
                'ok'    => false,
                'reply' => '',
                'error' => 'OpenAI API key is not configured yet. Please add your key in CW/includes/ai_config.php.',
            ];
        }

        $messages = [['role' => 'system', 'content' => $systemPrompt]]; // Start with system instruction message
        foreach ($history as $msg) { // Append sanitized conversation history
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage]; // Append current user prompt

        $payload = [ // Build OpenAI chat completions payload
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => 0.7,
            'max_tokens'  => 900,
        ];

        $res = self::postJson($baseUrl . '/chat/completions', $payload, [ // Send authenticated POST request
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);
        if (!$res['ok']) { // Propagate network or HTTP error
            return ['ok' => false, 'reply' => '', 'error' => $res['error']];
        }

        $data = $res['data']; // Decoded JSON response
        $text = trim((string)($data['choices'][0]['message']['content'] ?? '')); // Extract assistant message content
        if ($text === '') { // Handle empty response or API error message
            $apiErr = $data['error']['message'] ?? 'Empty response from AI provider.';
            return ['ok' => false, 'reply' => '', 'error' => $apiErr];
        }

        return ['ok' => true, 'reply' => $text, 'error' => '']; // Return successful assistant reply
    }

    /** Executes an HTTP POST JSON request via cURL and validates the decoded response. */
    private static function postJson(string $url, array $payload, array $headers): array
    {
        $ch = curl_init($url); // Initialize cURL handle for target endpoint
        curl_setopt_array($ch, [ // Configure cURL for JSON POST with timeouts
            CURLOPT_POST           => true, // Use HTTP POST method
            CURLOPT_RETURNTRANSFER => true, // Return response body as string
            CURLOPT_HTTPHEADER     => $headers, // Attach Content-Type / Auth headers
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE), // Encode payload as UTF-8 JSON
            CURLOPT_TIMEOUT        => 25, // Maximum total request duration (seconds)
            CURLOPT_CONNECTTIMEOUT => 10, // Maximum connection handshake duration (seconds)
        ]);

        $raw = curl_exec($ch); // Execute HTTP request
        $curlErr = curl_error($ch); // Capture transport-level error if any
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); // Read HTTP status code
        curl_close($ch); // Free cURL resource

        if ($raw === false) { // Fail on network/transport error
            return ['ok' => false, 'data' => [], 'error' => 'Network error contacting AI service: ' . $curlErr];
        }

        $decoded = json_decode($raw, true); // Decode JSON response body into associative array
        if (!is_array($decoded)) { // Fail if response is not valid JSON
            return ['ok' => false, 'data' => [], 'error' => "Invalid response from AI service (HTTP {$httpCode})."];
        }

        if ($httpCode >= 400) { // Fail on HTTP 4xx/5xx error responses
            $errMsg = $decoded['error']['message'] ?? "AI service returned HTTP {$httpCode}.";
            return ['ok' => false, 'data' => $decoded, 'error' => $errMsg];
        }

        return ['ok' => true, 'data' => $decoded, 'error' => '']; // Return decoded JSON payload
    }
}
