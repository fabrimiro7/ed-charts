(function () {
  /**
   * EDC Charts – Admin: data source toggle (URL vs upload), WordPress media CSV upload,
   * bar color picker and chips (add/remove hex colors).
   */
  var i18n = window.EDC_CHARTS_ADMIN || {};

  /* ── Color picker ── */
  var input = document.getElementById('edc_bar_colors');
  var picker = document.getElementById('edc_bar_color_picker');
  var addBtn = document.getElementById('edc_bar_color_add');
  var chipsEl = document.getElementById('edc_bar_colors_chips');

  /**
   * Parse comma/space-separated string into array of valid hex colors (#RGB or #RRGGBB).
   * @param {string} [str] - Input string.
   * @returns {string[]}
   */
  function parseHexList(str) {
    if (!str || typeof str !== 'string') return [];
    return str.split(/[\s,]+/).map(function (s) { return s.trim(); }).filter(function (s) {
      return /^#[0-9a-fA-F]{6}$/.test(s) || /^#[0-9a-fA-F]{3}$/.test(s);
    });
  }

  /**
   * Join array of values into comma-separated string for the form field.
   * @param {string[]} list
   * @returns {string}
   */
  function listToFieldValue(list) {
    return list.join(', ');
  }

  /**
   * Render color chips from current input value; each chip has swatch, hex label, and remove button.
   */
  function renderChips() {
    if (!input || !chipsEl) return;
    var list = parseHexList(input.value);
    chipsEl.innerHTML = '';
    list.forEach(function (hex) {
      var chip = document.createElement('span');
      chip.className = 'edc-bar-color-chip';
      var swatch = document.createElement('span');
      swatch.className = 'edc-bar-color-chip-swatch';
      swatch.style.backgroundColor = hex;
      chip.appendChild(swatch);
      chip.appendChild(document.createTextNode(hex));
      var remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'edc-bar-color-chip-remove';
      remove.setAttribute('aria-label', i18n.removeColor || 'Remove color');
      remove.textContent = '\u00d7';
      remove.addEventListener('click', function () { removeColor(hex); });
      chip.appendChild(remove);
      chipsEl.appendChild(chip);
    });
  }

  /**
   * Add current picker color to the input and re-render chips.
   */
  function addColor() {
    if (!picker || !input) return;
    var hex = (picker.value || '#1e3a5f').trim();
    if (!/^#[0-9a-fA-F]{6}$/.test(hex)) hex = '#1e3a5f';
    var current = input.value.trim();
    input.value = current ? current + ', ' + hex : hex;
    renderChips();
  }

  /**
   * Remove one hex color from the input and re-render chips.
   * @param {string} hex - Hex color to remove (e.g. "#1e3a5f").
   */
  function removeColor(hex) {
    if (!input) return;
    var list = parseHexList(input.value).filter(function (h) { return h !== hex; });
    input.value = listToFieldValue(list);
    renderChips();
  }

  if (addBtn) addBtn.addEventListener('click', addColor);
  if (input) {
    input.addEventListener('input', renderChips);
    input.addEventListener('change', renderChips);
  }
  renderChips();

  /* ── Data source toggle + CSV upload (wp.media) ── */
  var dataSource = document.getElementById('edc_data_source');
  var fieldUrl = document.getElementById('edc-field-csv-url');
  var fieldUpload = document.getElementById('edc-field-csv-upload');
  var attachmentInput = document.getElementById('edc_csv_attachment_id');
  var uploadBtn = document.getElementById('edc_csv_upload_btn');
  var removeBtn = document.getElementById('edc_csv_remove_btn');
  var fileNameEl = document.getElementById('edc_csv_file_name');

  function toggleSourceFields() {
    var v = dataSource ? dataSource.value : 'url';
    if (fieldUrl) fieldUrl.style.display = (v === 'url') ? '' : 'none';
    if (fieldUpload) fieldUpload.style.display = (v === 'upload') ? '' : 'none';
  }

  function updateUploadUI() {
    var id = attachmentInput ? attachmentInput.value : '';
    if (removeBtn) removeBtn.style.display = id ? '' : 'none';
    if (fileNameEl) fileNameEl.textContent = id
      ? (fileNameEl.getAttribute('data-filename') || i18n.fileSelected || 'File selected')
      : '';
  }

  if (dataSource) dataSource.addEventListener('change', toggleSourceFields);
  toggleSourceFields();

  /* ── Y axis fit: show/hide min/max inputs ── */
  var yAxisFitCheckbox = document.getElementById('edc_y_axis_fit_data');
  var yAxisRangeField = document.getElementById('edc-field-y-axis-range');

  function toggleYAxisRangeFields() {
    if (yAxisRangeField) {
      yAxisRangeField.style.display = (yAxisFitCheckbox && yAxisFitCheckbox.checked) ? '' : 'none';
    }
  }

  if (yAxisFitCheckbox) {
    yAxisFitCheckbox.addEventListener('change', toggleYAxisRangeFields);
    yAxisFitCheckbox.addEventListener('click', toggleYAxisRangeFields);
  }
  toggleYAxisRangeFields();

  if (uploadBtn && typeof wp !== 'undefined' && wp.media) {
    var frame = null;
    uploadBtn.addEventListener('click', function () {
      if (frame) { frame.open(); return; }
      frame = wp.media({
        title: i18n.selectOrUploadCsv || 'Select or upload CSV',
        button: { text: i18n.useThisFile || 'Use this file' },
        multiple: false
      });
      frame.on('select', function () {
        var att = frame.state().get('selection').first().toJSON();
        if (att && att.id) {
          attachmentInput.value = att.id;
          if (fileNameEl) fileNameEl.setAttribute('data-filename', att.filename || att.title || i18n.fileSelected || 'File selected');
          updateUploadUI();
        }
      });
      frame.open();
    });
  }

  if (removeBtn) {
    removeBtn.addEventListener('click', function () {
      if (attachmentInput) attachmentInput.value = '';
      if (fileNameEl) fileNameEl.removeAttribute('data-filename');
      updateUploadUI();
    });
  }

  if (i18n.attachmentFileName && fileNameEl) {
    fileNameEl.setAttribute('data-filename', i18n.attachmentFileName);
    fileNameEl.textContent = i18n.attachmentFileName;
  }
  if (i18n.attachmentFileName && removeBtn) {
    removeBtn.style.display = '';
  }

  updateUploadUI();
})();
