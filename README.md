# EDC Charts (Google Sheets CSV)

Create interactive, responsive charts from Google Sheets (published as CSV) or from uploaded CSV files. Embed charts anywhere with a shortcode.

**Developer:** Fabrizio Miranda  
**Company:** [EfestoDev](https://efestodev.com)

---

## Compatibility

- **WordPress:** 5.0 or higher (tested up to 6.9.1)
- **PHP:** 7.4 or higher
- **License:** GPLv2 or later

---

## Features

- **Line and bar charts** powered by Apache ECharts
- **Data sources:** Google Sheets (publish as CSV) or direct CSV file upload
- **Configurable parsing:** delimiter (comma/semicolon), header row, column indices for X axis and series
- **Custom bar colors** with a visual color picker (one or more hex colors per series)
- **Captions** for X and Y axes with rich text (bold, italic, links)
- **Caching** via WordPress transients to avoid refetching on every page load (configurable per chart)
- **Responsive** charts that adapt to container width
- **Shortcode-based:** `[edc_chart id="123"]` — embed in any post or page
- **Translations:** English, Italian, Spanish, French (`.pot`/`.po` in `languages/`)

---

## Installation

1. Upload the `edc-charts` folder to `wp-content/plugins/`.
2. Activate the plugin via **Plugins** in the WordPress admin.
3. Go to **EDC Charts** in the admin menu to create your first chart.

---

## Usage

1. In the admin, go to **EDC Charts → Add New**.
2. Give the chart a title (for your reference).
3. Choose **Data source:**  
   - **Google Sheets URL** — use the CSV publish link from Google Sheets (File → Share → Publish to web → CSV).  
   - **Upload CSV file** — select or upload a CSV from the Media Library.
4. Set **Chart type** (Line or Bar), **CSV has header row**, **delimiter**, **X column index**, and **Series column indices** (0-based, comma-separated).
5. Optionally set cache duration, axis captions, and bar colors.
6. Copy the **Shortcode** shown in the metabox and paste it into any post or page.

---

## Shortcode

```
[edc_chart id="POST_ID" height="420"]
```

| Attribute | Description |
|-----------|-------------|
| `id`      | (required) The post ID of the EDC Chart. |
| `height`  | (optional) Height of the chart in pixels. Default: 420. Minimum: 220. |

Example: `[edc_chart id="42" height="380"]`

---

## Requirements

- WordPress 5.0+
- PHP 7.4+

---

## License

GPLv2 or later. See [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).
