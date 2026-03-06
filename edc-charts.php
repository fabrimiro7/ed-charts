<?php
/**
 * Plugin Name: EDC Charts (Google Sheets CSV)
 * Description: Create interactive charts from Google Sheets published as CSV. Shortcode: [edc_chart id="123"].
 * Version: 0.2.6
 * Author: Fabrizio Miranda (EfestoDev)
 * Author URI: https://efestodev.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: edc-charts
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Tested up to: 6.9.1
 */

if (!defined('ABSPATH')) exit;

/**
 * EDC Charts – Plugin bootstrap.
 *
 * Defines constants, loads includes, registers hooks for i18n, CPT, admin metaboxes,
 * REST routes, and the [edc_chart] shortcode. Enqueues admin assets on chart edit screen
 * and registers (but does not enqueue) frontend assets for shortcode output.
 */

define('EDC_CHARTS_VERSION', '0.2.0');
define('EDC_CHARTS_DIR', plugin_dir_path(__FILE__));
define('EDC_CHARTS_URL', plugin_dir_url(__FILE__));

require_once EDC_CHARTS_DIR . 'includes/helpers.php';
require_once EDC_CHARTS_DIR . 'includes/cpt.php';
require_once EDC_CHARTS_DIR . 'includes/admin.php';
require_once EDC_CHARTS_DIR . 'includes/rest.php';

add_action('plugins_loaded', function () {
  load_plugin_textdomain(
    'edc-charts',
    false,
    dirname(plugin_basename(__FILE__)) . '/languages'
  );
  edc_register_admin_metaboxes();
  edc_register_rest_routes();
});

add_action('init', 'edc_register_chart_cpt');

add_action('admin_enqueue_scripts', function ($hook) {
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->post_type !== 'edc_chart' || $screen->base !== 'post') {
    return;
  }

  wp_enqueue_media();

  wp_enqueue_style(
    'edc-charts-admin',
    EDC_CHARTS_URL . 'assets/css/edc-charts-admin.css',
    [],
    EDC_CHARTS_VERSION
  );

  wp_enqueue_script(
    'edc-charts-admin',
    EDC_CHARTS_URL . 'assets/js/edc-charts-admin.js',
    [],
    EDC_CHARTS_VERSION,
    true
  );

  $admin_data = [
    'selectOrUploadCsv' => __('Select or upload CSV', 'edc-charts'),
    'useThisFile'       => __('Use this file', 'edc-charts'),
    'fileSelected'      => __('File selected', 'edc-charts'),
    'remove'            => __('Remove', 'edc-charts'),
    'removeColor'       => __('Remove color', 'edc-charts'),
    'chooseColor'       => __('Choose color', 'edc-charts'),
    'attachmentFileName' => '',
  ];

  $post_id = isset($GLOBALS['post']) ? (int) $GLOBALS['post']->ID : 0;
  if ($post_id > 0) {
    $meta = edc_get_chart_meta($post_id);
    $aid  = !empty($meta['csv_attachment_id']) ? (int) $meta['csv_attachment_id'] : 0;
    if ($aid > 0) {
      $fn = get_the_title($aid);
      if (!$fn) {
        $fn = basename(get_attached_file($aid));
      }
      if ($fn) {
        $admin_data['attachmentFileName'] = $fn;
      }
    }
  }

  wp_localize_script('edc-charts-admin', 'EDC_CHARTS_ADMIN', $admin_data);
});

/**
 * Register and localize frontend chart assets (scripts/styles).
 *
 * Registers ECharts, edc-charts.js and edc-charts.css. Localizes REST base URL and
 * nonce for wp-api. Does not enqueue; enqueue happens when shortcode is rendered.
 *
 * @return void
 */
function edc_enqueue_frontend_assets() {
  wp_register_script(
    'edc-echarts',
    EDC_CHARTS_URL . 'assets/js/echarts.min.js',
    [],
    EDC_CHARTS_VERSION,
    true
  );

  wp_register_script(
    'edc-charts',
    EDC_CHARTS_URL . 'assets/js/edc-charts.js',
    ['edc-echarts'],
    EDC_CHARTS_VERSION,
    true
  );

  wp_register_style(
    'edc-charts',
    EDC_CHARTS_URL . 'assets/css/edc-charts.css',
    [],
    EDC_CHARTS_VERSION
  );

  wp_localize_script('edc-charts', 'EDC_CHARTS', [
    'restBase' => esc_url_raw(rest_url('edc/v1')),
    'nonce' => wp_create_nonce('wp_rest'),
  ]);
}

/**
 * Print critical inline CSS for table/tab display so it applies even when theme/Elementor overrides.
 * Called once per page when shortcode is used; ensures table elements render as table and tabs work.
 */
function edc_print_inline_table_styles() {
  static $printed = false;
  if ($printed) return;
  $printed = true;

  $css = '
  .edc-chart-wrap .edc-chart-canvas .edc-chart-table,
  .edc-chart-wrap .edc-chart-canvas .edc-tabs-year-panel .edc-chart-table { display: table !important; width: 100% !important; border-collapse: collapse; }
  .edc-chart-wrap .edc-chart-canvas .edc-chart-table thead,
  .edc-chart-wrap .edc-chart-canvas .edc-tabs-year-panel .edc-chart-table thead { display: table-header-group !important; }
  .edc-chart-wrap .edc-chart-canvas .edc-chart-table tbody,
  .edc-chart-wrap .edc-chart-canvas .edc-tabs-year-panel .edc-chart-table tbody { display: table-row-group !important; }
  .edc-chart-wrap .edc-chart-canvas .edc-chart-table tr,
  .edc-chart-wrap .edc-chart-canvas .edc-tabs-year-panel .edc-chart-table tr { display: table-row !important; }
  .edc-chart-wrap .edc-chart-canvas .edc-chart-table th,
  .edc-chart-wrap .edc-chart-canvas .edc-chart-table td,
  .edc-chart-wrap .edc-chart-canvas .edc-tabs-year-panel .edc-chart-table th,
  .edc-chart-wrap .edc-chart-canvas .edc-tabs-year-panel .edc-chart-table td { display: table-cell !important; padding: 8px 12px; border: 1px solid #d1d5db; }
  .edc-chart-wrap .edc-chart-canvas .edc-tabs-year-panel[aria-hidden="true"] { display: none !important; }
  .edc-chart-wrap .edc-chart-canvas .edc-tabs-year-tabs { display: flex !important; gap: 10px; flex-wrap: wrap; }
  ';
  wp_add_inline_style('edc-charts', trim($css));
}

/**
 * Return critical inline CSS as a style block for table/tab (output in shortcode HTML).
 * Ensures styles apply when the shortcode is inside Elementor AJAX or iframe where enqueued CSS may not load.
 *
 * @return string HTML style block (empty if already printed once).
 */
function edc_get_critical_table_css() {
  static $done = false;
  if ($done) return '';
  $done = true;

  $css = '.edc-chart-wrap .edc-chart-canvas .edc-chart-table,.edc-chart-wrap .edc-chart-canvas .edc-tabs-year-panel .edc-chart-table{display:table!important;width:100%!important;border-collapse:collapse}
.edc-chart-wrap .edc-chart-canvas .edc-chart-table thead,.edc-chart-wrap .edc-chart-canvas .edc-tabs-year-panel .edc-chart-table thead{display:table-header-group!important}
.edc-chart-wrap .edc-chart-canvas .edc-chart-table tbody,.edc-chart-wrap .edc-chart-canvas .edc-tabs-year-panel .edc-chart-table tbody{display:table-row-group!important}
.edc-chart-wrap .edc-chart-canvas .edc-chart-table tr,.edc-chart-wrap .edc-chart-canvas .edc-tabs-year-panel .edc-chart-table tr{display:table-row!important}
.edc-chart-wrap .edc-chart-canvas .edc-chart-table th,.edc-chart-wrap .edc-chart-canvas .edc-chart-table td,.edc-chart-wrap .edc-chart-canvas .edc-tabs-year-panel .edc-chart-table th,.edc-chart-wrap .edc-chart-canvas .edc-tabs-year-panel .edc-chart-table td{display:table-cell!important;padding:8px 12px;border:1px solid #d1d5db}
.edc-chart-wrap .edc-chart-canvas .edc-tabs-year-panel[aria-hidden=true]{display:none!important}
.edc-chart-wrap .edc-chart-canvas .edc-tabs-year-tabs{display:flex!important;gap:10px;flex-wrap:wrap}';

  return '<style id="edc-charts-critical" type="text/css">' . $css . '</style>';
}

/**
 * Shortcode callback: [edc_chart id="123" height="380"].
 *
 * Renders a chart container and error placeholder. Enqueues frontend assets.
 * Invalid or missing id returns an HTML comment for debugging.
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML markup or HTML comment on error.
 */
function edc_chart_shortcode($atts) {
  $atts = shortcode_atts([
    'id' => 0,
    'height' => 420,
  ], $atts);

  $chart_id = intval($atts['id']);
  if ($chart_id <= 0) return '<!-- edc_chart: missing id -->';

  $post = get_post($chart_id);
  if (!$post || $post->post_type !== 'edc_chart') {
    return '<!-- edc_chart: invalid id -->';
  }

  $meta = edc_get_chart_meta($chart_id);

  edc_enqueue_frontend_assets();
  wp_enqueue_script('edc-echarts');
  wp_enqueue_script('edc-charts');
  wp_enqueue_style('edc-charts');
  edc_print_inline_table_styles();

  $container_id = 'edc-chart-' . $chart_id . '-' . wp_generate_uuid4();
  $height = max(220, intval($atts['height']));

  $caption_below_x = trim((string) $meta['caption_below_x']);
  $caption_left_y = trim((string) $meta['caption_left_y']);

  $html = '<div class="edc-chart-wrap">';
  $html .= edc_get_critical_table_css();
  $html .= '<div class="edc-chart-layout">';

  if ($caption_left_y !== '') {
    $html .= '<div class="edc-chart-caption-y">' . wp_kses_post($caption_left_y) . '</div>';
  }

  $html .= '<div id="' . esc_attr($container_id) . '" class="edc-chart-canvas" ';
  $html .= 'style="height:' . esc_attr($height) . 'px" ';
  $html .= 'data-edc-chart-id="' . esc_attr($chart_id) . '"></div>';

  if ($caption_below_x !== '') {
    $html .= '<div class="edc-chart-caption-x">' . wp_kses_post($caption_below_x) . '</div>';
  }

  $html .= '</div>';
  $html .= '<div class="edc-chart-error" data-edc-error-for="' . esc_attr($container_id) . '" style="display:none;"></div>';
  $html .= '</div>';

  return $html;
}

add_shortcode('edc_chart', 'edc_chart_shortcode');

register_activation_hook(__FILE__, function () {
  edc_register_chart_cpt();
});