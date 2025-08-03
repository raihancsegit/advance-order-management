<?php

/**
 * Plugin Name:       Advanced Order Reports for WooCommerce
 * Plugin URI:        https://github.com/raihancsegit/advance-order-management
 * Description:       A modern reporting dashboard for WooCommerce, built with React.
 * Version:           1.2.0
 * Author:            Raihan Islam
 * License:           GPL v2 or later
 * Text Domain:       advanced-order-reports-for-woocommerce
 */

if (! defined('ABSPATH')) {
    exit;
}

define('AORW_VERSION', '1.2.0');
require_once plugin_dir_path(__FILE__) . 'includes/class-aorw-api.php';

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
        // Register the admin menu page on the 'admin_menu' hook.
        add_action('admin_menu', array($this, 'register_admin_page'));
        new AORW_API();
    }

    /**
     * Registers the admin menu page.
     */
    public function register_admin_page()
    {
        add_menu_page(
            'Order Reports',
            'Order Reports',
            'view_woocommerce_reports',
            'aorw-reports', // This is our page's slug
            array($this, 'render_app_root'),
            'dashicons-chart-area',
            56
        );

        // ✅ The correct way to enqueue scripts for a specific admin page.
        // We use the 'admin_enqueue_scripts' hook directly, not inside another function.
        // We check if the current page is our reports page.
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets_for_admin_page'));
    }

    /**
     * Renders the root div for our React app.
     */
    public function render_app_root()
    {
        echo '<div id="aorw-react-app-root"></div>';
    }

    /**
     * Checks the current admin page and enqueues assets if it's our page.
     * @param string $hook The hook suffix of the current page.
     */
    public function enqueue_assets_for_admin_page($hook)
    {
        // We only load our assets on our specific plugin page.
        if ('toplevel_page_aorw-reports' !== $hook) {
            return;
        }

        // ✅ Step 1: Enqueue Tailwind CSS directly from its CDN.
        wp_enqueue_style(
            'tailwindcss-cdn',
            'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css', // Tailwind v2 is stable for CDN
            array(),
            '2.2.19'
        );

        // --- The rest of the function remains the same ---

        $build_path = plugin_dir_path(__FILE__) . 'build/';
        $build_url = plugin_dir_url(__FILE__) . 'build/';
        $manifest_path = $build_path . 'asset-manifest.json';

        if (! file_exists($manifest_path)) {
            wp_die('<strong>Error:</strong> React app build files are missing.');
            return;
        }

        $manifest = json_decode(file_get_contents($manifest_path), true);
        $files = $manifest['files'];

        // Enqueue the (now Tailwind-free) main CSS file from our app.
        if (isset($files['main.css'])) {
            wp_enqueue_style(
                'aorw-react-app-styles',
                $build_url . $files['main.css'],
                array('tailwindcss-cdn'), // ✅ Set Tailwind as a dependency
                AORW_VERSION
            );
        }

        // Enqueue the main JS file.
        if (isset($files['main.js'])) {
            wp_enqueue_script(
                'aorw-react-app-scripts',
                $build_url . $files['main.js'],
                array('wp-element'),
                AORW_VERSION,
                true
            );
        }

        wp_localize_script(
            'aorw-react-app-scripts',
            'aorwApiSettings',
            array(
                'root'  => esc_url_raw(rest_url('aorw/v1/')),
                'nonce' => wp_create_nonce('wp_rest'),
            )
        );

        // We don't need extra inline CSS anymore, as the CDN handles scoping.
    }
}

// Initialize the plugin
add_action('plugins_loaded', array('Advanced_Order_Reports_For_WooCommerce', 'instance'));
