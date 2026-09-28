<?php
/**
 * RequestDesk Claude AI Integration
 *
 * Handles all Claude AI API interactions for AEO features
 */

class RequestDesk_Claude_Integration {

    private $api_key;
    private $api_url = 'https://api.anthropic.com/v1/messages';
    private $model;

    public function __construct() {
        $settings = get_option('requestdesk_settings', array());
        $this->api_key = $settings['claude_api_key'] ?? '';
        $this->model = $settings['claude_model'] ?? 'claude-sonnet-4-5-20250929';
    }

    /**
     * Check if Claude integration is available
     */
    public function is_available() {
        return !empty($this->api_key);
    }

    /**
     * Make a request to Claude API
     */
    private function make_request($prompt, $max_tokens = 4096) {
        if (!$this->is_available()) {
            return new WP_Error('no_api_key', 'Claude API key not configured');
        }

        $headers = array(
            'Content-Type' => 'application/json',
            'x-api-key' => $this->api_key,
            'anthropic-version' => '2023-06-01'
        );

        $body = array(
            'model' => $this->model,
            'max_tokens' => $max_tokens,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );

        $response = wp_remote_post($this->api_url, array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('api_error', $data['error']['message'] ?? 'Claude API error');
        }

        return $data['content'][0]['text'] ?? '';
    }

    /**
     * Analyze content quality and structure for AEO
     */
    public function analyze_content($title, $content) {
        $prompt = "Analyze this WordPress post for Answer Engine Optimization (AEO). Provide a detailed analysis in JSON format.

Title: {$title}

Content: {$content}

Please analyze and return JSON with these fields:
- aeo_score: Overall AEO readiness score (0-100)
- readability_score: Content readability score (0-100)
- structure_score: Content structure score (0-100)
- keyword_density: Object with primary keywords and their density
- improvements: Array of specific improvement suggestions
- questions_answered: Array of questions this content answers
- missing_elements: Array of missing AEO elements
- schema_suggestions: Array of recommended schema types

Focus on how well this content would perform in AI search engines and answer engines.";

        $result = $this->make_request($prompt);

        if (is_wp_error($result)) {
            return $result;
        }

        // Try to extract JSON from the response
        if (preg_match('/\{.*\}/s', $result, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return $json;
            }
        }

        return new WP_Error('parse_error', 'Could not parse Claude response');
    }

    /**
     * Extract Q&A pairs from content
     */
    public function extract_qa_pairs($title, $content) {
        $prompt = "Extract question and answer pairs from this WordPress post content. Return as JSON array.

Title: {$title}

Content: {$content}

Extract Q&A pairs that:
1. Are directly answered by the content
2. Would be useful for FAQ schema markup
3. Are relevant for voice search and AI assistants
4. Cover the main topics and subtopics

Return JSON array with objects containing:
- question: The question being answered
- answer: Concise answer (1-2 sentences max)
- confidence: Confidence score (0-100) that this Q&A is accurate
- type: Type of question (factual, how-to, definition, comparison, etc.)
- keywords: Array of relevant keywords for this Q&A

Limit to the 10 most important Q&A pairs.";

        $result = $this->make_request($prompt);

        if (is_wp_error($result)) {
            return $result;
        }

        // Try to extract JSON from the response
        if (preg_match('/\[.*\]/s', $result, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return $json;
            }
        }

        return new WP_Error('parse_error', 'Could not parse Claude Q&A response');
    }

    /**
     * Generate content optimization suggestions
     */
    public function get_optimization_suggestions($title, $content) {
        $prompt = "Provide specific optimization suggestions for this WordPress post to improve its performance in AI search engines and answer engines.

Title: {$title}

Content: {$content}

Analyze and provide JSON with:
- title_suggestions: Array of improved title variations
- heading_improvements: Array of better H2/H3 heading suggestions
- content_gaps: Array of missing information that should be added
- semantic_improvements: Suggestions for better semantic structure
- answer_engine_optimization: Specific tips for AI search engines
- featured_snippet_potential: How to optimize for featured snippets
- voice_search_optimization: Tips for voice search optimization

Focus on actionable, specific improvements rather than general advice.";

        $result = $this->make_request($prompt);

        if (is_wp_error($result)) {
            return $result;
        }

        // Try to extract JSON from the response
        if (preg_match('/\{.*\}/s', $result, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return $json;
            }
        }

        return new WP_Error('parse_error', 'Could not parse Claude optimization response');
    }

    /**
     * Generate schema markup suggestions
     * Enhanced for AI-First schema optimization
     */
    public function generate_schema_suggestions($title, $content, $post_type = 'article') {
        $prompt = "Analyze this WordPress content and suggest optimal schema.org markup for AI/LLM visibility.

Title: {$title}
Content: {$content}
Post Type: {$post_type}

IMPORTANT: Focus on schema that will help AI assistants (ChatGPT, Claude, Perplexity, Google AI Overviews) understand, cite, and reference this content accurately.

Analyze the content and return JSON with:

1. primary_schema: Main schema type. Choose from:
   - Article (for blog posts, news)
   - Product (for product pages, reviews with pricing)
   - LocalBusiness (for location-based content with addresses, hours)
   - VideoObject (for video content)
   - Course (for educational/training content)
   - HowTo (for instructional step-by-step content)
   - FAQPage (for Q&A content)

2. additional_schemas: Array of secondary schema types that should also be applied (can include multiple)

3. detected_signals: What content signals led to your recommendation:
   - For Product: price patterns, SKU, availability, ratings found
   - For LocalBusiness: address, phone, hours, location keywords
   - For Video: embedded videos, video URLs detected
   - For Course: learning objectives, modules, enrollment keywords
   - For HowTo: step patterns, instructional language
   - For FAQ: Q&A patterns detected

4. confidence_score: 0-100 confidence in primary schema recommendation

5. extracted_data: Any data values extracted that should populate the schema:
   - product: { price, currency, rating, brand, availability }
   - local_business: { address, phone, hours, business_type }
   - video: { video_ids, platform, duration }
   - course: { duration, instructor, price, level }

6. ai_optimization_tips: Specific tips to make schema more AI-friendly:
   - What additional properties would help LLMs cite this content
   - What structured data would improve answer engine responses

7. faq_schema: FAQ schema if Q&A content is present

8. breadcrumb_suggestions: Suggested breadcrumb structure

Focus on schema that will help this content appear in AI-generated summaries and get cited by AI assistants.";

        $result = $this->make_request($prompt);

        if (is_wp_error($result)) {
            return $result;
        }

        // Try to extract JSON from the response
        if (preg_match('/\{.*\}/s', $result, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return $json;
            }
        }

        return new WP_Error('parse_error', 'Could not parse Claude schema response');
    }

    /**
     * Assess content freshness and update recommendations
     */
    public function assess_content_freshness($title, $content, $post_date, $last_modified) {
        $post_age = (time() - strtotime($post_date)) / (60 * 60 * 24); // Days
        $last_update_age = (time() - strtotime($last_modified)) / (60 * 60 * 24); // Days

        $prompt = "Assess the freshness and update needs for this WordPress post content.

Title: {$title}
Content: {$content}
Post Age: {$post_age} days
Last Updated: {$last_update_age} days ago

Analyze and return JSON with:
- freshness_score: Content freshness score (0-100)
- update_priority: Priority level (low, medium, high, urgent)
- outdated_elements: Array of specific outdated information found
- update_suggestions: Array of specific updates needed
- evergreen_score: How evergreen/timeless this content is (0-100)
- trending_opportunities: Current trends this content could capitalize on
- competitive_gaps: Areas where content could be strengthened vs competitors

Focus on actionable insights for content updates and improvements.";

        $result = $this->make_request($prompt);

        if (is_wp_error($result)) {
            return $result;
        }

        // Try to extract JSON from the response
        if (preg_match('/\{.*\}/s', $result, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return $json;
            }
        }

        return new WP_Error('parse_error', 'Could not parse Claude freshness response');
    }

    /**
     * Catch link-shaped text a plain-HTML regex can't reach -- bare URLs,
     * spelled-out or obfuscated domains ("example dot com", "example[.]com"),
     * anything a spammer typed as plain text instead of an <a> tag.
     *
     * A LIGHT EDIT ONLY: removes link-shaped text, changes nothing else.
     * Used by RequestDesk_Comment_Link_Stripper as a second pass after its
     * own regex has already stripped real <a href> tags. Returns the edited
     * text, or a WP_Error the caller falls back from -- moderation must never
     * block on this call failing.
     */
    public function strip_remaining_links($content, $allowed_domains = array()) {
        $allowed_domains = array_filter(array_map('strval', (array) $allowed_domains));
        $exception = '';
        if (!empty($allowed_domains)) {
            $exception = " The ONLY exception: leave alone anything that points to " . implode(', ', $allowed_domains) . " (including subdomains of those) -- those are our own sites, not spam.";
        }

        $prompt = "You are cleaning a WordPress comment a human moderator already approved for its content. An automated pass already removed every <a href> link that doesn't point to one of our own domains. Your ONLY job is to find and remove any remaining link-shaped text this comment still contains -- a bare web address typed as plain text (an http/https or www-prefixed link), a spelled-out or obfuscated domain (\"example dot com\", \"example[.]com\"), or any other text whose sole purpose is to point somewhere else.{$exception}

Rules:
- Remove ONLY link-shaped text. Do not paraphrase, reword, summarize, or correct anything else.
- Every other word must be identical to the input, in the same order.
- If there is nothing link-shaped to remove, return the comment completely unchanged.
- Return ONLY the edited comment text. No preamble, no explanation, no surrounding quotes, no markdown.

Comment:
{$content}";

        $result = $this->make_request($prompt, 1024);

        if (is_wp_error($result)) {
            return $result;
        }

        $cleaned = trim($result);
        // Claude sometimes wraps output in quotes despite the instruction not to.
        $cleaned = trim($cleaned, "\"'`");

        if ($cleaned === '') {
            return new WP_Error('requestdesk_empty_response', 'Claude returned an empty comment');
        }

        return $cleaned;
    }

    /**
     * Test Claude API connection
     */
    public function test_connection() {
        $result = $this->make_request("Test connection. Please respond with 'Claude API connection successful'", 100);

        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'status' => 'success',
            'response' => $result,
            'model' => $this->model
        );
    }
}