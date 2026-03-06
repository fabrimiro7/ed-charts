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
 *   has_header, delimiter, x_col, series_cols, cache_minutes, caption_below_x, caption_left_y, bar_colors, value_prefix, value_suffix, line_area_fill.
 */
function edc_get_chart_meta(int $post_id): array {
  $defaults = [
    'data_source' => 'url',       // 'url' | 'upload'
    'csv_url' => '',
    'csv_attachment_id' => '',    // attachment ID when data_source = upload
    'chart_type' => 'line',       // line | bar | table | table_tabs_year | table_tabs_year_unified_date
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
    'line_smooth' => '1',         // "1" | "0" linee curve (smooth) o squadrate
    'line_area_fill' => '0',     // "1" | "0" riempimento area sotto le linee
    'value_prefix' => '',         // prefisso opzionale per valori (es. €)
    'value_suffix' => '',         // suffisso opzionale per valori (es. %)
    'table_title' => '',          // titolo sopra la tabella (tipo table)
    'year_col' => '0',            // indice colonna anno (tipo table_tabs_year)
    'month_col' => '1',            // indice colonna mese
    'value_col' => '2',            // indice colonna valore
    'date_col' => '0',            // indice colonna data unificata (tipo table_tabs_year_unified_date)
    'value_column_label' => '',   // etichetta colonna valore (es. Valore quota (€))
    'table_header_color' => '',    // colore intestazioni tabelle (hex, es. #2e7d5e)
    'tab_button_color' => '',      // colore pulsanti tab attivi (hex)
    'date_range_start' => '',      // filtro range: data inizio (Y-m-d), vuoto = nessun filtro
    'date_range_end' => '',        // filtro range: data fine (Y-m-d), opzionale
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
  $out['chart_type'] = in_array($out['chart_type'], ['line', 'bar', 'table', 'table_tabs_year', 'table_tabs_year_unified_date'], true) ? $out['chart_type'] : 'line';
  $out['has_header'] = ($out['has_header'] === '0') ? '0' : '1';
  $out['delimiter']  = ($out['delimiter'] === ';') ? ';' : ',';
  $out['line_smooth'] = ($out['line_smooth'] === '0') ? '0' : '1';
  $out['line_area_fill'] = ($out['line_area_fill'] === '1') ? '1' : '0';

  $x = intval($out['x_col']);
  $out['x_col'] = (string) max(0, $x);

  $out['year_col'] = (string) max(0, intval($out['year_col']));
  $out['month_col'] = (string) max(0, intval($out['month_col']));
  $out['value_col'] = (string) max(0, intval($out['value_col']));
  $out['date_col'] = (string) max(0, intval($out['date_col']));

  $out['table_header_color'] = edc_sanitize_hex_color($out['table_header_color']);
  $out['tab_button_color'] = edc_sanitize_hex_color($out['tab_button_color']);

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
 * Sanitize hex color string (#RGB or #RRGGBB). Returns empty string if invalid.
 *
 * @param string $color
 * @return string
 */
function edc_sanitize_hex_color(string $color): string {
  $color = trim($color);
  if ($color === '') return '';
  if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
    return strtolower($color);
  }
  return '';
}

/**
 * Return Italian month name (1–12). 0 or invalid returns empty string.
 *
 * @param int $month_num Month number 1–12.
 * @return string
 */
function edc_month_name_it(int $month_num): string {
  $months = [
    1 => __('Gennaio', 'edc-charts'),
    2 => __('Febbraio', 'edc-charts'),
    3 => __('Marzo', 'edc-charts'),
    4 => __('Aprile', 'edc-charts'),
    5 => __('Maggio', 'edc-charts'),
    6 => __('Giugno', 'edc-charts'),
    7 => __('Luglio', 'edc-charts'),
    8 => __('Agosto', 'edc-charts'),
    9 => __('Settembre', 'edc-charts'),
    10 => __('Ottobre', 'edc-charts'),
    11 => __('Novembre', 'edc-charts'),
    12 => __('Dicembre', 'edc-charts'),
  ];
  return isset($months[$month_num]) ? $months[$month_num] : '';
}

/**
 * Parse month string to number 1–12. Accepts numeric "1"-"12" or Italian month names (case-insensitive).
 *
 * @param string $month_str Raw month from CSV (e.g. "3", "Marzo").
 * @return int 1–12 or 0 if invalid.
 */
function edc_month_to_num(string $month_str): int {
  $s = trim($month_str);
  if ($s === '') return 0;
  if (is_numeric($s)) {
    $n = (int) $s;
    return ($n >= 1 && $n <= 12) ? $n : 0;
  }
  $names = [
    'gennaio' => 1, 'febbraio' => 2, 'marzo' => 3, 'aprile' => 4, 'maggio' => 5, 'giugno' => 6,
    'luglio' => 7, 'agosto' => 8, 'settembre' => 9, 'ottobre' => 10, 'novembre' => 11, 'dicembre' => 12,
  ];
  $key = strtolower($s);
  return isset($names[$key]) ? $names[$key] : 0;
}

/**
 * Check if a row timestamp falls within the optional date range.
 * If range_start is empty, no filter (returns true). Otherwise row_ts must be >= start; if range_end is set, row_ts must be <= end (end of day).
 *
 * @param int|null $row_ts Unix timestamp of the row date (or null = exclude).
 * @param string   $range_start Y-m-d or empty.
 * @param string   $range_end   Y-m-d or empty.
 * @return bool
 */
function edc_row_in_date_range(?int $row_ts, string $range_start, string $range_end): bool {
  $range_start = trim($range_start);
  if ($range_start === '') return true;
  if ($row_ts === null) return false;
  $start_ts = strtotime($range_start . ' 00:00:00');
  if ($start_ts === false) return true;
  if ($row_ts < $start_ts) return false;
  $range_end = trim($range_end);
  if ($range_end === '') return true;
  $end_ts = strtotime($range_end . ' 23:59:59');
  if ($end_ts === false) return true;
  return $row_ts <= $end_ts;
}

/**
 * Parse a date string to timestamp. Tries strtotime() then d/m/Y and Y-m-d.
 *
 * @param string $date_str Raw date from CSV.
 * @return int|null Unix timestamp or null if unparseable.
 */
function edc_parse_date_unified(string $date_str): ?int {
  $s = trim($date_str);
  if ($s === '') return null;
  $ts = strtotime($s);
  if ($ts !== false) return $ts;
  // Try d/m/Y and Y-m-d
  if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $s, $m)) {
    $ts = mktime(0, 0, 0, (int) $m[2], (int) $m[1], (int) $m[3]);
    return $ts !== false ? $ts : null;
  }
  if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m)) {
    $ts = mktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]);
    return $ts !== false ? $ts : null;
  }
  return null;
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

  $chart_type = $meta['chart_type'];

  if ($chart_type === 'table') {
    $table_col_indices = array_merge([$x_col], $series_cols);
    $value_prefix = isset($meta['value_prefix']) ? (string) $meta['value_prefix'] : '';
    $value_suffix = isset($meta['value_suffix']) ? (string) $meta['value_suffix'] : '';

    $out_header = [];
    if ($has_header && is_array($header)) {
      foreach ($table_col_indices as $idx) {
        $out_header[] = isset($header[$idx]) ? (string) $header[$idx] : '';
      }
    } else {
      $out_header = array_fill(0, count($table_col_indices), '');
    }

    $out_rows = [];
    foreach ($rows as $r) {
      if (!is_array($r)) continue;

      // Skip row if all value columns (series_cols) are null or empty
      $has_any_value = false;
      foreach ($series_cols as $vidx) {
        $raw = isset($r[$vidx]) ? trim((string) $r[$vidx]) : '';
        if ($raw !== '') {
          $has_any_value = true;
          break;
        }
      }
      if (!$has_any_value) continue;

      $out_row = [];
      foreach ($table_col_indices as $idx) {
        $cell = isset($r[$idx]) ? (string) $r[$idx] : '';
        if (in_array($idx, $series_cols, true)) {
          $trimmed = trim($cell);
          if ($trimmed !== '') {
            $norm = edc_normalize_number($trimmed);
            $display = $trimmed;
            if ($norm !== null && is_finite($norm)) {
              $display = number_format($norm, 3, ',', '.');
              $display = preg_replace('/0+$/', '', $display);
              $display = rtrim($display, ',');
            }
            $cell = $value_prefix . $display . $value_suffix;
          }
        }
        $out_row[] = $cell;
      }
      $out_rows[] = $out_row;
    }

    $payload = [
      'chartType' => 'table',
      'header' => $out_header,
      'rows' => $out_rows,
      'labels' => [],
      'datasets' => [],
      'meta' => $meta,
    ];
    $ttl = max(60, intval($meta['cache_minutes']) * 60);
    set_transient($cache_key, $payload, $ttl);
    return $payload;
  }

  if ($chart_type === 'table_tabs_year') {
    $year_col = max(0, intval($meta['year_col']));
    $month_col = max(0, intval($meta['month_col']));
    $value_col = max(0, intval($meta['value_col']));
    $value_label = trim((string) $meta['value_column_label']);
    if ($value_label === '' && $has_header && isset($header[$value_col])) {
      $value_label = trim((string) $header[$value_col]);
    }
    if ($value_label === '') {
      $value_label = __('Valore', 'edc-charts');
    }

    $range_start = trim((string) ($meta['date_range_start'] ?? ''));
    $range_end = trim((string) ($meta['date_range_end'] ?? ''));

    $dataByYear = [];
    $years_set = [];
    foreach ($rows as $r) {
      if (!is_array($r)) continue;
      $year_raw = isset($r[$year_col]) ? trim((string) $r[$year_col]) : '';
      $year = is_numeric($year_raw) ? (string) intval($year_raw) : $year_raw;
      if ($year === '' || !is_numeric($year)) continue;
      $year_int = (int) $year;
      $month_str = isset($r[$month_col]) ? (string) $r[$month_col] : '';
      $month_num = edc_month_to_num($month_str);
      if ($month_num === 0) continue;
      $row_ts = mktime(0, 0, 0, $month_num, 1, $year_int);
      if ($row_ts === false || !edc_row_in_date_range($row_ts, $range_start, $range_end)) continue;
      $month = edc_month_name_it($month_num);
      $raw_val = isset($r[$value_col]) ? (string) $r[$value_col] : '';
      $norm = edc_normalize_number($raw_val);
      $value = $norm !== null ? $norm : $raw_val;
      if (!isset($dataByYear[$year])) {
        $dataByYear[$year] = [];
        $years_set[$year] = true;
      }
      $dataByYear[$year][] = [ 'month' => $month, 'value' => $value ];
    }
    $years = array_keys($years_set);
    rsort($years, SORT_NUMERIC);

    $payload = [
      'chartType' => 'table_tabs_year',
      'years' => array_values($years),
      'dataByYear' => $dataByYear,
      'labels' => [],
      'datasets' => [],
      'meta' => array_merge($meta, [ 'value_column_label' => $value_label ]),
    ];
    $ttl = max(60, intval($meta['cache_minutes']) * 60);
    set_transient($cache_key, $payload, $ttl);
    return $payload;
  }

  if ($chart_type === 'table_tabs_year_unified_date') {
    $date_col = max(0, intval($meta['date_col']));
    $value_col = max(0, intval($meta['value_col']));
    $value_label = trim((string) $meta['value_column_label']);
    if ($value_label === '' && $has_header && isset($header[$value_col])) {
      $value_label = trim((string) $header[$value_col]);
    }
    if ($value_label === '') {
      $value_label = __('Valore', 'edc-charts');
    }

    $range_start = trim((string) ($meta['date_range_start'] ?? ''));
    $range_end = trim((string) ($meta['date_range_end'] ?? ''));

    $dataByYear = [];
    $years_set = [];
    foreach ($rows as $r) {
      if (!is_array($r)) continue;
      $date_raw = isset($r[$date_col]) ? trim((string) $r[$date_col]) : '';
      $ts = edc_parse_date_unified($date_raw);
      if ($ts === null) continue;
      if (!edc_row_in_date_range($ts, $range_start, $range_end)) continue;
      $year = (string) (int) date('Y', $ts);
      $month_num = (int) date('n', $ts);
      $month = edc_month_name_it($month_num);
      if ($month === '') continue;
      $raw_val = isset($r[$value_col]) ? (string) $r[$value_col] : '';
      $norm = edc_normalize_number($raw_val);
      $value = $norm !== null ? $norm : $raw_val;
      if (!isset($dataByYear[$year])) {
        $dataByYear[$year] = [];
        $years_set[$year] = true;
      }
      $dataByYear[$year][] = [ 'month' => $month, 'value' => $value ];
    }
    $years = array_keys($years_set);
    rsort($years, SORT_NUMERIC);

    $payload = [
      'chartType' => 'table_tabs_year_unified_date',
      'years' => array_values($years),
      'dataByYear' => $dataByYear,
      'labels' => [],
      'datasets' => [],
      'meta' => array_merge($meta, [ 'value_column_label' => $value_label ]),
    ];
    $ttl = max(60, intval($meta['cache_minutes']) * 60);
    set_transient($cache_key, $payload, $ttl);
    return $payload;
  }

  $labels = [];
  $series_data = [];
  foreach ($series_cols as $c) {
    $series_data[$c] = [];
  }

  $range_start = trim((string) ($meta['date_range_start'] ?? ''));
  $range_end = trim((string) ($meta['date_range_end'] ?? ''));

  foreach ($rows as $r) {
    if (!is_array($r)) continue;

    $x_val = isset($r[$x_col]) ? (string) $r[$x_col] : '';
    if ($range_start !== '') {
      $row_ts = edc_parse_date_unified($x_val);
      if (!edc_row_in_date_range($row_ts, $range_start, $range_end)) continue;
    }

    $labels[] = $x_val !== '' ? $x_val : '';

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