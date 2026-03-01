<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

/**
 * EDC Charts – Uninstall cleanup.
 *
 * Removes all edc_chart post meta and chart data transients when the plugin is deleted.
 */

// Remove all plugin meta and transients
$charts = get_posts([
  'post_type' => 'edc_chart',
  'numberposts' => -1,
  'post_status' => 'any',
  'fields' => 'ids',
]);

if ($charts) {
  foreach ($charts as $id) {
    delete_post_meta($id, 'edc_data_source');
    delete_post_meta($id, 'edc_csv_url');
    delete_post_meta($id, 'edc_csv_attachment_id');
    delete_post_meta($id, 'edc_chart_type');
    delete_post_meta($id, 'edc_has_header');
    delete_post_meta($id, 'edc_delimiter');
    delete_post_meta($id, 'edc_x_col');
    delete_post_meta($id, 'edc_series_cols');
    delete_post_meta($id, 'edc_cache_minutes');
    delete_post_meta($id, 'edc_caption_below_x');
    delete_post_meta($id, 'edc_caption_left_y');
    delete_post_meta($id, 'edc_bar_colors');
    delete_post_meta($id, 'edc_y_axis_fit_data');

    delete_transient('edc_chart_data_' . $id);
  }
}