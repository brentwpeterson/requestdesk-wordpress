<?php
/**
 * RequestDesk graph piece for Yoast SEO's schema graph.
 *
 * Loaded only from RequestDesk_Yoast_Schema::add_graph_pieces(), after it has
 * confirmed Yoast's Abstract_Schema_Piece class exists. Never require this file
 * at plugin load: the parent class does not exist on sites without Yoast.
 *
 * Yoast injects $context and $helpers into every piece before calling
 * is_needed() and generate(), and keys pieces by $identifier, so each
 * connector piece gets a unique "requestdesk_*" identifier.
 *
 * @package RequestDesk
 * @since 2.46.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Yoast_Schema_Piece extends \Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece {

    /** @var callable Receives the Yoast context, returns a node array or null. */
    private $builder;

    /** @var array|null|false Built node, cached between is_needed() and generate(). false = not built yet. */
    private $node = false;

    /**
     * @param string   $identifier Unique Yoast piece identifier.
     * @param callable $builder    Node builder.
     */
    public function __construct($identifier, $builder) {
        $this->identifier = $identifier;
        $this->builder = $builder;
    }

    private function node() {
        if ($this->node === false) {
            $this->node = call_user_func($this->builder, $this->context);
        }
        return $this->node;
    }

    public function is_needed() {
        return !empty($this->node());
    }

    public function generate() {
        $node = $this->node();
        return empty($node) ? false : $node;
    }
}
