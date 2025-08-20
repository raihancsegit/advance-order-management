<?php
// includes/api/controllers/class-aorw-customer-controller.php
if (! defined('ABSPATH')) exit;

class AORW_Customer_Controller
{

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

    public function get_customer_orders_list($request)
    {
        $customer_id = get_current_user_id();
        if ($customer_id === 0) {
            return new WP_REST_Response(['error' => 'User not logged in.'], 401);
        }

        $page     = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        $status   = $request->get_param('status');
        $search   = $request->get_param('search');

        $args = array(
            'customer_id' => $customer_id,
            'limit'       => $per_page,
            'paged'       => $page,
            'paginate'    => true,
        );

        if (! empty($status) && $status !== 'any') {
            $args['status'] = $status;
        }

        // Simple search for order ID
        if (! empty($search) && is_numeric($search)) {
            $args['post__in'] = array($search);
        }
        // Note: Searching by product name is more complex and can be a premium feature.

        $query = new WC_Order_Query($args);
        $result = $query->get_orders();

        $orders_data = [];
        foreach ($result->orders as $order) {
            $items = [];
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                $items[] = [
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'image' => $product ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : wc_placeholder_img_src(),
                ];
            }

            $orders_data[] = [
                'id'             => $order->get_id(),
                'order_number'   => $order->get_order_number(),
                'date_created'   => $order->get_date_created()->format('M j, Y'),
                'status'         => $order->get_status(),
                'status_name'    => wc_get_order_status_name($order->get_status()),
                'total'          => $order->get_formatted_order_total(),
                'items'          => $items,
                'view_url'       => $order->get_view_order_url(),
            ];
        }

        $response = new WP_REST_Response([
            'orders' => $orders_data,
            'totalPages' => $result->max_num_pages,
            'totalOrders' => $result->total,
        ], 200);

        return $response;
    }

    public function get_customer_spending_chart_data($request)
    {
        $customer_id = get_current_user_id();
        $data = array_fill(0, 12, 0); // Initialize an array for 12 months with 0
        $labels = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = new DateTime("first day of -$i month");
            $labels[] = $date->format('M Y');
        }

        $orders = wc_get_orders([
            'customer_id' => $customer_id,
            'limit' => -1,
            'status' => ['wc-completed', 'wc-processing'],
            'date_created' => '>' . date('Y-m-d', strtotime('-12 months')),
        ]);

        foreach ($orders as $order) {
            $month_index = (int)date('n', strtotime($order->get_date_created())) - (int)date('n') + 11;
            if (date('Y', strtotime($order->get_date_created())) < date('Y')) {
                $month_index = (int)date('n', strtotime($order->get_date_created())) - 1;
            } else {
                $month_index = (int)date('n', strtotime($order->get_date_created())) - (int)date('n') + 11;
                if ($month_index > 11) $month_index -= 12;
            }

            if (isset($data[$month_index])) {
                $data[$month_index] += $order->get_total();
            }
        }

        $chart_data = ['labels' => $labels, 'data' => $data];
        return new WP_REST_Response($chart_data, 200);
    }

    public function export_customer_orders($request)
    {
        $customer_id = get_current_user_id();
        $orders = wc_get_orders(['customer_id' => $customer_id, 'limit' => -1]);

        $export_data = [];
        foreach ($orders as $order) {
            $export_data[] = [
                'Order ID' => $order->get_id(),
                'Date' => $order->get_date_created()->format('Y-m-d'),
                'Status' => $order->get_status(),
                'Total' => $order->get_total(),
            ];
        }
        return new WP_REST_Response($export_data, 200);
    }
}
