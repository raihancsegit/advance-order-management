<?php

/**
 * Plugin Name:       Advanced Order Reports for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/your-plugin-slug/
 * Description:       A modern reporting dashboard for WooCommerce, built with React.
 * Version:           1.1.0
 * Author:            Your Name
 * License:           GPL v2 or later
 * Text Domain:       advanced-order-reports-for-woocommerce
 * WC requires at least: 6.0
 */

// Block direct access.
if (! defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AORW_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));
define('AORW_APP_BUILD_PATH', plugin_dir_path(__FILE__) . 'app/build/');
define('AORW_APP_BUILD_URL', plugin_dir_url(__FILE__) . 'app/build/');

/**
 * The main plugin class.
 */
final class Advanced_Order_Reports_For_WooCommerce
{

    private static $_instance = null;

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct()
    {
        add_action('admin_menu', array($this, 'register_admin_page'));
    }

    /**
     * Register the admin menu page for our app.
     */
    public function register_admin_page()
    {
        $hook_suffix = add_menu_page(
            __('Order Reports', 'advanced-order-reports-for-woocommerce'),
            __('Order Reports', 'advanced-order-reports-for-woocommerce'),
            'view_woocommerce_reports',
            'aorw-reports',
            array($this, 'render_app_root'),
            'dashicons-chart-area',
            56
        );

        // Load our app's scripts and styles only on our admin page.
        add_action('admin_enqueue_scripts', function ($hook) use ($hook_suffix) {
            if ($hook === $hook_suffix) {
                $this->enqueue_react_app_assets();
            }
        });
    }

    /**
     * Render the root HTML element for our React app to mount on.
     */
    public function render_app_root()
    {
        // This div is the entry point for our React app.
        echo '<div id="aorw-react-app-root"></div>';
    }

    /**
     * Enqueue the built JS and CSS files from the React app.
     */
    private function enqueue_react_app_assets()
    {
        $manifest_path = AORW_APP_BUILD_PATH . 'asset-manifest.json';

        if (! file_exists($manifest_path)) {
            // Show an error if the build files are not found.
            wp_die(esc_html__('React app build files are missing. Please run "npm run build" in the react-app directory and copy the build folder.', 'advanced-order-reports-for-woocommerce'));
            return;
        }

        // Decode the asset manifest to find the file names.
        $manifest = json_decode(file_get_contents($manifest_path), true);
        $entrypoints = $manifest['files'];

        // Enqueue the main CSS file.
        if (isset($entrypoints['main.css'])) {
            wp_enqueue_style(
                'aorw-react-app',
                AORW_APP_BUILD_URL . $entrypoints['main.css']
            );
        }

        // Enqueue the main JS file.
        if (isset($entrypoints['main.js'])) {
            wp_enqueue_script(
                'aorw-react-app',
                AORW_APP_BUILD_URL . $entrypoints['main.js'],
                array('wp-element'), // Dependency
                null,                // Version
                true                 // Load in footer
            );
        }

        // Optional: Add inline CSS for a better fullscreen experience
        $inline_css = "
            #wpbody-content { position: relative; }
            #aorw-react-app-root { height: calc(100vh - 32px); }
        ";
        wp_add_inline_style('aorw-react-app', $inline_css);
    }
}

/**
 * Initialize the plugin once all other plugins are loaded.
 */
add_action('plugins_loaded', array('Advanced_Order_Reports_For_WooCommerce', 'instance'));
