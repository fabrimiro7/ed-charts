(function () {
  /**
   * EDC Charts – Frontend: fetch chart data via REST, build ECharts option, init and resize.
   * Runs on DOM ready; finds all div[data-edc-chart-id] and initializes one chart per container.
   */

  /**
   * Show error message in the .edc-chart-error element for the given container.
   * @param {string} containerId - ID of the chart container (used in data-edc-error-for).
   * @param {string} msg - Error message to display.
   */
  function showError(containerId, msg) {
    const el = document.querySelector('[data-edc-error-for="' + containerId + '"]');
    if (!el) return;
    el.style.display = 'block';
    el.textContent = msg;
  }

  /**
   * Format a value with optional prefix and suffix for tooltip (e.g. "€ 100" or "100 %").
   * @param {*} v - Value (number or string).
   * @param {string} [prefix] - Optional prefix to prepend.
   * @param {string} [suffix] - Optional suffix to append (with space before it).
   * @returns {string}
   */
  function fmtValue(v, prefix, suffix) {
    if (v === null || typeof v === "undefined") return "";
    const str = String(v);
    const pre = prefix ? prefix : "";
    const suf = suffix ? " " + suffix : "";
    return pre + str + suf;
  }

  /**
   * Format a number for axis/tooltip display: at most 1 decimal (e.g. 22.4 or 22).
   * @param {*} value - Value (number or string).
   * @returns {string} Formatted string or empty if not a finite number.
   */
  function roundToMax1Decimal(value) {
    const num = typeof value === "number" ? value : parseFloat(value, 10);
    if (!Number.isFinite(num)) return "";
    const rounded = Math.round(num * 10) / 10;
    return rounded % 1 === 0 ? String(Math.round(rounded)) : rounded.toFixed(1);
  }

  /**
   * Format a numeric value for table display (optional thousands separator).
   * @param {*} value - Value (number or string).
   * @returns {string}
   */
  function formatTableNumber(value) {
    if (value === null || typeof value === "undefined") return "";
    const num = typeof value === "number" ? value : parseFloat(String(value).replace(/\./g, "").replace(",", "."), 10);
    if (!Number.isFinite(num)) return String(value);
    return num.toLocaleString("it-IT", { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  /**
   * Render table type (n columns): optional title + table with header and rows.
   * @param {Object} payload - API payload with header, rows, meta.
   * @param {Element} container - Container element.
   */
  function renderTableType(payload, container) {
    const meta = payload.meta || {};
    const title = (meta.table_title || "").trim();
    const header = Array.isArray(payload.header) ? payload.header : [];
    const rows = Array.isArray(payload.rows) ? payload.rows : [];

    container.classList.add("edc-chart-table-wrap");
    container.style.height = "auto";
    let html = "";
    if (title) {
      html += '<div class="edc-chart-table-title">' + escapeHtml(title) + "</div>";
    }
    html += '<table class="edc-chart-table"><thead><tr>';
    header.forEach(function (h) {
      html += "<th scope=\"col\">" + escapeHtml(String(h)) + "</th>";
    });
    html += "</tr></thead><tbody>";
    rows.forEach(function (row) {
      const cells = Array.isArray(row) ? row : [];
      html += "<tr>";
      cells.forEach(function (cell) {
        const numStr = formatTableNumber(cell);
        const val = numStr !== "" ? numStr : escapeHtml(String(cell));
        html += "<td>" + val + "</td>";
      });
      html += "</tr>";
    });
    html += "</tbody></table>";
    container.innerHTML = html;
    container.__edcChart = true;
  }

  /**
   * Escape HTML for safe insertion into DOM.
   * @param {string} s
   * @returns {string}
   */
  function escapeHtml(s) {
    if (s == null) return "";
    const div = document.createElement("div");
    div.textContent = s;
    return div.innerHTML;
  }

  /**
   * Render table_tabs_year type: tabs (years + Tutti) and per-tab table (Mese, Valore).
   * @param {Object} payload - API payload with years, dataByYear, meta.
   * @param {Element} container - Container element.
   */
  function renderTableTabsYearType(payload, container) {
    const meta = payload.meta || {};
    const valueLabel = (meta.value_column_label || "Valore").trim();
    const years = Array.isArray(payload.years) ? payload.years : [];
    const dataByYear = payload.dataByYear || {};

    container.classList.add("edc-tabs-year-wrap");
    container.style.height = "auto";
    let html = '<div class="edc-tabs-year-tabs" role="tablist">';
    years.forEach(function (year, idx) {
      const id = "edc-tab-" + (container.id || "chart") + "-" + year;
      const panelId = "edc-panel-" + (container.id || "chart") + "-" + year;
      const activeClass = idx === 0 ? " edc-tab-active" : "";
      const ariaSelected = idx === 0 ? "true" : "false";
      const ariaHidden = idx === 0 ? "false" : "true";
      html += '<button type="button" class="edc-tabs-year-tab' + activeClass + '" role="tab" aria-selected="' + ariaSelected + '" aria-controls="' + panelId + '" id="' + id + '" data-year="' + escapeHtml(String(year)) + '">' + escapeHtml(String(year)) + "</button>";
    });
    const allId = "edc-tab-" + (container.id || "chart") + "-all";
    const allPanelId = "edc-panel-" + (container.id || "chart") + "-all";
    html += '<button type="button" class="edc-tabs-year-tab" role="tab" aria-selected="false" aria-controls="' + allPanelId + '" id="' + allId + '" data-year="all">Tutti</button>';
    html += "</div>";

    html += '<div class="edc-tabs-year-content">';

    // Build "Tutti" rows: all years, each year's rows (month, value)
    const allRows = [];
    years.forEach(function (year) {
      const list = dataByYear[year] || [];
      list.forEach(function (item) {
        allRows.push({ month: item.month, value: item.value });
      });
    });

    years.forEach(function (year, idx) {
      const panelId = "edc-panel-" + (container.id || "chart") + "-" + year;
      const isFirst = idx === 0;
      const list = dataByYear[year] || [];
      html += '<div class="edc-tabs-year-panel" role="tabpanel" id="' + panelId + '" aria-labelledby="edc-tab-' + (container.id || "chart") + "-" + year + '" data-year="' + escapeHtml(String(year)) + '" aria-hidden="' + (!isFirst) + '">';
      html += buildMonthValueTable(list, valueLabel);
      html += "</div>";
    });

    html += '<div class="edc-tabs-year-panel" role="tabpanel" id="' + allPanelId + '" aria-labelledby="' + allId + '" data-year="all" aria-hidden="true">';
    html += buildMonthValueTable(allRows, valueLabel);
    html += "</div>";

    html += "</div>";
    container.innerHTML = html;

    // Event delegation: one listener on container so clicks always work (Elementor/theme safe)
    container.addEventListener("click", function (e) {
      const tab = e.target && e.target.closest && e.target.closest(".edc-tabs-year-tab");
      if (!tab || !tab.getAttribute("data-year")) return;

      const year = tab.getAttribute("data-year");
      const panels = container.querySelectorAll(".edc-tabs-year-panel");
      const tabs = container.querySelectorAll(".edc-tabs-year-tab");

      tabs.forEach(function (t) {
        t.classList.remove("edc-tab-active");
        t.setAttribute("aria-selected", "false");
      });
      tab.classList.add("edc-tab-active");
      tab.setAttribute("aria-selected", "true");

      panels.forEach(function (p) {
        const isMatch = p.getAttribute("data-year") === year;
        p.setAttribute("aria-hidden", isMatch ? "false" : "true");
      });
    });

    container.__edcChart = true;
  }

  /**
   * Build HTML for a table with columns Mese and value (valueLabel as header).
   * @param {Array} list - Array of { month, value }.
   * @param {string} valueLabel - Header for value column.
   * @returns {string}
   */
  function buildMonthValueTable(list, valueLabel) {
    let html = '<table class="edc-chart-table"><thead><tr><th scope="col">Mese</th><th scope="col">' + escapeHtml(valueLabel) + "</th></tr></thead><tbody>";
    list.forEach(function (item) {
      const val = item.value !== null && item.value !== undefined && typeof item.value === "number"
        ? formatTableNumber(item.value)
        : escapeHtml(String(item.value != null ? item.value : ""));
      html += "<tr><td>" + escapeHtml(String(item.month || "")) + "</td><td>" + val + "</td></tr>";
    });
    html += "</tbody></table>";
    return html;
  }

  /**
   * Build ECharts option object from API payload. Handles grid, series, tooltip, legend,
   * x/y axes, optional dataZoom; adapts layout for narrow containers (< 480px).
   * @param {Object} payload - API response: labels, datasets, chartType, meta.
   * @param {number} [containerWidth] - Container width for responsive grid.
   * @returns {Object} ECharts option.
   */
  function buildOption(payload, containerWidth) {
    const meta = payload.meta || {};
    const title = meta.title || "";
    const xLabel = meta.x_label || "";
    const yLabel = meta.y_label || "";
    const valuePrefix = meta.value_prefix || "";
    const valueSuffix = meta.value_suffix || "";
    const rotateX = parseInt(meta.rotate_x || "0", 10) || 0;
    const showZoom = meta.show_data_zoom === "1";
    const stackBars = meta.stack === "1";
     const fitYAxis = meta.y_axis_fit_data === "1";
    const lineSmooth = meta.line_smooth !== "0";
    const lineAreaFill = meta.line_area_fill === "1";
    const barColorsRaw = (meta.bar_colors || "").trim();
    const barColors = barColorsRaw
      ? barColorsRaw.split(/[\s,]+/).map(function (c) { return c.trim(); }).filter(Boolean)
      : [];

    const labels = payload.labels || [];
    const datasets = payload.datasets || [];
    const chartType = payload.chartType || meta.chart_type || "line";

    const isNarrow = typeof containerWidth === "number" && containerWidth > 0 && containerWidth < 480;
    const grid = isNarrow
      ? { left: 36, right: 12, top: title ? 44 : 20, bottom: rotateX ? 56 : 40, containLabel: true }
      : { left: 52, right: 24, top: title ? 56 : 26, bottom: rotateX ? 70 : 52, containLabel: true };
    const nameGap = isNarrow ? (rotateX ? 40 : 28) : (rotateX ? 52 : 34);

    const series = datasets.map((ds, idx) => {
      const base = {
        name: ds.label || "Series",
        type: (chartType === "line") ? "line" : "bar",
        data: Array.isArray(ds.data) ? ds.data : [],
        emphasis: { focus: "series" },
      };

      if (chartType === "line") {
        base.smooth = lineSmooth;
        base.showSymbol = false;
        if (lineAreaFill) {
          base.areaStyle = { opacity: 0.18 };
          if (datasets.length > 1) base.stack = "total";
        }
      }

      if (chartType === "bar" || chartType === "grouped_bar") {
        if (stackBars) base.stack = "total";
        base.itemStyle = base.itemStyle || {};
        // top-left, top-right, bottom-right, bottom-left: solo sopra
        base.itemStyle.borderRadius = [6, 6, 0, 0];
      }

      return base;
    });

    const option = {
      animation: true,
      color: barColors.length > 0 ? barColors : undefined,
      grid: grid,
      title: title ? { text: title, left: "center" } : undefined,
      tooltip: {
        trigger: "axis",
        axisPointer: { type: (chartType === "line") ? "line" : "shadow" },
        valueFormatter: (value) => fmtValue(roundToMax1Decimal(value), valuePrefix, valueSuffix),
      },
      legend: {
        show: datasets.length > 1,
        top: title ? 26 : 0,
      },
      xAxis: {
        type: "category",
        name: xLabel || "",
        nameLocation: "middle",
        nameGap: nameGap,
        data: labels,
        axisLabel: {
          rotate: rotateX,
          hideOverlap: true,
        },
      },
      yAxis: {
        type: "value",
        name: yLabel || "",
        axisLabel: {
          formatter: (value) => roundToMax1Decimal(value),
        },
      },
      series,
    };

    if (fitYAxis && Array.isArray(datasets) && datasets.length > 0) {
      const allValues = [];
      datasets.forEach((ds) => {
        if (!ds || !Array.isArray(ds.data)) return;
        ds.data.forEach((v) => {
          if (typeof v === "number" && !Number.isNaN(v)) {
            allValues.push(v);
          }
        });
      });

      if (allValues.length > 0) {
        const dataMin = Math.min.apply(null, allValues);
        const dataMax = Math.max.apply(null, allValues);
        const parsedMin = parseFloat(meta.y_axis_min, 10);
        const parsedMax = parseFloat(meta.y_axis_max, 10);
        const useCustomMin = Number.isFinite(parsedMin);
        const useCustomMax = Number.isFinite(parsedMax);
        if (Number.isFinite(dataMin) && Number.isFinite(dataMax)) {
          option.yAxis.min = useCustomMin ? parsedMin : (dataMin - 0.2);
          option.yAxis.max = useCustomMax ? parsedMax : (dataMax + 0.2);
        }
      }
    }

    if (showZoom) {
      option.dataZoom = [
        { type: "inside" },
        { type: "slider", height: isNarrow ? 14 : 18, bottom: isNarrow ? 8 : 12 },
      ];
    }

    return option;
  }

  /**
   * Fetch chart data from REST, init ECharts on the container, attach ResizeObserver for resize.
   * On error, calls showError for the container's error element.
   * Skips containers already initialized (e.g. after dynamic load in Elementor).
   * @param {Element} container - DOM element with data-edc-chart-id and id.
   */
  function initOne(container) {
    if (container.__edcChart) return;

    const chartId = container.getAttribute("data-edc-chart-id");
    if (!chartId) return;

    const containerId = container.getAttribute("id");
    const url = (window.EDC_CHARTS && window.EDC_CHARTS.restBase)
      ? (window.EDC_CHARTS.restBase + "/chart/" + chartId)
      : ("/wp-json/edc/v1/chart/" + chartId);

    fetch(url, {
      method: "GET",
      headers: {
        "Accept": "application/json",
        "X-WP-Nonce": window.EDC_CHARTS ? window.EDC_CHARTS.nonce : ""
      }
    })
      .then(async (res) => {
        const json = await res.json().catch(() => null);
        if (!res.ok || !json) throw new Error("Failed to load chart data.");
        return json;
      })
      .then((payload) => {
        if (payload.error) {
          showError(containerId, payload.message || "Error loading chart.");
          return;
        }

        const chartType = payload.chartType || (payload.meta && payload.meta.chart_type) || "line";

        if (chartType === "table") {
          renderTableType(payload, container);
          return;
        }

        if (chartType === "table_tabs_year") {
          renderTableTabsYearType(payload, container);
          return;
        }

        const containerWidth = container.getBoundingClientRect().width || container.offsetWidth || 0;
        const chart = echarts.init(container, null, { renderer: "canvas" });
        const option = buildOption(payload, containerWidth);
        chart.setOption(option, true);

        // responsive
        const ro = new ResizeObserver(() => chart.resize());
        ro.observe(container);

        // if init ran with 0 width (e.g. Elementor flex not yet laid out), force resize when layout settles
        if (containerWidth === 0) {
          requestAnimationFrame(function () { chart.resize(); });
          setTimeout(function () { chart.resize(); }, 100);
          setTimeout(function () { chart.resize(); }, 300);
          startAggressiveResize(container, chart);
        }

        // store references to avoid GC in some themes
        container.__edcChart = chart;
        container.__edcRO = ro;
      })
      .catch((err) => showError(containerId, err.message || "Unexpected error."));
  }

  /**
   * Poll chart.resize() every 250ms for containers that had 0 width at init (e.g. Elementor below tabs).
   * Stops when container gets width or after 6 seconds.
   */
  function startAggressiveResize(container, chart) {
    var deadline = Date.now() + 6000;
    var t = setInterval(function () {
      if (Date.now() > deadline) {
        clearInterval(t);
        if (container.__edcResizeInterval === t) delete container.__edcResizeInterval;
        return;
      }
      chart.resize();
      var w = container.getBoundingClientRect().width || container.offsetWidth || 0;
      if (w > 0) {
        clearInterval(t);
        if (container.__edcResizeInterval === t) delete container.__edcResizeInterval;
      }
    }, 250);
    container.__edcResizeInterval = t;
  }

  /**
   * Initialize all chart containers on the page (div[data-edc-chart-id]).
   * Safe to call multiple times; already-initialized containers are skipped.
   * Uses IntersectionObserver so charts init when visible (fixes Elementor sections with 0 width at load).
   */
  function initAll() {
    var containers = document.querySelectorAll('div[data-edc-chart-id]');
    containers.forEach(observeForVisibility);
  }

  var visibilityObserver = null;

  /**
   * Observe container and init chart when it enters viewport.
   * Always inits when intersecting (even with 0 width); aggressive resize handles late layout.
   */
  function observeForVisibility(container) {
    if (container.__edcChart || container.__edcObservedForVisibility) return;
    if (!container.getAttribute('data-edc-chart-id')) return;

    if (typeof IntersectionObserver === 'undefined') {
      initOne(container);
      return;
    }

    container.__edcObservedForVisibility = true;
    if (!visibilityObserver) {
      visibilityObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          var el = entry.target;
          visibilityObserver.unobserve(el);
          initOne(el);
        });
      }, { rootMargin: '80px', threshold: 0.01 });
    }
    visibilityObserver.observe(container);
  }

  /**
   * Observe DOM for new chart containers (e.g. Elementor shortcode rendered after DOMContentLoaded).
   * New containers are observed for visibility before init.
   */
  function observeNewCharts() {
    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (node.nodeType !== 1) return;
          if (node.getAttribute && node.getAttribute('data-edc-chart-id')) {
            observeForVisibility(node);
            return;
          }
          var containers = node.querySelectorAll && node.querySelectorAll('div[data-edc-chart-id]');
          if (containers) containers.forEach(observeForVisibility);
        });
      });
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      initAll();
      observeNewCharts();
      setTimeout(initAll, 800);
      setTimeout(initAll, 2000);
      setTimeout(initAll, 4000);
    });
  } else {
    initAll();
    observeNewCharts();
    setTimeout(initAll, 800);
    setTimeout(initAll, 2000);
    setTimeout(initAll, 4000);
  }

  window.EDC_CHARTS = window.EDC_CHARTS || {};
  window.EDC_CHARTS.init = initAll;
  window.EDC_CHARTS.resizeAll = function () {
    document.querySelectorAll('div[data-edc-chart-id]').forEach(function (el) {
      if (el.__edcChart && typeof el.__edcChart.resize === "function") el.__edcChart.resize();
    });
  };
})();