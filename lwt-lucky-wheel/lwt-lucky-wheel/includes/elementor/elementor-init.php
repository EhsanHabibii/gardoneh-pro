<?php
if (!defined('ABSPATH')) exit;

class LWT_Elementor_Init {

    public function __construct() {
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
    }

    public function register_widgets($widgets_manager) {
        require_once LWT_PLUGIN_PATH . 'includes/elementor/class-lwt-elementor-widget.php';
        $widgets_manager->register(new LWT_Elementor_Widget());
    }
}
