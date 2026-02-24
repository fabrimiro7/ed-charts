<?php
if (!defined('ABSPATH')) exit;

/**
 * EDC Charts – Custom post type registration.
 *
 * Registers the edc_chart post type (not public, UI only).
 */

/**
 * Register the edc_chart custom post type and its labels.
 *
 * @return void
 */
function edc_register_chart_cpt() {
  $labels = [
    'name' => __('EDC Charts', 'edc-charts'),
    'singular_name' => __('EDC Chart', 'edc-charts'),
    'add_new' => __('Add New', 'edc-charts'),
    'add_new_item' => __('Add New Chart', 'edc-charts'),
    'edit_item' => __('Edit Chart', 'edc-charts'),
    'new_item' => __('New Chart', 'edc-charts'),
    'view_item' => __('View Chart', 'edc-charts'),
    'search_items' => __('Search Charts', 'edc-charts'),
    'not_found' => __('No charts found', 'edc-charts'),
    'not_found_in_trash' => __('No charts found in Trash', 'edc-charts'),
    'menu_name' => __('EDC Charts', 'edc-charts'),
  ];

  register_post_type('edc_chart', [
    'labels' => $labels,
    'public' => false,
    'show_ui' => true,
    'show_in_menu' => true,
    'menu_icon' => 'dashicons-chart-area',
    'supports' => ['title'],
    'has_archive' => false,
    'rewrite' => false,
  ]);
}