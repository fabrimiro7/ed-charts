<?php
if (!defined('ABSPATH')) exit;

/**
 * EDC Charts – REST API for chart data.
 *
 * Registers GET edc/v1/chart/{id} to return parsed chart data (labels, datasets, chartType, meta).
 * Supports optional refresh=1 for users with edit_posts to bypass cache.
 */

/**
 * Register REST route GET edc/v1/chart/{id} with optional refresh query arg.
 *
 * @return void
 */
function edc_register_rest_routes() {
  add_action('rest_api_init', function () {
    register_rest_route('edc/v1', '/chart/(?P<id>\d+)', [
      'methods' => 'GET',
      'callback' => 'edc_rest_get_chart',
      'permission_callback' => '__return_true',
      'args' => [
        'id' => [
          'validate_callback' => function ($param) {
            return is_numeric($param) && intval($param) > 0;
          }
        ],
        'refresh' => [
          'validate_callback' => function ($param) {
            return in_array((string)$param, ['0','1'], true);
          },
          'required' => false
        ],
      ],
    ]);
  });
}

/**
 * REST callback: return chart data for the given ID, or 404 if not found/not published.
 *
 * If the user can edit_posts and refresh=1 is passed, cache is bypassed.
 * Sensitive meta keys (csv_url, csv_attachment_id, data_source, cache_minutes) are stripped from response.
 *
 * @param \WP_REST_Request $request Request with route param 'id' and optional 'refresh'.
 * @return \WP_REST_Response
 */
function edc_rest_get_chart(\WP_REST_Request $request) {
  $id = intval($request['id']);

  $refresh = false;
  if (current_user_can('edit_posts') && (string) ($request->get_param('refresh') ?? '0') === '1') {
    $refresh = true;
  }

  $post = get_post($id);
  if (!$post || $post->post_type !== 'edc_chart' || $post->post_status !== 'publish') {
    return new \WP_REST_Response([
      'error' => true,
      'message' => __('Chart not found.', 'edc-charts'),
    ], 404);
  }

  $data = edc_fetch_and_parse_chart_data($id, $refresh);

  unset($data['meta']['csv_url'], $data['meta']['csv_attachment_id'], $data['meta']['data_source'], $data['meta']['cache_minutes']);

  return new \WP_REST_Response($data, 200);
}