<?php
// includes/class-aorw-my-account.php

if (! defined('ABSPATH')) {
    exit;
}

class AORW_My_Account
{
    const ENDPOINT = 'my-reports';

    public function __construct()
    {
        // Step 1: Register endpoint and menu item. These are fine.
        add_action('init', array($this, 'add_my_reports_endpoint'));
        add_filter('woocommerce_account_menu_items', array($this, 'add_my_reports_menu_item'), 20);

        // Step 2: Render the content for the endpoint. This is also fine.
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', array($this, 'render_my_reports_content'));

        // ✅ Step 3: THE FIX - Use a different hook to enqueue scripts.
        // The 'wp' action hook runs after the query variables are set, but before the header is sent.
        // It's a perfect and reliable place to check our condition and then add our enqueue action.
        add_action('wp', array($this, 'maybe_enqueue_assets'));
    }

    public function add_my_reports_endpoint()
    {
        add_rewrite_endpoint(self::ENDPOINT, EP_ROOT | EP_PAGES);
    }

    public function add_my_reports_menu_item($items)
    {
        $logout_item = $items['customer-logout'] ?? null;
        if ($logout_item) unset($items['customer-logout']);
        $items[self::ENDPOINT] = __('My Reports', 'advanced-order-reports-for-woocommerce');
        if ($logout_item) $items['customer-logout'] = $logout_item;
        return $items;
    }

    /**
     * This function runs on the 'wp' hook, which is a reliable point to check query vars.
     * If the condition is met, THEN it adds the 'enqueue_assets' function to 'wp_enqueue_scripts'.
     */
    public function maybe_enqueue_assets()
    {
        // Check if we are on the correct page. This is the condition that was failing before.
        if (is_account_page() && get_query_var(self::ENDPOINT, false) !== false) {
            // If we are on the right page, hook our enqueue function to run.
            add_action('wp_enqueue_scripts', array($this, 'enqueue_assets_callback'));
        }
    }

    /**
     * This is the actual enqueue function. It will only be called if the check in 'maybe_enqueue_assets' passes.
     */
    public function enqueue_assets_callback()
    {
        // Use the reliable get_query_var check
        if (is_account_page() && get_query_var(self::ENDPOINT, false) !== false) {

            // ✅ Step 1: Enqueue Tailwind CSS from its CDN, just like in the admin area.
            wp_enqueue_style(
                'tailwindcss-cdn',
                'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css',
                array(),
                '2.2.19'
            );

            // --- The rest of the function remains the same ---

            $main_plugin_file = dirname(__DIR__) . '/advanced-order-reports-for-woocommerce.php';
            $build_path       = plugin_dir_path($main_plugin_file) . 'build/';
            $build_url        = plugin_dir_url($main_plugin_file) . 'build/';
            $manifest_path    = $build_path . 'asset-manifest.json';

            if (! file_exists($manifest_path)) return;

            $manifest = json_decode(file_get_contents($manifest_path), true);
            $files = $manifest['files'];
            $handle = 'aorw-customer-app';

            // Enqueue our app's custom CSS (if any), and make it dependent on Tailwind.
            if (isset($files['main.css'])) {
                wp_enqueue_style(
                    $handle,
                    $build_url . $files['main.css'],
                    ['tailwindcss-cdn'], // ✅ Set Tailwind as a dependency
                    filemtime($build_path . $files['main.css'])
                );
            }

            // Enqueue our app's JS
            if (isset($files['main.js'])) {
                wp_enqueue_script(
                    $handle,
                    $build_url . $files['main.js'],
                    ['wp-element'],
                    filemtime($build_path . $files['main.js']),
                    true
                );
            }

            wp_localize_script(
                $handle,
                'aorwApiSettings',
                [
                    'root'     => esc_url_raw(rest_url('aorw/v1/')),
                    'nonce'    => wp_create_nonce('wp_rest'),
                ]
            );
        }
    }

    public function render_my_reports_content()
    {
        echo '<div id="aorw-customer-app-root"></div>';
    }
}
