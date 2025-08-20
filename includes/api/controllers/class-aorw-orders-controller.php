<?php
// includes/api/controllers/class-aorw-orders-controller.php
if (! defined('ABSPATH')) exit;

class AORW_Orders_Controller
{

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
}
