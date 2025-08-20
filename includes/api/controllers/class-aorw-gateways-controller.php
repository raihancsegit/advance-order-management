<?php
// includes/api/controllers/class-aorw-gateways-controller.php
if (! defined('ABSPATH')) exit;

class AORW_Gateways_Controller
{

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
}
