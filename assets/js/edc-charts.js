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
   * Format a value with optional suffix for tooltip/axis (e.g. "100 €").
   * @param {*} v - Value (number or string).
   * @param {string} [suffix] - Optional suffix to append.
   * @returns {string}
   */
  function fmtSuffix(v, suffix) {
    if (v === null || typeof v === "undefined") return "";
    if (!suffix) return String(v);
    return String(v) + " " + suffix;
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
    const ySuffix = meta.y_suffix || "";
    const rotateX = parseInt(meta.rotate_x || "0", 10) || 0;
    const showZoom = meta.show_data_zoom === "1";
    const stackBars = meta.stack === "1";
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
        base.smooth = true;
        base.showSymbol = false;
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
        valueFormatter: (value) => fmtSuffix(value, ySuffix),
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
          formatter: (value) => fmtSuffix(value, ySuffix),
        },
      },
      series,
    };

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
   * @param {Element} container - DOM element with data-edc-chart-id and id.
   */
  function initOne(container) {
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

        const containerWidth = container.getBoundingClientRect().width || container.offsetWidth || 0;
        const chart = echarts.init(container, null, { renderer: "canvas" });
        const option = buildOption(payload, containerWidth);
        chart.setOption(option, true);

        // responsive
        const ro = new ResizeObserver(() => chart.resize());
        ro.observe(container);

        // store references to avoid GC in some themes
        container.__edcChart = chart;
        container.__edcRO = ro;
      })
      .catch((err) => showError(containerId, err.message || "Unexpected error."));
  }

  /**
   * Initialize all chart containers on the page (div[data-edc-chart-id]).
   */
  function initAll() {
    document.querySelectorAll('div[data-edc-chart-id]').forEach(initOne);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initAll);
  } else {
    initAll();
  }
})();