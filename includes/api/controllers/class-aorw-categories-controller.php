<?php
// includes/api/controllers/class-aorw-categories-controller.php
if (! defined('ABSPATH')) exit;

class AORW_Categories_Controller
{

    public function get_bestselling_categories_report($request)
    {
        global $wpdb;

        $start_date = $request->get_param('start_date');
        $end_date   = $request->get_param('end_date');
        $limit      = $request->get_param('limit') ?? 10;

        $valid_order_statuses = array('wc-processing', 'wc-completed');

        // ✅ THE FIX: Manually create the IN clause for the statuses.
        $status_in_clause = "IN ('" . implode("', '", $valid_order_statuses) . "')";

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
                    SELECT id FROM {$wpdb->prefix}wc_orders WHERE status {$status_in_clause}
                )
            GROUP BY
                t.term_id, t.name
            ORDER BY
                total_sales DESC
            LIMIT %d",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59',
            absint($limit)
        ));

        return new WP_REST_Response($results, 200);
    }
}
