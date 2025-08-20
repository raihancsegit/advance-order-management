<?php
// includes/class-aorw-api.php
if (! defined('ABSPATH')) {
    exit;
}

class AORW_API
{
    /** @var AORW_Overview_Controller */
    private $overview_controller;
    /** @var AORW_Orders_Controller */
    private $orders_controller;
    /** @var AORW_Products_Controller */
    private $products_controller;
    /** @var AORW_Categories_Controller */
    private $categories_controller;
    /** @var AORW_Gateways_Controller */
    private $gateways_controller;
    /** @var AORW_Customer_Controller */
    private $customer_controller;

    public function __construct()
    {
        $this->load_and_instantiate_controllers();
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    private function load_and_instantiate_controllers()
    {
        $path = plugin_dir_path(__FILE__) . 'api/controllers/';

        require_once $path . 'class-aorw-overview-controller.php';
        $this->overview_controller = new AORW_Overview_Controller();

        require_once $path . 'class-aorw-orders-controller.php';
        $this->orders_controller = new AORW_Orders_Controller();

        require_once $path . 'class-aorw-products-controller.php';
        $this->products_controller = new AORW_Products_Controller();

        require_once $path . 'class-aorw-categories-controller.php';
        $this->categories_controller = new AORW_Categories_Controller();

        require_once $path . 'class-aorw-gateways-controller.php';
        $this->gateways_controller = new AORW_Gateways_Controller();

        require_once $path . 'class-aorw-customer-controller.php';
        $this->customer_controller = new AORW_Customer_Controller();
    }

    public function register_routes()
    {
        $namespace = 'aorw/v1';

        // Admin Routes
        $this->register_route($namespace, '/overview', 'GET', array($this->overview_controller, 'get_overview_data'), 'admin');
        $this->register_route($namespace, '/chart/sales-over-time', 'GET', array($this->overview_controller, 'get_sales_chart_data'), 'admin');
        $this->register_route($namespace, '/orders', 'GET', array($this->orders_controller, 'get_orders_report'), 'admin');
        $this->register_route($namespace, '/export/orders', 'GET', array($this->orders_controller, 'export_orders_report'), 'admin');
        $this->register_route($namespace, '/products/bestsellers', 'GET', array($this->products_controller, 'get_bestsellers_report'), 'admin');
        $this->register_route($namespace, '/categories/bestsellers', 'GET', array($this->categories_controller, 'get_bestselling_categories_report'), 'admin');
        $this->register_route($namespace, '/gateways', 'GET', array($this->gateways_controller, 'get_gateways_report'), 'admin');

        // Customer Routes
        $this->register_route($namespace, '/customer/overview', 'GET', array($this->customer_controller, 'get_customer_overview_data'), 'customer');
        $this->register_route($namespace, '/customer/orders', 'GET', array($this->customer_controller, 'get_customer_orders_list'), 'customer');
        $this->register_route($namespace, '/customer/spending-chart', 'GET', array($this->customer_controller, 'get_customer_spending_chart_data'), 'customer');
        $this->register_route($namespace, '/customer/export-orders', 'GET', array($this->customer_controller, 'export_customer_orders'), 'customer');
    }

    /**
     * Helper function to register a REST route.
     */
    private function register_route($namespace, $route, $methods, $callback, $permission_type)
    {
        register_rest_route($namespace, $route, array(
            'methods'  => $methods,
            'callback' => $callback,
            'permission_callback' => ($permission_type === 'admin')
                ? array($this, 'check_admin_permissions')
                : array($this, 'check_customer_permissions'),
            // Note: Args are removed for simplicity to ensure it works. You can add them back later.
        ));
    }

    public function check_admin_permissions()
    {
        return current_user_can('view_woocommerce_reports');
    }

    public function check_customer_permissions()
    {
        return is_user_logged_in();
    }
}
