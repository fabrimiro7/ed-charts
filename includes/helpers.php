<?php
if (!defined('ABSPATH')) exit;

/**
 * EDC Charts – Data fetching, CSV parsing, caching, number normalization.
 *
 * Fetches CSV from URL (e.g. Google Sheets) or uploaded attachment, parses to labels/datasets,
 * caches via transients, and normalizes numeric strings (e.g. Italian decimals).
 */

/**
 * Get chart post meta with defaults and sanitization.
 *
 * @param int $post_id Chart post ID.
 * @return array<string, string> Keys: data_source, csv_url, csv_attachment_id, chart_type,
 *   has_header, delimiter, x_col, series_cols, cache_minutes, caption_below_x, caption_left_y, bar_colors.
 */
function edc_get_chart_meta(int $post_id): array {
  $defaults = [
    'data_source' => 'url',       // 'url' | 'upload'
    'csv_url' => '',
    'csv_attachment_id' => '',    // attachment ID when data_source = upload
    'chart_type' => 'line',       // line | bar
    'has_header' => '1',          // "1" | "0"
    'delimiter' => ',',           // ',' or ';'
    'x_col' => '0',              // index as string
    'series_cols' => '1',         // comma-separated indices: "1,2,3"
    'cache_minutes' => '60',
    'caption_below_x' => '',      // testo sotto l'asse x
    'caption_left_y' => '',       // testo laterale all'asse y
    'bar_colors' => '',           // colori barre (es. #1e3a5f oppure #1e3a5f,#2563eb)
    'y_axis_fit_data' => '0',     // "1" | "0" scala Y adattata ai dati
    'y_axis_min' => '',           // valore minimo asse Y (vuoto = calcolato dai dati)
    'y_axis_max' => '',           // valore massimo asse Y (vuoto = calcolato dai dati)
  ];

  $out = [];
  foreach ($defaults as $k => $v) {
    $out[$k] = (string) get_post_meta($post_id, 'edc_' . $k, true);
    if ($out[$k] === '') $out[$k] = $v;
  }

  // sanitize post-meta format lightly
  $out['data_source'] = in_array($out['data_source'], ['url', 'upload'], true) ? $out['data_source'] : 'url';
  $aid = absint($out['csv_attachment_id']);
  $out['csv_attachment_id'] = $aid > 0 ? (string) $aid : '';
  $out['chart_type'] = in_array($out['chart_type'], ['line', 'bar'], true) ? $out['chart_type'] : 'line';
  $out['has_header'] = ($out['has_header'] === '0') ? '0' : '1';
  $out['delimiter']  = ($out['delimiter'] === ';') ? ';' : ',';

  $x = intval($out['x_col']);
  $out['x_col'] = (string) max(0, $x);

  $cache = intval($out['cache_minutes']);
  $out['cache_minutes'] = (string) max(1, $cache);

  // series cols normalization
  $series = array_filter(array_map('trim', explode(',', $out['series_cols'])));
  $series = array_values(array_unique(array_map(function ($s) {
    return (string) max(0, intval($s));
  }, $series)));
  if (empty($series)) $series = ['1'];
  $out['series_cols'] = implode(',', $series);

  return $out;
}

/**
 * Transient key used to cache chart data for a given chart ID.
 *
 * @param int $chart_id Chart post ID.
 * @return string Transient key.
 */
function edc_transient_key(int $chart_id): string {
  return 'edc_chart_data_' . $chart_id;
}

/**
 * Fetch CSV (from URL or upload), parse to datasets, and optionally cache.
 *
 * On success returns: labels, datasets (label + data), chartType, meta.
 * On error returns: error true, message, empty labels/datasets, chartType, meta.
 *
 * @param int  $chart_id     Chart post ID.
 * @param bool $force_refresh If true, bypass cache and refetch.
 * @return array{labels: array, datasets: array, chartType: string, meta: array} | array{error: true, message: string, labels: array, datasets: array, chartType: string, meta: array}
 */
function edc_fetch_and_parse_chart_data(int $chart_id, bool $force_refresh = false): array {
  $meta = edc_get_chart_meta($chart_id);

  $cache_key = edc_transient_key($chart_id);
  if (!$force_refresh) {
    $cached = get_transient($cache_key);
    if (is_array($cached)) return $cached;
  }

  $body = null;
  $data_source = isset($meta['data_source']) ? $meta['data_source'] : 'url';

  if ($data_source === 'upload') {
    $attachment_id = !empty($meta['csv_attachment_id']) ? (int) $meta['csv_attachment_id'] : 0;
    if ($attachment_id <= 0) {
      return edc_error_payload(__('No CSV file uploaded. Upload a CSV file in the chart settings.', 'edc-charts'), $meta);
    }
    $path = get_attached_file($attachment_id);
    if (!$path || !is_readable($path)) {
      return edc_error_payload(__('CSV file not found or not readable.', 'edc-charts'), $meta);
    }
    $allowed_mimes = ['text/csv', 'application/csv', 'text/plain', 'application/octet-stream'];
    $file_type = wp_check_filetype($path);
    $mime = $file_type['type'];
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($mime, $allowed_mimes, true) && $ext !== 'csv') {
      return edc_error_payload(__('The selected file is not a valid CSV.', 'edc-charts'), $meta);
    }
    $body = file_get_contents($path);
    if ($body === false || trim($body) === '') {
      return edc_error_payload(__('The CSV file is empty.', 'edc-charts'), $meta);
    }
  } else {
    $csv_url = trim($meta['csv_url']);
    if ($csv_url === '') {
      return edc_error_payload(__('Missing CSV URL. Set it in the chart settings.', 'edc-charts'), $meta);
    }

    $response = wp_safe_remote_get($csv_url, [
      'timeout' => 15,
      'redirection' => 5,
      'headers' => [
        'Accept' => 'text/csv,text/plain,*/*',
      ],
    ]);

    if (is_wp_error($response)) {
      return edc_error_payload(sprintf(__('Failed to fetch CSV: %s', 'edc-charts'), $response->get_error_message()), $meta);
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
      return edc_error_payload(sprintf(__('CSV request failed with HTTP %s', 'edc-charts'), $code), $meta);
    }

    $body = wp_remote_retrieve_body($response);
    if (!is_string($body) || trim($body) === '') {
      return edc_error_payload(__('CSV response is empty.', 'edc-charts'), $meta);
    }
  }

  $delimiter = $meta['delimiter'];
  $has_header = $meta['has_header'] === '1';
  $x_col = intval($meta['x_col']);
  $series_cols = array_map('intval', array_filter(array_map('trim', explode(',', $meta['series_cols']))));

  $lines = preg_split("/\r\n|\n|\r/", $body);
  $rows = [];
  foreach ($lines as $line) {
    if (trim($line) === '') continue;
    // str_getcsv handles quotes
    $rows[] = str_getcsv($line, $delimiter);
  }

  if (count($rows) < 1) {
    return edc_error_payload(__('CSV contains no rows.', 'edc-charts'), $meta);
  }

  $header = [];
  if ($has_header) {
    $header = array_shift($rows);
  }

  $labels = [];
  $series_data = [];
  foreach ($series_cols as $c) {
    $series_data[$c] = [];
  }

  foreach ($rows as $r) {
    if (!is_array($r)) continue;

    $labels[] = isset($r[$x_col]) ? (string) $r[$x_col] : '';

    foreach ($series_cols as $c) {
      $raw = isset($r[$c]) ? (string) $r[$c] : '';

      // Normalize Italian decimals "1.234,56" -> "1234.56"
      $normalized = edc_normalize_number($raw);
      $series_data[$c][] = $normalized;
    }
  }

  $datasets = [];
  foreach ($series_cols as $c) {
    $label = $has_header && isset($header[$c]) && trim((string)$header[$c]) !== ''
      ? (string) $header[$c]
      : sprintf(__('Series %s', 'edc-charts'), $c);

    $datasets[] = [
      'label' => $label,
      'data' => $series_data[$c],
    ];
  }

  $payload = [
    'labels' => $labels,
    'datasets' => $datasets,
    'chartType' => $meta['chart_type'],
    'meta' => $meta,
  ];

  $ttl = max(60, intval($meta['cache_minutes']) * 60);
  set_transient($cache_key, $payload, $ttl);

  return $payload;
}

/**
 * Normalize numeric string (e.g. Italian-style "1.234,56" to 1234.56).
 *
 * Handles thousand/decimal separators and strips non-numeric characters.
 *
 * @param string $value Raw value from CSV.
 * @return float|null Parsed number or null if empty/invalid.
 */
function edc_normalize_number(string $value) {
  $v = trim($value);
  if ($v === '') return null;

  // remove spaces
  $v = str_replace(["\u{00A0}", ' '], '', $v);

  // If contains both '.' and ',', assume '.' is thousand separator and ',' decimal
  if (strpos($v, '.') !== false && strpos($v, ',') !== false) {
    $v = str_replace('.', '', $v);
    $v = str_replace(',', '.', $v);
  } else {
    // If only ',', treat as decimal separator
    if (strpos($v, ',') !== false) {
      $v = str_replace(',', '.', $v);
    }
  }

  // remove anything not number-ish
  $v = preg_replace('/[^0-9\.\-]/', '', $v);

  if ($v === '' || $v === '-' || $v === '.' || $v === '-.' ) return null;

  $num = floatval($v);
  return $num;
}

/**
 * Build a standardized error payload for chart data responses.
 *
 * @param string       $message Error message (translated).
 * @param array $meta   Chart meta to include.
 * @return array{error: true, message: string, labels: array, datasets: array, chartType: string, meta: array}
 */
function edc_error_payload(string $message, array $meta = []): array {
  return [
    'error' => true,
    'message' => $message,
    'labels' => [],
    'datasets' => [],
    'chartType' => $meta['chart_type'] ?? 'line',
    'meta' => $meta,
  ];
}