<?php
// includes/class-aorw-my-account.php

if (! defined('ABSPATH')) {
    exit;
}

class AORW_My_Account
{
    // Our custom endpoint slug
    const ENDPOINT = 'my-reports';

    public function __construct()
    {
        // Add the menu item
        add_filter('woocommerce_account_menu_items', array($this, 'add_my_reports_menu_item'), 20);

        // Register the endpoint
        add_action('init', array($this, 'add_my_reports_endpoint'));

        // ✅ Tell WooCommerce what content to show on our endpoint's page
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', array($this, 'render_my_reports_content'));

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function add_my_reports_menu_item($items)
    {
        // Add 'My Reports' after 'orders'
        $logout_item = $items['customer-logout'];
        unset($items['customer-logout']);
        $items[self::ENDPOINT] = __('My Reports', 'advanced-order-reports-for-woocommerce');
        $items['customer-logout'] = $logout_item;
        return $items;
    }

    public function add_my_reports_endpoint()
    {
        add_rewrite_endpoint(self::ENDPOINT, EP_ROOT | EP_PAGES);
    }

    /**
     * This function is now correctly hooked to WooCommerce's endpoint action.
     * It will be called by WooCommerce to render the content.
     */
    public function render_my_reports_content()
    {
        echo '<!-- AORW App Root Start -->';
        echo '<div id="aorw-customer-app-root">Loading Reports...</div>';
        echo '<!-- AORW App Root End -->';
    }

    public function enqueue_assets()
    {
        // Only load our scripts on the "My Reports" endpoint.
        if (is_account_page() && is_wc_endpoint_url(self::ENDPOINT)) {

            // Let's build the path and URL in a very direct way.
            $plugin_slug = 'advanced-order-reports-for-woocommerce'; // Your plugin's folder name

            // The URL to the build directory.
            $build_url = content_url("/plugins/{$plugin_slug}/build/");

            // The physical path to the build directory.
            $build_path = WP_PLUGIN_DIR . "/{$plugin_slug}/build/";

            $manifest_path = $build_path . 'asset-manifest.json';

            if (! file_exists($manifest_path)) {
                // If the manifest file is not found, let's output a comment in HTML
                // so we can see it in "View Source". This helps debugging.
                add_action('wp_footer', function () {
                    echo "<!-- AORW DEBUG: asset-manifest.json not found at: {$manifest_path} -->";
                });
                return;
            }

            $manifest = json_decode(file_get_contents($manifest_path), true);
            $files = $manifest['files'];

            // Enqueue CSS
            if (isset($files['main.css'])) {
                wp_enqueue_style(
                    'aorw-react-app-styles1',
                    $build_url . $files['main.css'],
                    array(),
                    filemtime($build_path . $files['main.css']) // Auto-versioning
                );
            }

            // Enqueue JS
            if (isset($files['main.js'])) {
                wp_enqueue_script(
                    'aorw-react-app-scripts1',
                    $build_url . $files['main.js'],
                    array('wp-element'),
                    filemtime($build_path . $files['main.js']), // Auto-versioning
                    true
                );
            }

            // We are not using wp_localize_script yet to keep it simple.
        }
    }
}
