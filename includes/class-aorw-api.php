<?php
// includes/class-aorw-api.php

if (! defined('ABSPATH')) {
    exit;
}

class AORW_API
{

    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes()
    {
        $namespace = 'aorw/v1';
        register_rest_route($namespace, '/overview', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_overview_data'),
            'permission_callback' => array($this, 'check_permissions'),
            'args'                => array(
                'start_date' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
                'end_date'   => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
            ),
        ));

        register_rest_route($namespace, '/chart/sales-over-time', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_sales_chart_data'),
            'permission_callback' => array($this, 'check_permissions'),
            'args'                => array(
                'start_date' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
                'end_date'   => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
            ),
        ));

        // ✅ New route for paginated orders report
        register_rest_route($namespace, '/orders', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_orders_report'),
            'permission_callback' => array($this, 'check_permissions'),
            'args'                => array(
                'start_date' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
                'end_date'   => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
                'page'       => array('required' => false, 'sanitize_callback' => 'absint', 'default' => 1),
                'per_page'   => array('required' => false, 'sanitize_callback' => 'absint', 'default' => 20),
                'status'     => array('required' => false, 'sanitize_callback' => 'sanitize_text_field', 'default' => 'any'),
            ),
        ));

        // ✅ New route for exporting orders
        register_rest_route($namespace, '/export/orders', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'export_orders_report'),
            'permission_callback' => array($this, 'check_permissions'),
            'args'                => array(
                'start_date' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
                'end_date'   => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
                'status'     => array('required' => false, 'sanitize_callback' => 'sanitize_text_field'),
            ),
        ));

        // includes/class-aorw-api.php -> register_routes() function
        // ... existing routes ...

        // ✅ New route for best-selling products report
        register_rest_route($namespace, '/products/bestsellers', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_bestsellers_report'),
            'permission_callback' => array($this, 'check_permissions'),
            'args'                => array(
                'start_date' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
                'end_date'   => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
                'limit'      => array('required' => false, 'sanitize_callback' => 'absint', 'default' => 20),
            ),
        ));

        // ✅ New route for best-selling categories report
        register_rest_route($namespace, '/categories/bestsellers', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_bestselling_categories_report'),
            'permission_callback' => array($this, 'check_permissions'),
            'args'                => array(
                'start_date' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
                'end_date'   => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
                'limit'      => array('required' => false, 'sanitize_callback' => 'absint', 'default' => 10),
            ),
        ));

        // ✅ New route for payment gateways report
        register_rest_route($namespace, '/gateways', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_gateways_report'),
            'permission_callback' => array($this, 'check_permissions'),
            'args'                => array(
                'start_date' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
                'end_date'   => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
            ),
        ));

        register_rest_route($namespace, '/customer/overview', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_customer_overview_data'),
            'permission_callback' => array($this, 'is_current_customer_allowed'),
        ));
    }

    public function check_permissions()
    {
        return current_user_can('view_woocommerce_reports');
    }

    /**
     * Checks if HPOS is active using a bulletproof method.
     * It checks if the specific method exists before calling it.
     * @return bool
     */
    private function is_hpos_active()
    {
        // First, check if the OrderUtil class exists.
        if (! class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            return false;
        }
        // Then, check if the specific method exists within that class.
        // This prevents the "Call to undefined method" fatal error.
        if (! method_exists('Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_is_enabled')) {
            return false;
        }
        // Only if both checks pass, call the method.
        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_is_enabled();
    }

    public function get_overview_data($request)
    {
        if ($this->is_hpos_active()) {
            return $this->get_overview_from_hpos_table($request);
        } else {
            return $this->get_overview_from_legacy_posts($request);
        }
    }

    private function get_overview_from_legacy_posts($request)
    {
        $start_date = $request->get_param('start_date');
        $end_date   = $request->get_param('end_date');

        $query = new WC_Order_Query(array(
            'limit'        => -1,
            'status'       => array('wc-processing', 'wc-completed'),
            'date_created' => $start_date . '...' . $end_date,
            'return'       => 'ids',
        ));

        $order_ids = $query->get_orders();

        if (empty($order_ids)) {
            return new WP_REST_Response($this->get_zero_overview_data(), 200);
        }

        $gross_sales = 0;
        $items_sold = 0;
        $total_discount = 0;

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (! $order) continue;

            $gross_sales += $order->get_total();
            $items_sold += $order->get_item_count();
            $total_discount += $order->get_discount_total();
        }

        $orders_count = count($order_ids);
        $net_sales = $gross_sales - $total_discount;
        $avg_order_value = ($orders_count > 0) ? $gross_sales / $orders_count : 0;

        $data = $this->format_overview_data($gross_sales, $net_sales, $orders_count, $avg_order_value, $items_sold, $total_discount);

        return new WP_REST_Response($data, 200);
    }

    private function get_overview_from_hpos_table($request)
    {
        global $wpdb;
        $start_date = $request->get_param('start_date') . ' 00:00:00';
        $end_date   = $request->get_param('end_date') . ' 23:59:59';
        $order_statuses = array('wc-processing', 'wc-completed');
        $status_placeholders = implode(', ', array_fill(0, count($order_statuses), '%s'));
        $query_args = array_merge(array($start_date, $end_date), $order_statuses);
        $results = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(total_sales) as gross_sales, COUNT(id) as orders_count, SUM(num_items_sold) as items_sold, SUM(net_total) as net_sales, SUM(total_sales - net_total) as total_discount FROM {$wpdb->prefix}wc_orders_stats WHERE date_created_gmt >= %s AND date_created_gmt <= %s AND status IN ($status_placeholders)",
            $query_args
        ));

        if (is_null($results)) {
            $results = (object) $this->get_zero_overview_data();
        }

        $gross_sales = $results->gross_sales ?? 0;
        $orders_count = $results->orders_count ?? 0;
        $avg_order_value = ($orders_count > 0) ? $gross_sales / $orders_count : 0;

        $data = $this->format_overview_data($gross_sales, $results->net_sales ?? 0, $orders_count, $avg_order_value, $results->items_sold ?? 0, $results->total_discount ?? 0);

        return new WP_REST_Response($data, 200);
    }

    private function get_zero_overview_data()
    {
        return $this->format_overview_data(0, 0, 0, 0, 0, 0);
    }

    private function format_overview_data($gross_sales, $net_sales, $orders_count, $avg_order_value, $items_sold, $total_discount)
    {
        return array(
            'gross_sales'      => (float) $gross_sales,
            'net_sales'        => (float) $net_sales,
            'orders_count'     => (int) $orders_count,
            'avg_order_value'  => (float) $avg_order_value,
            'items_sold'       => (int) $items_sold,
            'total_discount'   => (float) $total_discount,
        );
    }

    /**
     * Get data for the sales over time chart.
     * This function will be compatible with both HPOS and Legacy modes.
     */
    public function get_sales_chart_data($request)
    {
        global $wpdb;

        $start_date = $request->get_param('start_date');
        $end_date   = $request->get_param('end_date');

        // Determine which table to use based on HPOS status
        $table_name = $this->is_hpos_active() ? $wpdb->prefix . 'wc_orders_stats' : $wpdb->prefix . 'posts';
        $date_column = $this->is_hpos_active() ? 'date_created_gmt' : 'post_date_gmt';

        // We need to handle the different ways sales are stored
        if ($this->is_hpos_active()) {
            $sales_column = 'total_sales';
            $status_column = 'status';
            $order_statuses = array('wc-processing', 'wc-completed');
            $status_placeholders = implode(', ', array_fill(0, count($order_statuses), '%s'));
            $where_clause = "AND $status_column IN ($status_placeholders)";
            $query_args = array_merge(array($start_date . ' 00:00:00', $end_date . ' 23:59:59'), $order_statuses);
        } else {
            // For legacy, we need to join with postmeta to get total sales
            // This query is more complex. Let's use a simpler approach for now
            // A more optimized query might be needed for very large stores.
            return $this->get_sales_chart_data_legacy($start_date, $end_date);
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT
            DATE( $date_column ) as order_date,
            SUM( $sales_column ) as daily_sales
        FROM $table_name
        WHERE $date_column >= %s AND $date_column <= %s $where_clause
        GROUP BY order_date
        ORDER BY order_date ASC",
            $query_args
        ));

        // Format data for Chart.js
        $labels = [];
        $data = [];

        // Create a complete date range to fill in days with zero sales
        $period = new DatePeriod(
            new DateTime($start_date),
            new DateInterval('P1D'),
            (new DateTime($end_date))->modify('+1 day')
        );

        $sales_by_date = [];
        foreach ($results as $result) {
            $sales_by_date[$result->order_date] = $result->daily_sales;
        }

        foreach ($period as $date) {
            $formatted_date = $date->format('Y-m-d');
            $labels[] = $date->format('M j'); // Format like 'Aug 3'
            $data[] = isset($sales_by_date[$formatted_date]) ? (float) $sales_by_date[$formatted_date] : 0;
        }

        $chart_data = array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => 'Gross Sales',
                    'data'  => $data,
                    'borderColor' => '#3B82F6', // blue-500
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                )
            )
        );

        return new WP_REST_Response($chart_data, 200);
    }

    /**
     * Legacy support for sales chart data
     */
    private function get_sales_chart_data_legacy($start_date, $end_date)
    {
        $query = new WC_Order_Query(array(
            'limit'        => -1,
            'status'       => array('wc-processing', 'wc-completed'),
            'date_created' => $start_date . '...' . $end_date,
        ));
        $orders = $query->get_orders();

        $sales_by_date = [];

        foreach ($orders as $order) {
            $date = $order->get_date_created()->format('Y-m-d');
            if (!isset($sales_by_date[$date])) {
                $sales_by_date[$date] = 0;
            }
            $sales_by_date[$date] += $order->get_total();
        }

        // Format for chart
        $labels = [];
        $data = [];
        $period = new DatePeriod(
            new DateTime($start_date),
            new DateInterval('P1D'),
            (new DateTime($end_date))->modify('+1 day')
        );

        foreach ($period as $date) {
            $formatted_date = $date->format('Y-m-d');
            $labels[] = $date->format('M j');
            $data[] = $sales_by_date[$formatted_date] ?? 0;
        }

        $chart_data = array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => 'Gross Sales',
                    'data'  => $data,
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                )
            )
        );

        return new WP_REST_Response($chart_data, 200);
    }

    /**
     * Get paginated orders report data.
     * This is compatible with both HPOS and Legacy modes.
     */
    public function get_orders_report($request)
    {
        $start_date = $request->get_param('start_date');
        $end_date   = $request->get_param('end_date');
        $page       = $request->get_param('page');
        $per_page   = $request->get_param('per_page');
        $status     = $request->get_param('status');

        $args = array(
            'limit'        => $per_page,
            'paged'        => $page,
            'date_created' => $start_date . '...' . $end_date,
            'return'       => 'objects', // We need full order objects
            'paginate'     => true,      // This is key for getting total counts
        );

        if ($status !== 'any' && !empty($status)) {
            $args['status'] = $status;
        }

        $query = new WC_Order_Query($args);
        $result = $query->get_orders();

        $orders_data = array();
        foreach ($result->orders as $order) {
            $orders_data[] = array(
                'id'             => $order->get_id(),
                'order_number'   => $order->get_order_number(),
                'date_created'   => $order->get_date_created()->format('M j, Y, g:i a'),
                'status'         => wc_get_order_status_name($order->get_status()),
                'status_key'     => $order->get_status(),
                'customer_name'  => $order->get_formatted_billing_full_name(),
                'total'          => (float) $order->get_total(),
                'currency'       => $order->get_currency(),
                'edit_url'       => $order->get_edit_order_url(),
            );
        }

        $response = new WP_REST_Response($orders_data, 200);

        // Add pagination headers
        $response->header('X-WP-Total', $result->total);
        $response->header('X-WP-TotalPages', $result->max_num_pages);

        return $response;
    }

    public function export_orders_report($request)
    {
        // This is almost the same as get_orders_report, but without pagination
        $start_date = $request->get_param('start_date');
        $end_date   = $request->get_param('end_date');
        $status     = $request->get_param('status');

        $args = array(
            'limit'        => -1, // -1 means all orders
            'date_created' => $start_date . '...' . $end_date,
            'return'       => 'objects',
        );
        if ($status !== 'any' && !empty($status)) {
            $args['status'] = $status;
        }

        $query = new WC_Order_Query($args);
        $orders = $query->get_orders();

        $orders_data = array();
        foreach ($orders as $order) {
            $orders_data[] = array(
                'order_id'       => $order->get_id(),
                'order_number'   => $order->get_order_number(),
                'date_created'   => $order->get_date_created()->format('Y-m-d H:i:s'),
                'status'         => $order->get_status(),
                'customer_name'  => $order->get_formatted_billing_full_name(),
                'total'          => $order->get_total(),
            );
        }

        return new WP_REST_Response($orders_data, 200);
    }

    /**
     * Get best-selling products report data.
     */
    public function get_bestsellers_report($request)
    {
        global $wpdb;

        $start_date = $request->get_param('start_date');
        $end_date   = $request->get_param('end_date');
        $limit      = $request->get_param('limit');

        // Define which order statuses are considered as a valid sale.
        $valid_order_statuses = array('wc-processing', 'wc-completed');
        $status_placeholders = implode(', ', array_fill(0, count($valid_order_statuses), '%s'));

        // This query now joins with the orders table to check the status.
        // It is compatible with both HPOS (wc_orders) and legacy (posts) as lookup table works for both.
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT
            p.ID as product_id,
            p.post_title as product_name,
            MAX(pm.meta_value) as sku,
            SUM(wooi.product_qty) as units_sold,
            SUM(wooi.product_net_revenue) as total_sales
        FROM
            {$wpdb->prefix}posts as p
        INNER JOIN
            {$wpdb->prefix}wc_order_product_lookup as wooi ON p.ID = wooi.product_id
        LEFT JOIN
            {$wpdb->prefix}postmeta as pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
        WHERE
            wooi.date_created BETWEEN %s AND %s
            AND wooi.order_id IN (
                SELECT id FROM {$wpdb->prefix}wc_orders WHERE status IN ($status_placeholders)
            )
        GROUP BY
            p.ID, p.post_title
        ORDER BY
            total_sales DESC
        LIMIT %d",
            array_merge(
                array($start_date . ' 00:00:00', $end_date . ' 23:59:59'),
                $valid_order_statuses,
                array($limit)
            )
        ));

        // ... (বাকি কোড একই থাকবে, যা ডেটা ফরম্যাট করে) ...
        $products_data = array();
        foreach ($results as $product) {
            // ... (আগের কোডের মতো) ...
            $product_obj = wc_get_product($product->product_id);
            if (! $product_obj) continue;

            $products_data[] = array(
                'product_id'   => $product->product_id,
                'product_name' => $product->product_name,
                'image_url'    => wp_get_attachment_image_url($product_obj->get_image_id(), 'thumbnail'),
                'sku'          => $product->sku ?? 'N/A',
                // 'categories'   => wc_get_product_category_list( $product->product_id ), // This can be slow, let's skip for now
                'units_sold'   => (int) $product->units_sold,
                'total_sales'  => (float) $product->total_sales,
                'edit_url'     => get_edit_post_link($product->product_id, 'raw'),
            );
        }

        return new WP_REST_Response($products_data, 200);
    }

    /**
     * Get best-selling categories report data.
     */
    public function get_bestselling_categories_report($request)
    {
        global $wpdb;

        $start_date = $request->get_param('start_date');
        $end_date   = $request->get_param('end_date');
        $limit      = $request->get_param('limit');

        $valid_order_statuses = array('wc-processing', 'wc-completed');
        $status_placeholders = implode(', ', array_fill(0, count($valid_order_statuses), '%s'));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT
            t.term_id as category_id,
            t.name as category_name,
            SUM(wooi.product_qty) as units_sold,
            SUM(wooi.product_net_revenue) as total_sales
        FROM
            {$wpdb->prefix}terms as t
        INNER JOIN
            {$wpdb->prefix}term_taxonomy as tt ON t.term_id = tt.term_id
        INNER JOIN
            {$wpdb->prefix}term_relationships as tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
        INNER JOIN
            {$wpdb->prefix}wc_order_product_lookup as wooi ON tr.object_id = wooi.product_id
        WHERE
            tt.taxonomy = 'product_cat'
            AND wooi.date_created BETWEEN %s AND %s
            AND wooi.order_id IN (
                SELECT id FROM {$wpdb->prefix}wc_orders WHERE status IN ($status_placeholders)
            )
        GROUP BY
            t.term_id, t.name
        ORDER BY
            total_sales DESC
        LIMIT %d",
            array_merge(
                array($start_date . ' 00:00:00', $end_date . ' 23:59:59'),
                $valid_order_statuses,
                array($limit)
            )
        ));

        return new WP_REST_Response($results, 200);
    }

    /**
     * Get payment gateways report data.
     */
    public function get_gateways_report($request)
    {
        global $wpdb;

        $start_date = $request->get_param('start_date');
        $end_date   = $request->get_param('end_date');

        $valid_order_statuses = array('wc-processing', 'wc-completed');
        $status_placeholders = implode(', ', array_fill(0, count($valid_order_statuses), '%s'));

        $query_args = array_merge(
            array($start_date . ' 00:00:00', $end_date . ' 23:59:59'),
            $valid_order_statuses
        );

        // This query is compatible with both HPOS and Legacy modes.
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT
            meta.meta_value as payment_method_key,
            COUNT(p.ID) as order_count,
            SUM(meta_total.meta_value) as total_sales
        FROM
            {$wpdb->prefix}posts as p
        INNER JOIN
            {$wpdb->prefix}postmeta as meta ON p.ID = meta.post_id AND meta.meta_key = '_payment_method'
        INNER JOIN
            {$wpdb->prefix}postmeta as meta_total ON p.ID = meta_total.post_id AND meta_total.meta_key = '_order_total'
        WHERE
            p.post_type = 'shop_order'
            AND p.post_date BETWEEN %s AND %s
            AND p.post_status IN ($status_placeholders)
        GROUP BY
            meta.meta_value
        ORDER BY
            total_sales DESC",
            $query_args
        ));

        // Get all available payment gateways to get their titles
        $available_gateways = WC()->payment_gateways->payment_gateways();

        $gateways_data = array();
        foreach ($results as $result) {
            $gateway_title = isset($available_gateways[$result->payment_method_key])
                ? $available_gateways[$result->payment_method_key]->get_title()
                : ucwords(str_replace('_', ' ', $result->payment_method_key)); // Fallback title

            $gateways_data[] = array(
                'id'          => $result->payment_method_key,
                'title'       => $gateway_title,
                'order_count' => (int) $result->order_count,
                'total_sales' => (float) $result->total_sales,
            );
        }

        return new WP_REST_Response($gateways_data, 200);
    }

    /**
     * Permission check for customer-specific endpoints.
     * Ensures that only a logged-in user can access this endpoint.
     *
     * @return bool True if the user is logged in, false otherwise.
     */
    public function is_current_customer_allowed()
    {
        return is_user_logged_in();
    }

    /**
     * Get overview data for the currently logged-in customer.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function get_customer_overview_data($request)
    {
        $customer_id = get_current_user_id();

        // Double-check if user is logged in, although permission callback should handle this.
        if (0 === $customer_id) {
            return new WP_REST_Response(array('error' => 'You must be logged in to view your reports.'), 401);
        }

        try {
            $customer = new WC_Customer($customer_id);

            // --- Free Version Features ---
            $total_spent = $customer->get_total_spent();
            $order_count = $customer->get_order_count();
            $avg_order_value = ($order_count > 0) ? $total_spent / $order_count : 0;

            // Get the date of the customer's first order
            $first_order_query = new WC_Order_Query(array(
                'customer_id' => $customer_id,
                'limit'       => 1,
                'orderby'     => 'date',
                'order'       => 'ASC',
                'return'      => 'objects',
            ));

            $first_order = $first_order_query->get_orders();
            $first_order_date_str = ! empty($first_order) ? $first_order[0]->get_date_created()->format('M j, Y') : 'N/A';

            // Check if the user has a premium license (example logic)
            // You will need to implement the 'aorw_is_premium_active' function yourself.
            $is_premium_active = function_exists('aorw_is_premium_active') && aorw_is_premium_active();

            $data = array(
                'total_spent'      => (float) $total_spent,
                'order_count'      => (int) $order_count,
                'avg_order_value'  => (float) $avg_order_value,
                'first_order_date' => $first_order_date_str,
                'is_premium'       => $is_premium_active, // This will control locked features in React
            );

            return new WP_REST_Response($data, 200);
        } catch (Exception $e) {
            // Handle cases where WC_Customer might throw an error
            return new WP_REST_Response(array('error' => 'Could not retrieve customer data.'), 500);
        }
    }
}
