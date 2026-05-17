<?php
/**
 * RequestDesk Schema Generator
 *
 * Generates structured data markup for AEO optimization
 */

class RequestDesk_Schema_Generator {

    private $claude_integration;

    public function __construct() {
        $this->claude_integration = new RequestDesk_Claude_Integration();
    }

    /**
     * Generate FAQ schema markup
     */
    public function generate_faq_schema($post, $qa_pairs = array()) {
        if (empty($qa_pairs)) {
            return array();
        }

        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array()
        );

        foreach ($qa_pairs as $qa) {
            // Only include high-confidence Q&A pairs in schema
            if ($qa['confidence'] >= 0.7) {
                $schema['mainEntity'][] = array(
                    '@type' => 'Question',
                    'name' => $qa['question'],
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => $qa['answer']
                    )
                );
            }
        }

        // Only return schema if we have at least 2 questions
        if (count($schema['mainEntity']) >= 2) {
            return $schema;
        }

        return array();
    }

    /**
     * Generate Article schema markup
     */
    public function generate_article_schema($post, $aeo_data = array()) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->post_title,
            'description' => $this->get_post_description($post),
            'url' => get_permalink($post->ID),
            'datePublished' => get_the_date('c', $post->ID),
            'dateModified' => get_the_modified_date('c', $post->ID),
            'author' => $this->get_author_schema($post),
            'publisher' => $this->get_publisher_schema(),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id' => get_permalink($post->ID)
            )
        );

        // Add featured image if available
        $featured_image = get_the_post_thumbnail_url($post->ID, 'full');
        if ($featured_image) {
            $schema['image'] = array(
                '@type' => 'ImageObject',
                'url' => $featured_image,
                'width' => 1200,
                'height' => 630
            );
        }

        // Add word count if available
        if (!empty($aeo_data['word_count'])) {
            $schema['wordCount'] = $aeo_data['word_count'];
        }

        // Add reading time estimate
        $word_count = str_word_count(strip_tags($post->post_content));
        $reading_time = max(1, round($word_count / 200)); // Assume 200 words per minute
        $schema['timeRequired'] = 'PT' . $reading_time . 'M';

        return $schema;
    }

    /**
     * Generate HowTo schema markup
     */
    public function generate_howto_schema($post, $steps = array()) {
        if (empty($steps)) {
            $steps = $this->extract_howto_steps($post->post_content);
        }

        if (empty($steps)) {
            return array();
        }

        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => $post->post_title,
            'description' => $this->get_post_description($post),
            'image' => $this->get_post_images($post),
            'totalTime' => $this->estimate_completion_time($steps),
            'supply' => array(),
            'tool' => array(),
            'step' => array()
        );

        foreach ($steps as $index => $step) {
            $schema['step'][] = array(
                '@type' => 'HowToStep',
                'position' => $index + 1,
                'name' => $step['name'],
                'text' => $step['text'],
                'url' => get_permalink($post->ID) . '#step-' . ($index + 1)
            );
        }

        return $schema;
    }

    /**
     * Generate QAPage schema markup
     */
    public function generate_qanda_schema($post, $qa_pairs = array()) {
        if (empty($qa_pairs)) {
            return array();
        }

        // For single Q&A, use QAPage instead of FAQPage
        if (count($qa_pairs) === 1) {
            $qa = $qa_pairs[0];

            return array(
                '@context' => 'https://schema.org',
                '@type' => 'QAPage',
                'mainEntity' => array(
                    '@type' => 'Question',
                    'name' => $qa['question'],
                    'text' => $qa['question'],
                    'answerCount' => 1,
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => $qa['answer'],
                        'dateCreated' => get_the_date('c', $post->ID),
                        'upvoteCount' => 0,
                        'url' => get_permalink($post->ID)
                    )
                )
            );
        }

        return array();
    }

    /**
     * Generate BreadcrumbList schema markup
     * Always recommended for AI/LLM navigation understanding
     *
     * @param WP_Post $post The post object
     * @return array Breadcrumb schema
     */
    public function generate_breadcrumb_schema($post) {
        $breadcrumbs = array();
        $position = 1;

        // Home
        $breadcrumbs[] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => 'Home',
            'item' => home_url()
        );

        // For pages with parent hierarchy
        if ($post->post_type === 'page' && $post->post_parent > 0) {
            $ancestors = get_post_ancestors($post->ID);
            $ancestors = array_reverse($ancestors);

            foreach ($ancestors as $ancestor_id) {
                $ancestor = get_post($ancestor_id);
                if ($ancestor) {
                    $breadcrumbs[] = array(
                        '@type' => 'ListItem',
                        'position' => $position++,
                        'name' => $ancestor->post_title,
                        'item' => get_permalink($ancestor_id)
                    );
                }
            }
        }

        // For posts - add category
        if ($post->post_type === 'post') {
            $categories = get_the_category($post->ID);
            if (!empty($categories)) {
                // Use primary category (first one)
                $primary_cat = $categories[0];

                // Add parent categories first
                $cat_ancestors = get_ancestors($primary_cat->term_id, 'category');
                $cat_ancestors = array_reverse($cat_ancestors);

                foreach ($cat_ancestors as $cat_id) {
                    $cat = get_category($cat_id);
                    if ($cat) {
                        $breadcrumbs[] = array(
                            '@type' => 'ListItem',
                            'position' => $position++,
                            'name' => $cat->name,
                            'item' => get_category_link($cat_id)
                        );
                    }
                }

                // Add primary category
                $breadcrumbs[] = array(
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $primary_cat->name,
                    'item' => get_category_link($primary_cat->term_id)
                );
            }
        }

        // For custom post types - add archive
        if (!in_array($post->post_type, array('post', 'page'))) {
            $post_type_obj = get_post_type_object($post->post_type);
            if ($post_type_obj && $post_type_obj->has_archive) {
                $breadcrumbs[] = array(
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $post_type_obj->labels->name,
                    'item' => get_post_type_archive_link($post->post_type)
                );
            }
        }

        // Current page (without item URL per Google spec for last item)
        $breadcrumbs[] = array(
            '@type' => 'ListItem',
            'position' => $position,
            'name' => $post->post_title
        );

        return array(
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumbs
        );
    }

    /**
     * Generate VideoObject schema markup
     * Optimized for AI/LLM video content understanding
     *
     * @param WP_Post $post The post object
     * @param array $video_data Optional video data from detection
     * @return array|array[] Video schema (single or multiple)
     */
    public function generate_video_schema($post, $video_data = array()) {
        // If no video data provided, try to extract from content
        if (empty($video_data)) {
            $video_data = $this->extract_video_data($post->post_content);
        }

        if (empty($video_data)) {
            return array();
        }

        $schemas = array();

        // Handle YouTube videos
        if (!empty($video_data['youtube_ids'])) {
            foreach ($video_data['youtube_ids'] as $video_id) {
                $schemas[] = $this->create_video_schema_object(
                    $post,
                    array(
                        'embedUrl' => 'https://www.youtube.com/embed/' . $video_id,
                        'contentUrl' => 'https://www.youtube.com/watch?v=' . $video_id,
                        'thumbnailUrl' => 'https://img.youtube.com/vi/' . $video_id . '/maxresdefault.jpg',
                        'platform' => 'youtube'
                    )
                );
            }
        }

        // Handle Vimeo videos
        if (!empty($video_data['vimeo_ids'])) {
            foreach ($video_data['vimeo_ids'] as $video_id) {
                $schemas[] = $this->create_video_schema_object(
                    $post,
                    array(
                        'embedUrl' => 'https://player.vimeo.com/video/' . $video_id,
                        'contentUrl' => 'https://vimeo.com/' . $video_id,
                        'platform' => 'vimeo'
                    )
                );
            }
        }

        // Handle HTML5 video URLs
        if (!empty($video_data['video_urls'])) {
            foreach ($video_data['video_urls'] as $video_url) {
                $schemas[] = $this->create_video_schema_object(
                    $post,
                    array(
                        'contentUrl' => $video_url,
                        'embedUrl' => $video_url,
                        'platform' => 'html5'
                    )
                );
            }
        }

        // Return single schema or array
        if (count($schemas) === 1) {
            return $schemas[0];
        }

        return $schemas;
    }

    /**
     * Create a single video schema object
     *
     * @param WP_Post $post The post object
     * @param array $video Video data
     * @return array Video schema object
     */
    private function create_video_schema_object($post, $video) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => $video['title'] ?? $post->post_title,
            'description' => $video['description'] ?? $this->get_post_description($post),
            'uploadDate' => get_the_date('c', $post->ID)
        );

        // Add URLs
        if (!empty($video['contentUrl'])) {
            $schema['contentUrl'] = $video['contentUrl'];
        }
        if (!empty($video['embedUrl'])) {
            $schema['embedUrl'] = $video['embedUrl'];
        }

        // Add thumbnail
        if (!empty($video['thumbnailUrl'])) {
            $schema['thumbnailUrl'] = $video['thumbnailUrl'];
        } else {
            // Fallback to featured image
            $featured = get_the_post_thumbnail_url($post->ID, 'full');
            if ($featured) {
                $schema['thumbnailUrl'] = $featured;
            }
        }

        // Add duration if available (ISO 8601 format)
        if (!empty($video['duration'])) {
            $schema['duration'] = $this->format_video_duration($video['duration']);
        }

        // Add interaction statistics if available
        if (!empty($video['views'])) {
            $schema['interactionStatistic'] = array(
                '@type' => 'InteractionCounter',
                'interactionType' => array('@type' => 'WatchAction'),
                'userInteractionCount' => $video['views']
            );
        }

        // Add publisher (required by Google)
        $schema['publisher'] = $this->get_publisher_schema();

        return $schema;
    }

    /**
     * Extract video data from content
     *
     * @param string $content Post content
     * @return array Extracted video data
     */
    private function extract_video_data($content) {
        $data = array();

        // YouTube
        if (preg_match_all('/(?:youtube\.com\/(?:embed\/|watch\?v=|v\/)|youtu\.be\/)([\w-]{11})/i', $content, $matches)) {
            $data['youtube_ids'] = array_unique($matches[1]);
        }

        // Vimeo
        if (preg_match_all('/vimeo\.com\/(?:video\/)?(\d+)/i', $content, $matches)) {
            $data['vimeo_ids'] = array_unique($matches[1]);
        }

        // HTML5 video
        if (preg_match_all('/<video[^>]*src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            $data['video_urls'] = $matches[1];
        }

        return $data;
    }

    /**
     * Format duration to ISO 8601
     *
     * @param int $seconds Duration in seconds
     * @return string ISO 8601 duration
     */
    private function format_video_duration($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $duration = 'PT';
        if ($hours > 0) $duration .= $hours . 'H';
        if ($minutes > 0) $duration .= $minutes . 'M';
        if ($secs > 0) $duration .= $secs . 'S';

        return $duration;
    }

    /**
     * Generate Product schema markup
     * Optimized for AI/LLM visibility
     *
     * @param WP_Post $post The post object
     * @param array $product_data Optional product data
     * @return array Product schema
     */
    public function generate_product_schema($post, $product_data = array()) {
        // Try to get WooCommerce data if available
        $wc_data = $this->get_woocommerce_data($post->ID);
        $data = array_merge($wc_data, $product_data);

        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $data['name'] ?? $post->post_title,
            'description' => $data['description'] ?? $this->get_post_description($post),
            'url' => get_permalink($post->ID)
        );

        // Add SKU/product ID
        if (!empty($data['sku'])) {
            $schema['sku'] = $data['sku'];
            $schema['productID'] = $data['sku'];
        }

        // Add image
        $image_url = $data['image'] ?? get_the_post_thumbnail_url($post->ID, 'full');
        if ($image_url) {
            $schema['image'] = array(
                '@type' => 'ImageObject',
                'url' => $image_url
            );
        }

        // Add brand if available
        if (!empty($data['brand'])) {
            $schema['brand'] = array(
                '@type' => 'Brand',
                'name' => $data['brand']
            );
        }

        // Add offers (pricing)
        if (!empty($data['price']) || !empty($data['regular_price'])) {
            $price = $data['price'] ?? $data['regular_price'];
            $schema['offers'] = array(
                '@type' => 'Offer',
                'price' => $price,
                'priceCurrency' => $data['currency'] ?? 'USD',
                'availability' => $this->get_availability_url($data['availability'] ?? 'InStock'),
                'url' => get_permalink($post->ID),
                // AI-optimized: Include price valid date for freshness
                'priceValidUntil' => date('Y-m-d', strtotime('+30 days'))
            );

            // Add sale price if different
            if (!empty($data['sale_price']) && $data['sale_price'] !== $price) {
                $schema['offers']['priceSpecification'] = array(
                    '@type' => 'PriceSpecification',
                    'price' => $data['sale_price'],
                    'priceCurrency' => $data['currency'] ?? 'USD'
                );
            }
        }

        // Add aggregate rating if reviews exist
        if (!empty($data['rating']) && !empty($data['review_count'])) {
            $schema['aggregateRating'] = array(
                '@type' => 'AggregateRating',
                'ratingValue' => $data['rating'],
                'reviewCount' => $data['review_count'],
                'bestRating' => '5',
                'worstRating' => '1'
            );
        }

        // Add individual reviews if available
        if (!empty($data['reviews']) && is_array($data['reviews'])) {
            $schema['review'] = array();
            foreach (array_slice($data['reviews'], 0, 5) as $review) {
                $schema['review'][] = array(
                    '@type' => 'Review',
                    'reviewRating' => array(
                        '@type' => 'Rating',
                        'ratingValue' => $review['rating'] ?? 5,
                        'bestRating' => '5'
                    ),
                    'author' => array(
                        '@type' => 'Person',
                        'name' => $review['author'] ?? 'Anonymous'
                    ),
                    'reviewBody' => $review['content'] ?? '',
                    'datePublished' => $review['date'] ?? get_the_date('c', $post->ID)
                );
            }
        }

        return $schema;
    }

    /**
     * Get WooCommerce product data
     *
     * @param int $post_id Post ID
     * @return array Product data
     */
    private function get_woocommerce_data($post_id) {
        if (!function_exists('wc_get_product')) {
            return array();
        }

        $product = wc_get_product($post_id);
        if (!$product) {
            return array();
        }

        $data = array(
            'name' => $product->get_name(),
            'description' => $product->get_short_description() ?: $product->get_description(),
            'sku' => $product->get_sku(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
            'availability' => $product->is_in_stock() ? 'InStock' : 'OutOfStock',
            'image' => wp_get_attachment_url($product->get_image_id())
        );

        // Get rating data
        if ($product->get_review_count() > 0) {
            $data['rating'] = $product->get_average_rating();
            $data['review_count'] = $product->get_review_count();
        }

        return $data;
    }

    /**
     * Get schema.org availability URL
     *
     * @param string $status Availability status
     * @return string Schema.org URL
     */
    private function get_availability_url($status) {
        $statuses = array(
            'InStock' => 'https://schema.org/InStock',
            'OutOfStock' => 'https://schema.org/OutOfStock',
            'PreOrder' => 'https://schema.org/PreOrder',
            'BackOrder' => 'https://schema.org/BackOrder',
            'Discontinued' => 'https://schema.org/Discontinued',
            'LimitedAvailability' => 'https://schema.org/LimitedAvailability',
            'SoldOut' => 'https://schema.org/SoldOut'
        );

        return $statuses[$status] ?? 'https://schema.org/InStock';
    }

    /**
     * Generate Course schema markup
     * Optimized for AI/LLM educational content understanding
     *
     * @param WP_Post $post The post object
     * @param array $course_data Optional course data
     * @return array Course schema
     */
    public function generate_course_schema($post, $course_data = array()) {
        // Try to get LMS data if available
        $lms_data = $this->get_lms_course_data($post->ID);
        $data = array_merge($lms_data, $course_data);

        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Course',
            'name' => $data['name'] ?? $post->post_title,
            'description' => $data['description'] ?? $this->get_post_description($post),
            'url' => get_permalink($post->ID),
            'provider' => array(
                '@type' => 'Organization',
                'name' => $data['provider'] ?? get_bloginfo('name'),
                'url' => home_url()
            )
        );

        // Add course image
        $image = $data['image'] ?? get_the_post_thumbnail_url($post->ID, 'full');
        if ($image) {
            $schema['image'] = $image;
        }

        // Course mode (online, onsite, blended)
        $schema['courseMode'] = $data['courseMode'] ?? 'online';

        // Add instructor if available
        if (!empty($data['instructor'])) {
            $schema['instructor'] = array(
                '@type' => 'Person',
                'name' => is_array($data['instructor']) ? $data['instructor']['name'] : $data['instructor']
            );

            if (is_array($data['instructor']) && !empty($data['instructor']['url'])) {
                $schema['instructor']['url'] = $data['instructor']['url'];
            }
        }

        // Add course duration
        if (!empty($data['duration'])) {
            $schema['timeRequired'] = $data['duration'];
        }

        // AI-optimized: Learning outcomes
        if (!empty($data['learningOutcomes'])) {
            $schema['teaches'] = $data['learningOutcomes'];
        }

        // Add prerequisites
        if (!empty($data['prerequisites'])) {
            $schema['coursePrerequisites'] = $data['prerequisites'];
        }

        // Add educational level
        if (!empty($data['level'])) {
            $schema['educationalLevel'] = $data['level'];
        }

        // Add language
        $schema['inLanguage'] = $data['language'] ?? get_bloginfo('language');

        // Add course instance (specific offering)
        if (!empty($data['startDate']) || !empty($data['endDate'])) {
            $schema['hasCourseInstance'] = array(
                '@type' => 'CourseInstance',
                'courseMode' => $schema['courseMode']
            );

            if (!empty($data['startDate'])) {
                $schema['hasCourseInstance']['startDate'] = $data['startDate'];
            }
            if (!empty($data['endDate'])) {
                $schema['hasCourseInstance']['endDate'] = $data['endDate'];
            }
        }

        // Add offers (pricing)
        if (!empty($data['price'])) {
            $schema['offers'] = array(
                '@type' => 'Offer',
                'price' => $data['price'],
                'priceCurrency' => $data['currency'] ?? 'USD',
                'availability' => 'https://schema.org/InStock',
                'url' => get_permalink($post->ID)
            );
        }

        // Add aggregate rating
        if (!empty($data['rating'])) {
            $rating_data = is_array($data['rating']) ? $data['rating'] : array('value' => $data['rating']);
            $schema['aggregateRating'] = array(
                '@type' => 'AggregateRating',
                'ratingValue' => $rating_data['value'] ?? $rating_data,
                'reviewCount' => $rating_data['count'] ?? 1,
                'bestRating' => '5'
            );
        }

        return $schema;
    }

    /**
     * Get LMS course data (LearnDash, LifterLMS, etc.)
     *
     * @param int $post_id Post ID
     * @return array Course data
     */
    private function get_lms_course_data($post_id) {
        $data = array();

        // LearnDash integration
        if (function_exists('learndash_get_course_meta_setting')) {
            $price = learndash_get_course_meta_setting($post_id, 'course_price');
            if ($price) {
                $data['price'] = $price;
            }

            $length = learndash_get_course_meta_setting($post_id, 'course_length');
            if ($length) {
                $data['duration'] = $length;
            }
        }

        // LifterLMS integration
        if (function_exists('llms_get_post')) {
            $course = llms_get_post($post_id);
            if ($course && method_exists($course, 'get_price')) {
                $price = $course->get_price();
                if ($price) {
                    $data['price'] = $price;
                }
            }
        }

        // Tutor LMS integration
        if (function_exists('tutor_utils')) {
            $tutor = tutor_utils();
            if (method_exists($tutor, 'get_course_settings')) {
                $settings = $tutor->get_course_settings($post_id);
                if (!empty($settings['course_price'])) {
                    $data['price'] = $settings['course_price'];
                }
            }
        }

        return $data;
    }

    /**
     * Generate LocalBusiness schema markup
     * Optimized for AI/LLM visibility and local search
     *
     * @param WP_Post $post The post object
     * @param array $business_data Optional business data
     * @return array LocalBusiness schema
     */
    public function generate_local_business_schema($post, $business_data = array()) {
        // Get business type from detection or default
        $business_type = $business_data['type'] ?? 'LocalBusiness';

        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => $business_type,
            'name' => $business_data['name'] ?? get_bloginfo('name'),
            'description' => $business_data['description'] ?? get_bloginfo('description'),
            'url' => $business_data['url'] ?? home_url(),
            '@id' => home_url() . '#LocalBusiness'
        );

        // Add logo/image
        $logo_id = get_theme_mod('custom_logo');
        if ($logo_id) {
            $logo_url = wp_get_attachment_image_url($logo_id, 'full');
            if ($logo_url) {
                $schema['logo'] = $logo_url;
                $schema['image'] = $logo_url;
            }
        }

        // Add address
        if (!empty($business_data['address'])) {
            $address = $business_data['address'];
            $schema['address'] = array(
                '@type' => 'PostalAddress'
            );

            if (!empty($address['street'])) {
                $schema['address']['streetAddress'] = $address['street'];
            }
            if (!empty($address['city'])) {
                $schema['address']['addressLocality'] = $address['city'];
            }
            if (!empty($address['state'])) {
                $schema['address']['addressRegion'] = $address['state'];
            }
            if (!empty($address['zip'])) {
                $schema['address']['postalCode'] = $address['zip'];
            }
            $schema['address']['addressCountry'] = $address['country'] ?? 'US';
        }

        // Add geo coordinates if available
        if (!empty($business_data['geo'])) {
            $schema['geo'] = array(
                '@type' => 'GeoCoordinates',
                'latitude' => $business_data['geo']['lat'],
                'longitude' => $business_data['geo']['lng']
            );
        }

        // Add contact information
        if (!empty($business_data['phone'])) {
            $schema['telephone'] = $business_data['phone'];
        }

        if (!empty($business_data['email'])) {
            $schema['email'] = $business_data['email'];
        }

        // Add opening hours
        if (!empty($business_data['hours']) && is_array($business_data['hours'])) {
            $schema['openingHoursSpecification'] = array();
            foreach ($business_data['hours'] as $day => $hours) {
                if (!empty($hours['open']) && !empty($hours['close'])) {
                    $schema['openingHoursSpecification'][] = array(
                        '@type' => 'OpeningHoursSpecification',
                        'dayOfWeek' => $day,
                        'opens' => $hours['open'],
                        'closes' => $hours['close']
                    );
                }
            }
        }

        // Add price range if available
        if (!empty($business_data['priceRange'])) {
            $schema['priceRange'] = $business_data['priceRange'];
        }

        // Add aggregate rating
        if (!empty($business_data['rating'])) {
            $rating_data = is_array($business_data['rating']) ? $business_data['rating'] : array('value' => $business_data['rating']);
            $schema['aggregateRating'] = array(
                '@type' => 'AggregateRating',
                'ratingValue' => $rating_data['value'] ?? $rating_data,
                'reviewCount' => $rating_data['count'] ?? 1,
                'bestRating' => '5'
            );
        }

        // Add service area
        if (!empty($business_data['serviceArea'])) {
            $schema['areaServed'] = array(
                '@type' => 'City',
                'name' => $business_data['serviceArea']
            );
        }

        // Add social profiles
        $social_profiles = $this->get_social_profiles();
        if (!empty($social_profiles)) {
            $schema['sameAs'] = $social_profiles;
        }

        return $schema;
    }

    /**
     * Generate Organization schema markup
     */
    public function generate_organization_schema() {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => get_bloginfo('name'),
            'url' => home_url(),
            'description' => get_bloginfo('description')
        );

        // Add logo if available
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            if ($logo_url) {
                $schema['logo'] = array(
                    '@type' => 'ImageObject',
                    'url' => $logo_url
                );
            }
        }

        // Add social media profiles
        $social_profiles = $this->get_social_profiles();
        if (!empty($social_profiles)) {
            $schema['sameAs'] = $social_profiles;
        }

        return $schema;
    }

    /**
     * Generate WebSite schema markup with search action
     */
    public function generate_website_schema() {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'url' => home_url(),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name')
            )
        );

        // Add search action if search is available
        $search_url = home_url('/?s={search_term_string}');
        $schema['potentialAction'] = array(
            '@type' => 'SearchAction',
            'target' => array(
                '@type' => 'EntryPoint',
                'urlTemplate' => $search_url
            ),
            'query-input' => 'required name=search_term_string'
        );

        return $schema;
    }

    /**
     * Generate ProfessionalService + OfferCatalog schema for the home page.
     *
     * Gives AI engines an explicit machine-readable entity for what the
     * site sells (the category handle the SEO/AI audit flagged as missing,
     * CC-FULL-02). Service list and description track the site's own
     * llms.txt declared positioning.
     */
    public function generate_professional_service_schema() {
        $home = trailingslashit(home_url());
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'ProfessionalService',
            'name' => get_bloginfo('name'),
            'url' => $home,
            'description' => get_bloginfo('description'),
            'knowsAbout' => array(
                'Ecommerce content marketing',
                'Answer Engine Optimization',
                'Generative Engine Optimization',
                'SEO',
                'HubSpot implementation',
                'Revenue attribution'
            ),
            'hasOfferCatalog' => array(
                '@type' => 'OfferCatalog',
                'name' => 'Services',
                'itemListElement' => array(
                    array('@type' => 'Offer', 'itemOffered' => array('@type' => 'Service', 'name' => 'Growth Marketing', 'url' => $home . 'growth-marketing')),
                    array('@type' => 'Offer', 'itemOffered' => array('@type' => 'Service', 'name' => 'SEO / AEO / AIO')),
                    array('@type' => 'Offer', 'itemOffered' => array('@type' => 'Service', 'name' => 'HubSpot Implementation', 'url' => $home . 'hubspot-audit')),
                    array('@type' => 'Offer', 'itemOffered' => array('@type' => 'Service', 'name' => 'Loop Marketing', 'url' => $home . 'hubspot-loop-marketing')),
                    array('@type' => 'Offer', 'itemOffered' => array('@type' => 'Service', 'name' => 'Live Event Content', 'url' => $home . 'conference-coverage'))
                )
            )
        );

        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            if ($logo_url) {
                $schema['logo'] = array('@type' => 'ImageObject', 'url' => $logo_url);
            }
        }

        $social_profiles = $this->get_social_profiles();
        if (!empty($social_profiles)) {
            $schema['sameAs'] = $social_profiles;
        }

        return $schema;
    }

    /**
     * Extract HowTo steps from content
     */
    private function extract_howto_steps($content) {
        $steps = array();
        $content = strip_tags($content);

        // Pattern 1: Numbered steps
        $numbered_pattern = '/(?:^|\n)\s*(\d+)\.\s*([^\n]+)(?:\n(.+?))?(?=\n\s*\d+\.|\Z)/m';
        preg_match_all($numbered_pattern, $content, $numbered_matches, PREG_SET_ORDER);

        foreach ($numbered_matches as $match) {
            $steps[] = array(
                'name' => 'Step ' . $match[1],
                'text' => trim($match[2] . ' ' . ($match[3] ?? '')),
                'type' => 'numbered'
            );
        }

        // Pattern 2: Step headings
        $step_pattern = '/(?:^|\n)\s*(?:step\s+\d+:?\s*)?([^\n]+?)(?:\n(.+?))?(?=\n\s*step\s+\d+|\Z)/mi';
        if (stripos($content, 'step') !== false && count($steps) < 3) {
            preg_match_all($step_pattern, $content, $step_matches, PREG_SET_ORDER);

            foreach ($step_matches as $index => $match) {
                if (stripos($match[1], 'step') !== false) {
                    $steps[] = array(
                        'name' => trim($match[1]),
                        'text' => trim($match[2] ?? $match[1]),
                        'type' => 'heading'
                    );
                }
            }
        }

        // Filter out very short or very long steps
        $steps = array_filter($steps, function($step) {
            $text_length = strlen($step['text']);
            return $text_length >= 10 && $text_length <= 500;
        });

        return array_slice($steps, 0, 20); // Limit to 20 steps
    }

    /**
     * Get post description
     */
    private function get_post_description($post) {
        // Try excerpt first
        if (!empty($post->post_excerpt)) {
            return $post->post_excerpt;
        }

        // Generate from content
        $content = strip_tags($post->post_content);
        $description = wp_trim_words($content, 25);

        return $description;
    }

    /**
     * Get author schema
     */
    private function get_author_schema($post) {
        $author_id = $post->post_author;
        $author = get_userdata($author_id);

        $author_schema = array(
            '@type' => 'Person',
            'name' => $author->display_name,
            'url' => get_author_posts_url($author_id)
        );

        // Add author description if available
        $author_description = get_user_meta($author_id, 'description', true);
        if (!empty($author_description)) {
            $author_schema['description'] = $author_description;
        }

        // Add author avatar
        $avatar_url = get_avatar_url($author_id, array('size' => 96));
        if ($avatar_url) {
            $author_schema['image'] = array(
                '@type' => 'ImageObject',
                'url' => $avatar_url,
                'width' => 96,
                'height' => 96
            );
        }

        return $author_schema;
    }

    /**
     * Get publisher schema
     */
    private function get_publisher_schema() {
        $publisher = array(
            '@type' => 'Organization',
            'name' => get_bloginfo('name'),
            'url' => home_url()
        );

        // Add logo if available
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            if ($logo_url) {
                $logo_data = wp_get_attachment_metadata($custom_logo_id);
                $publisher['logo'] = array(
                    '@type' => 'ImageObject',
                    'url' => $logo_url,
                    'width' => $logo_data['width'] ?? 600,
                    'height' => $logo_data['height'] ?? 60
                );
            }
        }

        return $publisher;
    }

    /**
     * Get post images
     */
    private function get_post_images($post) {
        $images = array();

        // Featured image
        $featured_image = get_the_post_thumbnail_url($post->ID, 'full');
        if ($featured_image) {
            $images[] = array(
                '@type' => 'ImageObject',
                'url' => $featured_image
            );
        }

        // Images from content
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $content_images);
        foreach ($content_images[1] as $image_url) {
            if (filter_var($image_url, FILTER_VALIDATE_URL)) {
                $images[] = array(
                    '@type' => 'ImageObject',
                    'url' => $image_url
                );
            }
        }

        return array_slice($images, 0, 5); // Limit to 5 images
    }

    /**
     * Estimate completion time for HowTo
     */
    private function estimate_completion_time($steps) {
        $base_time = 5; // Base 5 minutes
        $step_time = count($steps) * 2; // 2 minutes per step

        $total_minutes = $base_time + $step_time;

        // Convert to ISO 8601 duration format
        if ($total_minutes < 60) {
            return 'PT' . $total_minutes . 'M';
        } else {
            $hours = floor($total_minutes / 60);
            $minutes = $total_minutes % 60;
            return 'PT' . $hours . 'H' . $minutes . 'M';
        }
    }

    /**
     * Get social media profiles
     */
    private function get_social_profiles() {
        $profiles = array();

        // Common social media meta keys (from themes/plugins)
        $social_keys = array(
            'facebook_url' => 'facebook',
            'twitter_url' => 'twitter',
            'linkedin_url' => 'linkedin',
            'instagram_url' => 'instagram',
            'youtube_url' => 'youtube'
        );

        foreach ($social_keys as $key => $platform) {
            $url = get_option($key) ?: get_theme_mod($key);
            if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                $profiles[] = $url;
            }
        }

        return $profiles;
    }

    /**
     * Generate comprehensive schema for a post
     * Enhanced with automatic content detection and AI-first optimization
     *
     * @param WP_Post $post The post object
     * @param array $aeo_data AEO analysis data
     * @return array Array of schema objects
     */
    public function generate_comprehensive_schema($post, $aeo_data = array()) {
        $schemas = array();
        $settings = get_option('requestdesk_aeo_settings', array());

        // Initialize content detector
        $detector = null;
        $detected_types = array();
        if (class_exists('RequestDesk_Content_Detector')) {
            $detector = new RequestDesk_Content_Detector();
            $detected_types = $detector->detect_schema_types($post);
        }

        // Get confidence threshold from settings (default 0.6 = medium)
        $confidence_threshold = floatval($settings['schema_detection_confidence'] ?? 0.6);

        // Get Claude AI schema suggestions if available
        $claude_suggestions = array();
        if ($this->claude_integration->is_available()) {
            $claude_suggestions = $this->claude_integration->generate_schema_suggestions(
                $post->post_title,
                strip_tags($post->post_content),
                $post->post_type
            );

            if (!is_wp_error($claude_suggestions)) {
                // Store Claude suggestions in AEO data for future reference
                $aeo_data['claude_schema_suggestions'] = $claude_suggestions;
            } else {
                $claude_suggestions = array();
            }
        }

        // 1. ARTICLE SCHEMA (default for all content)
        if ($settings['schema_article'] ?? true) {
            $schemas[] = $this->generate_article_schema($post, $aeo_data);
        }

        // 2. BREADCRUMB SCHEMA (always recommended for AI navigation)
        if ($settings['schema_breadcrumb'] ?? true) {
            $schemas[] = $this->generate_breadcrumb_schema($post);
        }

        // 3. FAQ SCHEMA
        if (($settings['schema_faq'] ?? true) &&
            !empty($aeo_data['ai_questions']) &&
            count($aeo_data['ai_questions']) >= 2) {

            $faq_schema = $this->generate_faq_schema($post, $aeo_data['ai_questions']);
            if (!empty($faq_schema) && !empty($faq_schema['mainEntity'])) {
                $schemas[] = $faq_schema;
            }
        }

        // 4. HOWTO SCHEMA
        if ($settings['schema_howto'] ?? true) {
            $is_howto_content = (stripos($post->post_title, 'how to') !== false ||
                                stripos($post->post_content, 'step') !== false);

            // Check Claude's suggestions for HowTo recommendation
            if (!empty($claude_suggestions['additional_schemas']) &&
                is_array($claude_suggestions['additional_schemas']) &&
                in_array('HowTo', $claude_suggestions['additional_schemas'])) {
                $is_howto_content = true;
            }

            if ($is_howto_content) {
                $howto_schema = $this->generate_howto_schema($post);
                if (!empty($howto_schema) && !empty($howto_schema['step'])) {
                    $schemas[] = $howto_schema;
                }
            }
        }

        // 5. PRODUCT SCHEMA (auto-detected)
        if (($settings['schema_product'] ?? true) &&
            !empty($detected_types['product']) &&
            $detected_types['product']['detected'] &&
            $detected_types['product']['confidence'] >= $confidence_threshold) {

            $product_data = $claude_suggestions['extracted_data']['product'] ?? array();
            $product_schema = $this->generate_product_schema($post, $product_data);
            if (!empty($product_schema)) {
                $schemas[] = $product_schema;
            }
        }

        // 6. LOCAL BUSINESS SCHEMA (auto-detected)
        if (($settings['schema_local_business'] ?? true) &&
            !empty($detected_types['local_business']) &&
            $detected_types['local_business']['detected'] &&
            $detected_types['local_business']['confidence'] >= $confidence_threshold) {

            $business_data = get_option('requestdesk_local_business_settings', array());
            $business_data = array_merge($business_data,
                $claude_suggestions['extracted_data']['local_business'] ?? array());

            // Get business type from detection
            if ($detector && !empty($detected_types['local_business']['signals'])) {
                $business_data['type'] = $detector->get_local_business_type(
                    $detected_types['local_business']['signals']
                );
            }

            $local_schema = $this->generate_local_business_schema($post, $business_data);
            if (!empty($local_schema)) {
                $schemas[] = $local_schema;
            }
        }

        // 7. VIDEO SCHEMA (auto-detected)
        if (($settings['schema_video'] ?? true) &&
            !empty($detected_types['video']) &&
            $detected_types['video']['detected']) {

            $video_data = $detected_types['video']['video_data'] ?? array();
            $video_schema = $this->generate_video_schema($post, $video_data);
            if (!empty($video_schema)) {
                // Handle multiple videos
                if (isset($video_schema[0])) {
                    foreach ($video_schema as $vs) {
                        $schemas[] = $vs;
                    }
                } else {
                    $schemas[] = $video_schema;
                }
            }
        }

        // 8. COURSE SCHEMA (auto-detected)
        if (($settings['schema_course'] ?? true) &&
            !empty($detected_types['course']) &&
            $detected_types['course']['detected'] &&
            $detected_types['course']['confidence'] >= $confidence_threshold) {

            $course_data = $claude_suggestions['extracted_data']['course'] ?? array();
            $course_schema = $this->generate_course_schema($post, $course_data);
            if (!empty($course_schema)) {
                $schemas[] = $course_schema;
            }
        }

        // 9. QAPAGE SCHEMA (for single Q&A)
        if (!empty($aeo_data['ai_questions']) && count($aeo_data['ai_questions']) === 1) {
            $qa_schema = $this->generate_qanda_schema($post, $aeo_data['ai_questions']);
            if (!empty($qa_schema)) {
                $schemas[] = $qa_schema;
            }
        }

        // Store detected schema types for analytics
        $detected_schema_names = array();
        foreach ($detected_types as $type => $data) {
            if (!empty($data['detected'])) {
                $detected_schema_names[] = $type;
            }
        }
        update_post_meta($post->ID, '_requestdesk_detected_schemas', $detected_schema_names);

        return $schemas;
    }

    /**
     * Output schema markup as JSON-LD
     */
    public function output_schema_markup($schemas) {
        if (empty($schemas)) {
            return '';
        }

        $output = '';
        foreach ($schemas as $schema) {
            if (!empty($schema)) {
                $output .= '<script type="application/ld+json">';
                $output .= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                $output .= '</script>' . "\n";
            }
        }

        return $output;
    }
}