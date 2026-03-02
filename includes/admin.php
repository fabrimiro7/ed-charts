<?php
if (!defined('ABSPATH')) exit;

/**
 * EDC Charts – Admin UI, metaboxes, shortcode column, save logic.
 *
 * Renders the chart settings metabox, adds Shortcode column to the chart list,
 * and saves/validates post meta on save (with cache invalidation).
 */

/**
 * Register metabox, save hook, and Shortcode column for edc_chart.
 *
 * @return void
 */
function edc_register_admin_metaboxes() {
  add_action('add_meta_boxes', function () {
    add_meta_box(
      'edc_chart_settings',
      __('EDC Chart Settings', 'edc-charts'),
      'edc_render_chart_metabox',
      'edc_chart',
      'normal',
      'high'
    );
  });

  add_action('save_post_edc_chart', 'edc_save_chart_metabox', 10, 2);

  // Add a "Shortcode" column in admin list
  add_filter('manage_edc_chart_posts_columns', function ($columns) {
    $columns['edc_shortcode'] = __('Shortcode', 'edc-charts');
    return $columns;
  });

  add_action('manage_edc_chart_posts_custom_column', function ($column, $post_id) {
    if ($column === 'edc_shortcode') {
      echo '<code>[edc_chart id="' . intval($post_id) . '"]</code>';
    }
  }, 10, 2);
}

/**
 * Output the chart settings metabox form (data source, CSV, chart type, columns, captions, colors).
 *
 * @param \WP_Post $post Chart post object.
 * @return void
 */
function edc_render_chart_metabox($post) {
  $meta = edc_get_chart_meta($post->ID);
  wp_nonce_field('edc_chart_save', 'edc_chart_nonce');

  ?>
  <div class="edc-field">
    <label for="edc_data_source"><?php echo esc_html__('Data source', 'edc-charts'); ?></label>
    <select id="edc_data_source" name="edc_data_source">
      <option value="url" <?php selected($meta['data_source'], 'url'); ?>><?php echo esc_html__('Google Sheets URL', 'edc-charts'); ?></option>
      <option value="upload" <?php selected($meta['data_source'], 'upload'); ?>><?php echo esc_html__('Upload CSV file', 'edc-charts'); ?></option>
    </select>
  </div>

  <div class="edc-field edc-source-url" id="edc-field-csv-url" style="<?php echo $meta['data_source'] !== 'url' ? 'display:none;' : ''; ?>">
    <label for="edc_csv_url"><?php echo esc_html__('Google Sheets CSV URL', 'edc-charts'); ?></label>
    <input type="text" id="edc_csv_url" name="edc_csv_url" value="<?php echo esc_attr($meta['csv_url']); ?>" placeholder="https://docs.google.com/spreadsheets/d/e/.../pub?output=csv">
    <div class="edc-help">
      <?php echo esc_html__('In Google Sheets: File → Share → Publish to web → choose sheet → format CSV → publish → paste the link here.', 'edc-charts'); ?>
    </div>
  </div>

  <div class="edc-field edc-source-upload" id="edc-field-csv-upload" style="<?php echo $meta['data_source'] !== 'upload' ? 'display:none;' : ''; ?>">
    <label><?php echo esc_html__('Uploaded CSV file', 'edc-charts'); ?></label>
    <input type="hidden" id="edc_csv_attachment_id" name="edc_csv_attachment_id" value="<?php echo esc_attr($meta['csv_attachment_id']); ?>">
    <div class="edc-upload-row">
      <button type="button" id="edc_csv_upload_btn" class="button"><?php echo esc_html__('Select or upload CSV', 'edc-charts'); ?></button>
      <button type="button" id="edc_csv_remove_btn" class="button" style="display:none;"><?php echo esc_html__('Remove', 'edc-charts'); ?></button>
    </div>
    <div id="edc_csv_file_name" class="edc-help" style="margin-top:6px;"></div>
  </div>

  <div class="edc-field">
    <label for="edc_chart_type"><?php echo esc_html__('Chart type', 'edc-charts'); ?></label>
    <select id="edc_chart_type" name="edc_chart_type">
      <option value="line" <?php selected($meta['chart_type'], 'line'); ?>><?php echo esc_html__('Line', 'edc-charts'); ?></option>
      <option value="bar"  <?php selected($meta['chart_type'], 'bar'); ?>><?php echo esc_html__('Bar', 'edc-charts'); ?></option>
      <option value="table" <?php selected($meta['chart_type'], 'table'); ?>><?php echo esc_html__('Tabella (n colonne)', 'edc-charts'); ?></option>
      <option value="table_tabs_year" <?php selected($meta['chart_type'], 'table_tabs_year'); ?>><?php echo esc_html__('Tab per anno (mese/valore)', 'edc-charts'); ?></option>
    </select>
  </div>

  <div class="edc-field edc-chart-type-table" id="edc-field-table-title" style="<?php echo $meta['chart_type'] !== 'table' ? 'display:none;' : ''; ?>">
    <label for="edc_table_title"><?php echo esc_html__('Titolo tabella', 'edc-charts'); ?></label>
    <input type="text" id="edc_table_title" name="edc_table_title" value="<?php echo esc_attr($meta['table_title']); ?>" placeholder="<?php echo esc_attr__('e.g. Rendimento annuo del comparto', 'edc-charts'); ?>">
    <div class="edc-help"><?php echo esc_html__('Opzionale. Testo mostrato sopra la tabella.', 'edc-charts'); ?></div>
  </div>

  <div class="edc-field edc-chart-type-tabs-year" id="edc-field-tabs-year" style="<?php echo $meta['chart_type'] !== 'table_tabs_year' ? 'display:none;' : ''; ?>">
    <label><?php echo esc_html__('Colonne CSV (0-based)', 'edc-charts'); ?></label>
    <div class="edc-tabs-year-cols">
      <div>
        <label for="edc_year_col"><?php echo esc_html__('Colonna anno', 'edc-charts'); ?></label>
        <input type="number" min="0" id="edc_year_col" name="edc_year_col" value="<?php echo esc_attr($meta['year_col']); ?>">
      </div>
      <div>
        <label for="edc_month_col"><?php echo esc_html__('Colonna mese', 'edc-charts'); ?></label>
        <input type="number" min="0" id="edc_month_col" name="edc_month_col" value="<?php echo esc_attr($meta['month_col']); ?>">
      </div>
      <div>
        <label for="edc_value_col"><?php echo esc_html__('Colonna valore', 'edc-charts'); ?></label>
        <input type="number" min="0" id="edc_value_col" name="edc_value_col" value="<?php echo esc_attr($meta['value_col']); ?>">
      </div>
    </div>
    <label for="edc_value_column_label" style="display:block; margin-top:8px;"><?php echo esc_html__('Etichetta colonna valore', 'edc-charts'); ?></label>
    <input type="text" id="edc_value_column_label" name="edc_value_column_label" value="<?php echo esc_attr($meta['value_column_label']); ?>" placeholder="<?php echo esc_attr__('e.g. Valore quota (€)', 'edc-charts'); ?>">
    <div class="edc-help"><?php echo esc_html__('Opzionale. Intestazione della colonna valore in tabella. Se vuoto viene usato l\'header CSV.', 'edc-charts'); ?></div>
  </div>

  <div class="edc-field">
    <label for="edc_line_smooth">
      <input type="checkbox" id="edc_line_smooth" name="edc_line_smooth" value="1" <?php checked($meta['line_smooth'], '1'); ?>>
      <?php echo esc_html__('Linee curve (smooth)', 'edc-charts'); ?>
    </label>
    <div class="edc-help">
      <?php echo esc_html__('If disabled, lines connect points with straight segments (angular).', 'edc-charts'); ?>
    </div>
  </div>

  <div class="edc-field">
    <label for="edc_y_axis_fit_data">
      <input type="checkbox" id="edc_y_axis_fit_data" name="edc_y_axis_fit_data" value="1" <?php checked($meta['y_axis_fit_data'], '1'); ?>>
      <?php echo esc_html__('Fit Y axis to data range', 'edc-charts'); ?>
    </label>
    <div class="edc-help">
      <?php echo esc_html__('If enabled, the Y axis starts at (min value − 0.2) and ends at (max value + 0.2) instead of starting from 0.', 'edc-charts'); ?>
    </div>
  </div>

  <div class="edc-field" id="edc-field-y-axis-range" style="<?php echo $meta['y_axis_fit_data'] === '1' ? '' : 'display:none;'; ?>">
    <label for="edc_y_axis_min"><?php echo esc_html__('Y axis minimum', 'edc-charts'); ?></label>
    <input type="number" step="any" id="edc_y_axis_min" name="edc_y_axis_min" value="<?php echo esc_attr($meta['y_axis_min']); ?>" placeholder="">
    <label for="edc_y_axis_max" style="display:block; margin-top:8px;"><?php echo esc_html__('Y axis maximum', 'edc-charts'); ?></label>
    <input type="number" step="any" id="edc_y_axis_max" name="edc_y_axis_max" value="<?php echo esc_attr($meta['y_axis_max']); ?>" placeholder="">
    <div class="edc-help">
      <?php echo esc_html__('Leave empty to use values calculated from data.', 'edc-charts'); ?>
    </div>
  </div>

  <div class="edc-field">
    <label for="edc_has_header"><?php echo esc_html__('CSV has header row?', 'edc-charts'); ?></label>
    <select id="edc_has_header" name="edc_has_header">
      <option value="1" <?php selected($meta['has_header'], '1'); ?>><?php echo esc_html__('Yes', 'edc-charts'); ?></option>
      <option value="0" <?php selected($meta['has_header'], '0'); ?>><?php echo esc_html__('No', 'edc-charts'); ?></option>
    </select>
  </div>

  <div class="edc-field">
    <label for="edc_delimiter"><?php echo esc_html__('CSV delimiter', 'edc-charts'); ?></label>
    <select id="edc_delimiter" name="edc_delimiter">
      <option value="," <?php selected($meta['delimiter'], ','); ?>><?php echo esc_html__('Comma (,)', 'edc-charts'); ?></option>
      <option value=";" <?php selected($meta['delimiter'], ';'); ?>><?php echo esc_html__('Semicolon (;)', 'edc-charts'); ?></option>
    </select>
    <div class="edc-help"><?php echo esc_html__('If your CSV looks "all in one column", try semicolon.', 'edc-charts'); ?></div>
  </div>

  <div class="edc-field">
    <label for="edc_x_col"><?php echo esc_html__('X column index (0-based)', 'edc-charts'); ?></label>
    <input type="number" min="0" id="edc_x_col" name="edc_x_col" value="<?php echo esc_attr($meta['x_col']); ?>">
    <div class="edc-help"><?php echo esc_html__('Example: 0 means first column is used as labels (dates, names, etc.)', 'edc-charts'); ?></div>
  </div>

  <div class="edc-field">
    <label for="edc_series_cols"><?php echo esc_html__('Series column indices (comma-separated, 0-based)', 'edc-charts'); ?></label>
    <input type="text" id="edc_series_cols" name="edc_series_cols" value="<?php echo esc_attr($meta['series_cols']); ?>" placeholder="1,2,3">
    <div class="edc-help"><?php echo esc_html__('Example: "1,2" means second and third columns are chart series.', 'edc-charts'); ?></div>
  </div>

  <div class="edc-field">
    <label for="edc_cache_minutes"><?php echo esc_html__('Cache minutes', 'edc-charts'); ?></label>
    <input type="number" min="1" id="edc_cache_minutes" name="edc_cache_minutes" value="<?php echo esc_attr($meta['cache_minutes']); ?>">
    <div class="edc-help"><?php echo esc_html__('How often the plugin refetches the CSV. Avoid fetching on every page load.', 'edc-charts'); ?></div>
  </div>

  <div class="edc-field">
    <label for="edc_caption_below_x"><?php echo esc_html__('Text below X axis', 'edc-charts'); ?></label>
    <?php
    wp_editor($meta['caption_below_x'], 'edc_caption_below_x', [
      'textarea_name' => 'edc_caption_below_x',
      'textarea_rows' => 4,
      'teeny' => true,
      'quicktags' => true,
      'media_buttons' => false,
      'tinymce' => ['toolbar1' => 'bold,italic,underline,link,unlink'],
    ]);
    ?>
    <div class="edc-help"><?php echo esc_html__('Free text shown below the horizontal axis of the chart.', 'edc-charts'); ?></div>
  </div>

  <div class="edc-field">
    <label for="edc_caption_left_y"><?php echo esc_html__('Text beside Y axis', 'edc-charts'); ?></label>
    <?php
    wp_editor($meta['caption_left_y'], 'edc_caption_left_y', [
      'textarea_name' => 'edc_caption_left_y',
      'textarea_rows' => 4,
      'teeny' => true,
      'quicktags' => true,
      'media_buttons' => false,
      'tinymce' => ['toolbar1' => 'bold,italic,underline,link,unlink'],
    ]);
    ?>
    <div class="edc-help"><?php echo esc_html__('Free text shown to the left of the chart, beside the vertical axis (vertically centered).', 'edc-charts'); ?></div>
  </div>

  <div class="edc-field">
    <label for="edc_bar_colors"><?php echo esc_html__('Bar colors', 'edc-charts'); ?></label>
    <input type="text" id="edc_bar_colors" name="edc_bar_colors" value="<?php echo esc_attr($meta['bar_colors']); ?>" placeholder="<?php echo esc_attr__('e.g. #1e3a5f or #1e3a5f, #2563eb, #3b82f6', 'edc-charts'); ?>">
    <div class="edc-bar-colors-row">
      <input type="color" id="edc_bar_color_picker" value="#1e3a5f" aria-label="<?php echo esc_attr__('Choose color', 'edc-charts'); ?>">
      <button type="button" id="edc_bar_color_add" class="button"><?php echo esc_html__('Add color', 'edc-charts'); ?></button>
    </div>
    <div id="edc_bar_colors_chips" class="edc-bar-colors-chips"></div>
    <div class="edc-help"><?php echo esc_html__('For bar charts only. One color (e.g. #1e3a5f) or multiple comma-separated colors, one per series. Use the picker above to add a color. Leave empty for default palette.', 'edc-charts'); ?></div>
  </div>

  <div class="edc-field">
    <label for="edc_line_area_fill">
      <input type="checkbox" id="edc_line_area_fill" name="edc_line_area_fill" value="1" <?php checked($meta['line_area_fill'], '1'); ?>>
      <?php echo esc_html__('Riempimento area sotto le linee', 'edc-charts'); ?>
    </label>
    <div class="edc-help">
      <?php echo esc_html__('With multiple series, areas are stacked (stacked area chart).', 'edc-charts'); ?>
    </div>
  </div>

  <div class="edc-field">
    <label for="edc_value_prefix"><?php echo esc_html__('Value prefix', 'edc-charts'); ?></label>
    <input type="text" id="edc_value_prefix" name="edc_value_prefix" value="<?php echo esc_attr($meta['value_prefix']); ?>" placeholder="<?php echo esc_attr__('e.g. € or € ', 'edc-charts'); ?>">
    <div class="edc-help"><?php echo esc_html__('Optional. Shown before values on the Y axis and in tooltips (e.g. € for currency).', 'edc-charts'); ?></div>
  </div>

  <div class="edc-field">
    <label for="edc_value_suffix"><?php echo esc_html__('Value suffix', 'edc-charts'); ?></label>
    <input type="text" id="edc_value_suffix" name="edc_value_suffix" value="<?php echo esc_attr($meta['value_suffix']); ?>" placeholder="<?php echo esc_attr__('e.g. % or %', 'edc-charts'); ?>">
    <div class="edc-help"><?php echo esc_html__('Optional. Shown after values on the Y axis and in tooltips (e.g. % for percentages).', 'edc-charts'); ?></div>
  </div>

  <div class="edc-field">
    <label><?php echo esc_html__('Shortcode', 'edc-charts'); ?></label>
    <code>[edc_chart id="<?php echo intval($post->ID); ?>"]</code>
  </div>
  <?php
}

/**
 * Save chart metabox: verify nonce, check capabilities, sanitize fields, update post meta, invalidate cache.
 *
 * @param int      $post_id Post ID.
 * @param \WP_Post $post    Post object.
 * @return void
 */
function edc_save_chart_metabox($post_id, $post) {
  if (!isset($_POST['edc_chart_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['edc_chart_nonce'])), 'edc_chart_save')) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;

  $fields = [
    'data_source' => 'text',
    'csv_url' => 'text',
    'csv_attachment_id' => 'int',
    'chart_type' => 'text',
    'line_smooth' => 'text',
    'line_area_fill' => 'text',
    'y_axis_fit_data' => 'text',
    'y_axis_min' => 'text',
    'y_axis_max' => 'text',
    'has_header' => 'text',
    'delimiter' => 'text',
    'x_col' => 'int',
    'series_cols' => 'text',
    'cache_minutes' => 'int',
    'caption_below_x' => 'html',
    'caption_left_y' => 'html',
    'bar_colors' => 'text',
    'value_prefix' => 'text',
    'value_suffix' => 'text',
    'table_title' => 'text',
    'year_col' => 'int',
    'month_col' => 'int',
    'value_col' => 'int',
    'value_column_label' => 'text',
  ];

  $allowed_csv_mimes = ['text/csv', 'application/csv', 'text/plain', 'application/octet-stream'];

  foreach ($fields as $key => $type) {
    $form_key = 'edc_' . $key;
    $val = isset($_POST[$form_key]) ? wp_unslash($_POST[$form_key]) : '';

    if ($key === 'y_axis_fit_data') {
      // Checkbox: treat presence as "1", absence as "0"
      $val = isset($_POST['edc_y_axis_fit_data']) ? '1' : '0';
    }

    if ($key === 'line_smooth') {
      $val = isset($_POST['edc_line_smooth']) ? '1' : '0';
    }

    if ($key === 'line_area_fill') {
      $val = isset($_POST['edc_line_area_fill']) ? '1' : '0';
    }

    if ($key === 'y_axis_min' || $key === 'y_axis_max') {
      $val = trim((string) $val);
      $val = $val === '' ? '' : (string) floatval($val);
    }

    if ($type === 'int') {
      $val = (string) max(0, intval($val));
      if ($key === 'csv_attachment_id' && $val !== '') {
        $aid = (int) $val;
        $att = $aid ? get_post($aid) : null;
        if (!$att || $att->post_type !== 'attachment') {
          $val = '';
        } else {
          $path = get_attached_file($aid);
          $ext = $path ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';
          $mime = $att->post_mime_type;
          if (!in_array($mime, $allowed_csv_mimes, true) && $ext !== 'csv') {
            $val = '';
          }
        }
      }
    } elseif ($type === 'textarea') {
      $val = sanitize_textarea_field((string) $val);
    } elseif ($type === 'html') {
      $val = wp_kses_post((string) $val);
    } else {
      $val = sanitize_text_field((string) $val);
    }

    if ($key === 'data_source') {
      $val = in_array($val, ['url', 'upload'], true) ? $val : 'url';
    }

    if ($key === 'chart_type') {
      $val = in_array($val, ['line', 'bar', 'table', 'table_tabs_year'], true) ? $val : 'line';
    }

    if ($key === 'year_col' || $key === 'month_col' || $key === 'value_col') {
      $val = (string) max(0, intval($val));
    }

    update_post_meta($post_id, 'edc_' . $key, $val);
  }

  // invalidate cache on save
  delete_transient(edc_transient_key($post_id));
}