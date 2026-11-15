<?php
/**
 * ai_config.php — AI Assistant Configuration.
 * Purpose: Stores provider selection, API keys, and model settings for Cine AI Chat.
 * Connections: Loaded by AiService::loadConfig() during chat API requests.
 * OOP/Patterns: Configuration array module (environment-variable fallback pattern).
 * Data Structures & Algorithms: Associative array mapping setting keys to string values.
 *
 * Recommended (free tier available): Gemini API
 * 1. Create an API key at https://aistudio.google.com/apikey
 * 2. Paste it into 'gemini_api_key' below (or set GEMINI_API_KEY env var).
 *
 * Alternative: OpenAI / Groq / OpenRouter (OpenAI-compatible endpoint)
 * Set 'provider' => 'openai' and fill in 'openai_api_key', 'openai_base_url', 'openai_model'.
 */
return [
    // Active AI provider: 'gemini' or 'openai'
    'provider'        => 'gemini',
    // Gemini API key (prefers GEMINI_API_KEY environment variable if set)
    'gemini_api_key'  => getenv('GEMINI_API_KEY') ?: '',
    // Default Gemini model identifier
    'gemini_model'    => 'gemini-3.5-flash-lite',
    // OpenAI-compatible API key (prefers OPENAI_API_KEY environment variable if set)
    'openai_api_key'  => getenv('OPENAI_API_KEY') ?: '',
    // Base URL for OpenAI-compatible chat completion endpoints
    'openai_base_url' => 'https://api.openai.com/v1',
    // Default OpenAI-compatible model identifier
    'openai_model'    => 'gpt-4o-mini',
];
