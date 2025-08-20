<?php
// includes/api/controllers/class-aorw-products-controller.php
if (! defined('ABSPATH')) exit;

class AORW_Products_Controller
{

    public function get_bestsellers_report($request)
    {
        global $wpdb;

        $start_date = $request->get_param('start_date');
        $end_date   = $request->get_param('end_date');
        $limit      = $request->get_param('limit') ?? 20;

        $valid_order_statuses = array('wc-processing', 'wc-completed');

        // ✅ THE FIX: Manually create the IN clause for the statuses.
        // This is safe because we are defining the statuses ourselves.
        $status_in_clause = "IN ('" . implode("', '", $valid_order_statuses) . "')";

        // The query is now much simpler and directly compatible with wpdb->prepare.
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
                    SELECT id FROM {$wpdb->prefix}wc_orders WHERE status {$status_in_clause}
                )
            GROUP BY
                p.ID, p.post_title
            ORDER BY
                total_sales DESC
            LIMIT %d",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59',
            absint($limit)
        ));

        $products_data = [];
        foreach ($results as $product) {
            $product_obj = wc_get_product($product->product_id);
            if (! $product_obj) continue;
            $products_data[] = [
                'product_id'   => $product->product_id,
                'product_name' => $product->product_name,
                'image_url'    => wp_get_attachment_image_url($product_obj->get_image_id(), 'thumbnail'),
                'sku'          => $product->sku ?? 'N/A',
                'units_sold'   => (int) $product->units_sold,
                'total_sales'  => (float) $product->total_sales,
                'edit_url'     => get_edit_post_link($product->product_id, 'raw'),
            ];
        }

        return new WP_REST_Response($products_data, 200);
    }
}
