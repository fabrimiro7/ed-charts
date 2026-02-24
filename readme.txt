=== EDC Charts (Google Sheets CSV) ===
Contributors: efestodev
Tags: charts, csv, google sheets, echarts, data visualization
Requires at least: 5.0
Tested up to: 6.9.1
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create interactive charts from Google Sheets published as CSV or uploaded CSV files.

== Description ==

EDC Charts lets you create interactive, responsive charts from CSV data. You can either link a Google Sheets document published as CSV or upload a CSV file directly.

**Features:**

* Line and bar chart types powered by Apache ECharts
* Import data from Google Sheets (publish as CSV) or upload CSV files
* Configurable CSV parsing: delimiter, header row, column indices
* Customizable bar colors with a visual color picker
* Captions for X and Y axes with rich text (bold, italic, links)
* Built-in caching to avoid refetching data on every page load
* Fully responsive charts that adapt to any screen size
* Shortcode-based: embed charts anywhere with `[edc_chart id="123"]`
* Internationalized: English, Italian, Spanish, and French translations included

**How it works:**

1. Create a new EDC Chart from the admin menu
2. Choose your data source: Google Sheets URL or upload a CSV file
3. Configure chart type, columns, and appearance
4. Copy the shortcode and paste it into any post or page

== Installation ==

1. Upload the `edc-charts` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to "EDC Charts" in the admin menu to create your first chart.

== Frequently Asked Questions ==

= How do I get a CSV URL from Google Sheets? =

In Google Sheets, go to File → Share → Publish to web. Choose the sheet you want, select "Comma-separated values (.csv)" as the format, and click Publish. Copy the generated link and paste it into the chart settings.

= What CSV delimiters are supported? =

The plugin supports comma (,) and semicolon (;) delimiters. If your data appears in a single column, try switching to semicolon.

= Can I customize the chart colors? =

Yes. For bar charts, you can specify one or more hex colors (e.g., #1e3a5f, #2563eb) using the color picker in the chart settings.

= How does caching work? =

The plugin caches fetched CSV data using WordPress transients. You can configure the cache duration (in minutes) per chart. The cache is automatically cleared when you save the chart settings.

== Screenshots ==

1. Chart settings metabox in the admin area.
2. A line chart rendered on the frontend.
3. A bar chart with custom colors.

== Changelog ==

= 0.2.0 =
* Added CSV file upload as an alternative data source.
* Added customizable bar colors with visual color picker.
* Added captions for X and Y axes with rich text support.
* Security hardening and WordPress.org compliance improvements.

= 0.1.0 =
* Initial release.

== Upgrade Notice ==

= 0.2.0 =
Security improvements and new features. Update recommended.
