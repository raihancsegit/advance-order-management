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
}
