<?php
/**
 * RequestDesk Content Analyzer
 *
 * Analyzes post content for AEO optimization opportunities
 */

class RequestDesk_Content_Analyzer {

    private $claude_integration;

    public function __construct() {
        $this->claude_integration = new RequestDesk_Claude_Integration();
    }

    /**
     * Analyze content for AEO optimization
     */
    public function analyze_content($post) {
        $content = $post->post_content;
        $title = $post->post_title;

        // Start with basic analysis
        $analysis = array(
            'post_id' => $post->ID,
            'word_count' => $this->get_word_count($content),
            'readability_score' => $this->calculate_readability_score($content),
            'question_headings' => $this->count_question_headings($content),
            'has_clear_structure' => $this->has_clear_structure($content),
            'heading_hierarchy' => $this->analyze_heading_hierarchy($content),
            'internal_links' => $this->count_internal_links($content),
            'external_links' => $this->count_external_links($content),
            'images_with_alt' => $this->count_images_with_alt($content),
            'statistics_found' => $this->find_statistics($content),
            'content_freshness_indicators' => $this->find_freshness_indicators($content),
            'qa_potential' => $this->assess_qa_potential($content),
            'title_optimization' => $this->analyze_title($title),
            'content_depth_score' => $this->calculate_content_depth($content),
            'ai_readiness_score' => 0
        );

        // If Claude AI is available, enhance analysis with AI insights
        if ($this->claude_integration->is_available()) {
            $claude_analysis = $this->claude_integration->analyze_content($title, strip_tags($content));

            if (!is_wp_error($claude_analysis)) {
                // Merge Claude AI insights with basic analysis
                $analysis['claude_aeo_score'] = $claude_analysis['aeo_score'] ?? 0;
                $analysis['claude_readability_score'] = $claude_analysis['readability_score'] ?? $analysis['readability_score'];
                $analysis['claude_structure_score'] = $claude_analysis['structure_score'] ?? 0;
                $analysis['keyword_density'] = $claude_analysis['keyword_density'] ?? array();
                $analysis['improvements'] = $claude_analysis['improvements'] ?? array();
                $analysis['questions_answered'] = $claude_analysis['questions_answered'] ?? array();
                $analysis['missing_elements'] = $claude_analysis['missing_elements'] ?? array();
                $analysis['schema_suggestions'] = $claude_analysis['schema_suggestions'] ?? array();

                // Use Claude's AEO score as the primary AI readiness score
                $analysis['ai_readiness_score'] = $claude_analysis['aeo_score'] ?? $this->calculate_ai_readiness_score($analysis);
            } else {
                // Fallback to calculated score if Claude fails
                $analysis['ai_readiness_score'] = $this->calculate_ai_readiness_score($analysis);
                $analysis['claude_error'] = $claude_analysis->get_error_message();
            }
        } else {
            // Calculate AI readiness score using basic metrics
            $analysis['ai_readiness_score'] = $this->calculate_ai_readiness_score($analysis);
        }

        return $analysis;
    }

    /**
     * Extract question-answer pairs from content
     */
    public function extract_qa_pairs($content, $title = '') {
        // If Claude AI is available, use it for intelligent Q&A extraction
        if ($this->claude_integration->is_available()) {
            $claude_qa = $this->claude_integration->extract_qa_pairs($title, strip_tags($content));

            if (!is_wp_error($claude_qa) && is_array($claude_qa) && !empty($claude_qa)) {
                return $this->normalize_qa_questions($claude_qa);
            }
        }

        // Fallback to basic regex-based extraction
        $qa_pairs = array();

        // Remove HTML tags but keep structure
        $content = strip_tags($content);

        // Pattern 1: Direct question followed by answer
        $direct_pattern = '/^(.+\?)\s*\n+(.+?)(?=\n\n|\n[A-Z]|\Z)/m';
        preg_match_all($direct_pattern, $content, $direct_matches, PREG_SET_ORDER);

        foreach ($direct_matches as $match) {
            $question = trim($match[1]);
            $answer = trim($match[2]);

            if (strlen($answer) > 20 && strlen($answer) < 500) {
                $qa_pairs[] = array(
                    'question' => $question,
                    'answer' => $answer,
                    'type' => 'direct',
                    'confidence' => 0.9
                );
            }
        }

        // Pattern 2: Headings as questions with content as answers
        $heading_pattern = '/^(#{1,6})\s*(.+\?)\s*\n+(.+?)(?=\n#{1,6}|\Z)/m';
        preg_match_all($heading_pattern, $content, $heading_matches, PREG_SET_ORDER);

        foreach ($heading_matches as $match) {
            $question = trim($match[2]);
            $answer = trim($match[3]);

            if (strlen($answer) > 50 && strlen($answer) < 800) {
                $qa_pairs[] = array(
                    'question' => $question,
                    'answer' => $answer,
                    'type' => 'heading',
                    'confidence' => 0.8
                );
            }
        }

        // Pattern 3: Implicit questions (How to, What is, Why, When, Where)
        $implicit_patterns = array(
            '/^(How to .+?)\s*\n+(.+?)(?=\n\n|\n[A-Z]|\Z)/m',
            '/^(What is .+?)\s*\n+(.+?)(?=\n\n|\n[A-Z]|\Z)/m',
            '/^(Why .+?)\s*\n+(.+?)(?=\n\n|\n[A-Z]|\Z)/m',
            '/^(When .+?)\s*\n+(.+?)(?=\n\n|\n[A-Z]|\Z)/m',
            '/^(Where .+?)\s*\n+(.+?)(?=\n\n|\n[A-Z]|\Z)/m'
        );

        foreach ($implicit_patterns as $pattern) {
            preg_match_all($pattern, $content, $implicit_matches, PREG_SET_ORDER);

            foreach ($implicit_matches as $match) {
                $question = trim($match[1]);
                $answer = trim($match[2]);

                // Convert implicit to explicit question
                if (!preg_match('/\?$/', $question)) {
                    $question .= '?';
                }

                if (strlen($answer) > 30 && strlen($answer) < 600) {
                    $qa_pairs[] = array(
                        'question' => $question,
                        'answer' => $answer,
                        'type' => 'implicit',
                        'confidence' => 0.7
                    );
                }
            }
        }

        // Strip list numbering BEFORE dedup, so "2. Why X?" and "Why X?" are
        // seen as the same question rather than surviving as two.
        $qa_pairs = $this->normalize_qa_questions($qa_pairs);

        // Remove duplicates and sort by confidence
        $qa_pairs = $this->deduplicate_qa_pairs($qa_pairs);

        usort($qa_pairs, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        return array_slice($qa_pairs, 0, 10); // Limit to top 10
    }

    /**
     * Strip list numbering that a heading carried into its question text.
     *
     * Extraction lifts question-form headings out of the article, and when the
     * article numbers its sections the number comes along. The result reads as
     * broken in every place the question is then used.
     *
     * Live example, contentcucumber.com/blog/chatgpt-will-not-get-you-better-content
     * on 2026-08-01: the post is a numbered list of ten, five of whose headings
     * end in a question mark. Extraction took exactly those five, so the
     * "Frequently Asked Questions" block rendered as 2, 5, 6, 8, 9 -- opening at
     * "2." and skipping four numbers, referencing a list that is not in the box.
     * The same strings went into the FAQPage schema, so Google was handed a
     * question named "2. Why does AI content all sound the same?".
     *
     * Brent caught the sequence; the duplication had been the obvious defect and
     * the numbering read as a detail until you look at which numbers are missing.
     *
     * Handles "2.", "2)", "(2)", "Step 2:", "Q3." and the non-breaking space
     * WordPress likes to leave behind. A question that is ONLY a number is left
     * alone rather than emptied.
     *
     * Bounded to ONE OR TWO digits. A list does not reach a hundred items, and
     * the bound is what stops "2026: what changes?" losing its year -- a heading
     * that opens on a four-digit number is stating a year, not counting.
     *
     * @param array $pairs
     * @return array
     */
    protected function normalize_qa_questions($pairs) {
        if (!is_array($pairs)) {
            return array();
        }

        foreach ($pairs as $i => $pair) {
            if (!is_array($pair) || !isset($pair['question'])) {
                continue;
            }

            $q = (string) $pair['question'];
            $q = str_replace("\xc2\xa0", ' ', $q);          // nbsp -> space
            $stripped = preg_replace(
                '/^\s*(?:\(?\s*(?:step|q(?:uestion)?)?\s*\d{1,2}\s*\)?\s*[\.\):\-\x{2013}\x{2014}]\s*)+/iu',
                '',
                $q
            );

            // Only accept the strip if something survives it.
            $stripped = trim((string) $stripped);
            if ($stripped !== '') {
                $pairs[$i]['question'] = $stripped;
            } else {
                $pairs[$i]['question'] = trim($q);
            }
        }

        return $pairs;
    }

    /**
     * Remove duplicate Q&A pairs
     */
    private function deduplicate_qa_pairs($qa_pairs) {
        $seen_questions = array();
        $unique_pairs = array();

        foreach ($qa_pairs as $pair) {
            $question_key = strtolower(trim($pair['question'], '?'));

            if (!in_array($question_key, $seen_questions)) {
                $seen_questions[] = $question_key;
                $unique_pairs[] = $pair;
            }
        }

        return $unique_pairs;
    }

    /**
     * Calculate word count
     */
    private function get_word_count($content) {
        $content = strip_tags($content);
        return str_word_count($content);
    }

    /**
     * Calculate readability score (simplified Flesch Reading Ease)
     */
    private function calculate_readability_score($content) {
        $content = strip_tags($content);
        $words = str_word_count($content);

        if ($words === 0) {
            return 0;
        }

        $sentences = preg_split('/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $sentence_count = count($sentences);

        if ($sentence_count === 0) {
            return 0;
        }

        $syllables = $this->count_syllables($content);

        // Simplified Flesch Reading Ease formula
        $score = 206.835 - (1.015 * ($words / $sentence_count)) - (84.6 * ($syllables / $words));

        return max(0, min(100, round($score)));
    }

    /**
     * Count syllables in text (approximation)
     */
    private function count_syllables($text) {
        $words = str_word_count($text, 1);
        $syllable_count = 0;

        foreach ($words as $word) {
            $word = strtolower($word);
            $vowels = 'aeiouy';
            $syllables = 0;
            $prev_was_vowel = false;

            for ($i = 0; $i < strlen($word); $i++) {
                $is_vowel = strpos($vowels, $word[$i]) !== false;
                if ($is_vowel && !$prev_was_vowel) {
                    $syllables++;
                }
                $prev_was_vowel = $is_vowel;
            }

            // Handle silent 'e'
            if (substr($word, -1) === 'e' && $syllables > 1) {
                $syllables--;
            }

            $syllable_count += max(1, $syllables);
        }

        return $syllable_count;
    }

    /**
     * Count question headings
     */
    private function count_question_headings($content) {
        preg_match_all('/<h[1-6][^>]*>([^<]*\?[^<]*)<\/h[1-6]>/i', $content, $matches);
        return count($matches[0]);
    }

    /**
     * Check if content has clear structure
     */
    private function has_clear_structure($content) {
        $heading_count = preg_match_all('/<h[1-6][^>]*>/i', $content);
        $paragraph_count = preg_match_all('/<p[^>]*>/i', $content);
        $list_count = preg_match_all('/<[ou]l[^>]*>/i', $content);

        return ($heading_count >= 2 && $paragraph_count >= 3) || $list_count >= 1;
    }

    /**
     * Analyze heading hierarchy
     */
    private function analyze_heading_hierarchy($content) {
        preg_match_all('/<h([1-6])[^>]*>([^<]+)<\/h[1-6]>/i', $content, $matches, PREG_SET_ORDER);

        $hierarchy = array();
        foreach ($matches as $match) {
            $level = intval($match[1]);
            $text = trim(strip_tags($match[2]));

            $hierarchy[] = array(
                'level' => $level,
                'text' => $text,
                'is_question' => preg_match('/\?$/', $text) > 0
            );
        }

        return $hierarchy;
    }

    /**
     * Count internal links
     */
    private function count_internal_links($content) {
        $site_url = home_url();
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);

        $internal_count = 0;
        foreach ($matches[1] as $url) {
            if (strpos($url, $site_url) === 0 || strpos($url, '/') === 0) {
                $internal_count++;
            }
        }

        return $internal_count;
    }

    /**
     * Count external links
     */
    private function count_external_links($content) {
        $site_url = home_url();
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);

        $external_count = 0;
        foreach ($matches[1] as $url) {
            if (strpos($url, 'http') === 0 && strpos($url, $site_url) === false) {
                $external_count++;
            }
        }

        return $external_count;
    }

    /**
     * Count images with alt text
     */
    private function count_images_with_alt($content) {
        preg_match_all('/<img[^>]+>/i', $content, $images);
        $with_alt = 0;

        foreach ($images[0] as $img) {
            if (preg_match('/alt=["\']([^"\']*)["\']/', $img, $alt_match)) {
                if (!empty(trim($alt_match[1]))) {
                    $with_alt++;
                }
            }
        }

        return array(
            'total_images' => count($images[0]),
            'images_with_alt' => $with_alt,
            'alt_percentage' => count($images[0]) > 0 ? round(($with_alt / count($images[0])) * 100) : 100
        );
    }

    /**
     * Find statistics in content
     */
    private function find_statistics($content) {
        $content = strip_tags($content);
        $stats = array();

        // Pattern for percentages
        preg_match_all('/(\d+(?:\.\d+)?%)/i', $content, $percentages);
        foreach ($percentages[1] as $stat) {
            $stats[] = array('value' => $stat, 'type' => 'percentage');
        }

        // Pattern for numbers with units
        preg_match_all('/(\d{1,3}(?:,\d{3})*(?:\.\d+)?)\s*(million|billion|thousand|users|customers|people|companies|dollars?|years?|months?|days?|hours?|minutes?)/i', $content, $numbers);
        for ($i = 0; $i < count($numbers[1]); $i++) {
            $stats[] = array(
                'value' => $numbers[1][$i] . ' ' . $numbers[2][$i],
                'type' => 'numeric'
            );
        }

        // Pattern for ratios (X out of Y)
        preg_match_all('/(\d+)\s+(?:out of|in)\s+(\d+)/i', $content, $ratios);
        for ($i = 0; $i < count($ratios[1]); $i++) {
            $stats[] = array(
                'value' => $ratios[1][$i] . ' out of ' . $ratios[2][$i],
                'type' => 'ratio'
            );
        }

        return array_slice($stats, 0, 15); // Limit to 15 statistics
    }

    /**
     * Find content freshness indicators
     */
    private function find_freshness_indicators($content) {
        $content = strip_tags($content);
        $indicators = array();

        // Find dates
        $date_patterns = array(
            '/\b(20\d{2})\b/' => 'year',
            '/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s*20\d{2}\b/i' => 'full_date',
            '/\b\d{1,2}\/\d{1,2}\/20\d{2}\b/' => 'short_date'
        );

        foreach ($date_patterns as $pattern => $type) {
            preg_match_all($pattern, $content, $matches);
            foreach ($matches[0] as $match) {
                $indicators[] = array('value' => $match, 'type' => $type);
            }
        }

        // Find update indicators
        $update_patterns = array(
            '/\b(?:updated|revised|modified|changed)\s+(?:on|in)?\s*([^.]+)/i',
            '/\b(?:last updated|most recent|latest)\s*:?\s*([^.]+)/i'
        );

        foreach ($update_patterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            foreach ($matches[1] as $match) {
                $indicators[] = array('value' => trim($match), 'type' => 'update_reference');
            }
        }

        return array_slice($indicators, 0, 10);
    }

    /**
     * Assess Q&A potential of content
     */
    private function assess_qa_potential($content) {
        $content = strip_tags($content);
        $score = 0;

        // Question markers
        $question_words = array('how', 'what', 'why', 'when', 'where', 'who', 'which');
        foreach ($question_words as $word) {
            $count = preg_match_all('/\b' . $word . '\b/i', $content);
            $score += min($count * 2, 10); // Max 10 points per question word
        }

        // Instructional content indicators
        $instructional_patterns = array(
            '/\bstep\s+\d+/i',
            '/\bfirst,?\s+/i',
            '/\bsecond,?\s+/i',
            '/\bthen,?\s+/i',
            '/\bfinally,?\s+/i',
            '/\bnext,?\s+/i'
        );

        foreach ($instructional_patterns as $pattern) {
            $count = preg_match_all($pattern, $content);
            $score += $count * 3;
        }

        // List indicators
        $list_count = preg_match_all('/^\s*[\-\*\+]\s+/m', $content);
        $numbered_list_count = preg_match_all('/^\s*\d+\.\s+/m', $content);
        $score += ($list_count + $numbered_list_count) * 2;

        return min(100, $score);
    }

    /**
     * Analyze title for AEO optimization
     */
    private function analyze_title($title) {
        $analysis = array(
            'length' => strlen($title),
            'word_count' => str_word_count($title),
            'is_question' => preg_match('/\?$/', $title) > 0,
            'has_numbers' => preg_match('/\d/', $title) > 0,
            'has_power_words' => false,
            'optimization_score' => 0
        );

        // Check for power words
        $power_words = array('how', 'best', 'guide', 'complete', 'ultimate', 'top', 'easy', 'quick', 'proven', 'effective');
        foreach ($power_words as $word) {
            if (stripos($title, $word) !== false) {
                $analysis['has_power_words'] = true;
                break;
            }
        }

        // Calculate optimization score
        $score = 0;
        if ($analysis['length'] >= 30 && $analysis['length'] <= 60) $score += 20;
        if ($analysis['word_count'] >= 4 && $analysis['word_count'] <= 12) $score += 15;
        if ($analysis['is_question']) $score += 25;
        if ($analysis['has_numbers']) $score += 15;
        if ($analysis['has_power_words']) $score += 25;

        $analysis['optimization_score'] = $score;

        return $analysis;
    }

    /**
     * Calculate content depth score
     */
    private function calculate_content_depth($content) {
        $score = 0;
        $content_text = strip_tags($content);

        // Word count scoring
        $word_count = str_word_count($content_text);
        if ($word_count >= 300) $score += 10;
        if ($word_count >= 600) $score += 10;
        if ($word_count >= 1000) $score += 15;
        if ($word_count >= 1500) $score += 15;

        // Structure scoring
        $heading_count = preg_match_all('/<h[1-6][^>]*>/i', $content);
        $score += min($heading_count * 5, 25);

        // Media scoring
        $image_count = preg_match_all('/<img[^>]*>/i', $content);
        $score += min($image_count * 3, 15);

        // List scoring
        $list_count = preg_match_all('/<[ou]l[^>]*>/i', $content);
        $score += min($list_count * 5, 15);

        // Link scoring
        $link_count = preg_match_all('/<a[^>]*>/i', $content);
        $score += min($link_count * 2, 10);

        return min(100, $score);
    }

    /**
     * Calculate AI readiness score
     */
    private function calculate_ai_readiness_score($analysis) {
        $score = 0;

        // Content length (20 points)
        if ($analysis['word_count'] >= 300) $score += 10;
        if ($analysis['word_count'] >= 600) $score += 10;

        // Structure (25 points)
        if ($analysis['has_clear_structure']) $score += 15;
        if ($analysis['question_headings'] > 0) $score += 10;

        // Readability (15 points)
        if ($analysis['readability_score'] >= 60) $score += 15;

        // Q&A potential (20 points)
        $score += ($analysis['qa_potential'] / 100) * 20;

        // Title optimization (10 points)
        $score += ($analysis['title_optimization']['optimization_score'] / 100) * 10;

        // Statistics presence (10 points)
        if (count($analysis['statistics_found']) > 0) $score += 10;

        return min(100, round($score));
    }
}