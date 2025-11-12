<?php
/**
 * Plugin Name:       گردونه شانس پرو
 * Description:       افزونه پیشرفته گردونه شانس با ویجت المنتور و قابلیت‌های متنوع.
 * Version:           3.0
 * Author:            احسان
 * Text Domain:       lwt-lucky-wheel
 */
if (!defined('ABSPATH')) exit;

define('LWT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('LWT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LWT_VERSION', '3.0');

// Activation Hook
register_activation_hook(__FILE__, ['LWT_Admin', 'activate']);

// Include all necessary class files
require_once LWT_PLUGIN_PATH . 'includes/class-lwt-admin.php';
require_once LWT_PLUGIN_PATH . 'includes/class-lwt-ajax.php';
require_once LWT_PLUGIN_PATH . 'includes/class-lwt-frontend.php';
require_once LWT_PLUGIN_PATH . 'includes/elementor/elementor-init.php';

// Initialize main plugin classes
new LWT_Admin();
new LWT_Ajax();
new LWT_Frontend();
new LWT_Elementor_Init();
