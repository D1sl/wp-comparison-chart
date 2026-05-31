# Comparison Chart

A WordPress plugin that renders an interactive, drag-to-reorder comparison chart for any content type. Works with or without Bricks Builder.

**Author:** Benjamin Keaton

## Screenshots

![Chart preview — frontend comparison table](.github/screenshots/Chart%20Preview.png)

![Schema setup — defining rows on a parent post or term](.github/screenshots/Chart%20Setup.png)

![Values metabox — filling in values on a column post](.github/screenshots/Chart%20Values.png)

## How it works

The plugin supports two modes that can coexist on the same site. The mode is determined by how data is structured — no global switch needed.

### Post hierarchy mode

Uses WordPress's native parent/child post relationship:

- **Parent post** — defines the comparison rows (the schema). Each row has a label, a type, and type-specific options.
- **Child posts** — each becomes a column in the chart, filling in values for every row defined by the parent.

When the chart is rendered on a child post's page, that column is automatically pinned so visitors can compare it against the others.

### Taxonomy mode

Uses a taxonomy term to group posts into a chart:

- **Taxonomy term** — defines the comparison rows (the schema), set on the term's edit screen.
- **Top-level posts assigned to that term** — each becomes a column, filling in values for every row defined by the term.

Enable taxonomy mode for a specific taxonomy under **Settings → Comparison Chart → Taxonomy Mode**. Any number of taxonomies can be enabled simultaneously.

## Row types

| Type | Description |
|------|-------------|
| **Text** | Plain text. Optional variants: Default, Display (serif), Quote (italic). |
| **Pills** | A list of tags/labels, one per line. |
| **Rating** | Dot or star rating out of a configurable max (1–10). |
| **Meter** | A fill bar out of a configurable max. |
| **Bool** | A simple yes/no checkmark. |

## Installation

### From a release (recommended)

1. Download `comparison-chart-vX.X.X.zip` from the [Releases page](https://github.com/D1sl/wp-comparison-chart/releases).
2. In your WordPress admin go to **Plugins → Add New → Upload Plugin**, choose the ZIP, and click **Install Now**.
3. Activate the plugin, then go to **Settings → Comparison Chart** to configure post types and styling.

### Manual

1. Clone or download the repository and upload the `comparison-chart` folder to `/wp-content/plugins/`.
2. Activate the plugin from **Plugins** in the WordPress admin.
3. Go to **Settings → Comparison Chart** to configure post types and styling.

## Usage

### Post hierarchy mode

1. Create a **parent post** — this is your comparison page. Publish it first so its children can be attached.
2. Create **child posts** by setting the parent post in the page attributes. Each child becomes one column.
3. On the parent post's edit screen, use the **Comparison Chart Settings** metabox to define your rows.
4. On each child post's edit screen, fill in the **Comparison Chart Values** metabox.

### Taxonomy mode

1. Go to **Settings → Comparison Chart** and enable the taxonomy under **Taxonomy Mode**.
2. Edit any term in that taxonomy — a **Comparison Chart** section will appear at the bottom. Define your rows there.
3. Each top-level post assigned to that term will now show a **Comparison Chart Values** metabox. Fill in values for each post.

### Displaying the chart

**Shortcode** (works on any post type, with or without Bricks):

```
[comparison_chart]
```

Place this on a parent post, child post, or any post assigned to a term with a schema. The plugin auto-detects which mode to use. Additional attributes:

| Attribute | Description |
|-----------|-------------|
| `id="123"` | Render the chart for a specific post ID |
| `term="slug"` | Force taxonomy mode for a specific term slug |
| `term="slug" taxonomy="tax"` | Explicit term and taxonomy (required when the same slug exists in multiple taxonomies) |

Examples:

```
[comparison_chart]
[comparison_chart id="123"]
[comparison_chart term="wrinkle-relaxer"]
[comparison_chart term="wrinkle-relaxer" taxonomy="treatment_category"]
```

When rendered on a post that is one of the columns, that column is automatically pinned.

**Bricks Builder**: Enable Bricks support in **Settings → Comparison Chart**, then add the **Comparison Chart** element (found under the Comparison Chart category) in the Bricks editor. Styling is controlled per-element in the Bricks panel.

## Settings

Found at **Settings → Comparison Chart**.

| Setting | Description |
|---------|-------------|
| **Post Types** | Which post types show the schema and values metaboxes. Pages are always enabled. |
| **Taxonomy Mode** | Which taxonomies get a Row Schema editor on their term edit screens. |
| **Bricks Builder support** | Registers the plugin as a Bricks element. When off, use the shortcode. |
| **Chart Styling** | Global style controls for shortcode-rendered charts (ignored when Bricks support is on). |

### Style options (shortcode mode)

- Primary colour — drives headers, row tints, and borders.
- Secondary colour — pills, ratings, meters, and checkmarks. Falls back to primary if blank.
- Body text colour
- Body and heading font families (CSS `font-family` values)
- Pill / button border radius
- Minimum column width (px)
- Label column width (px)
- Full-bleed scroll — lets the chart scroll edge-to-edge beyond its container, with a configurable gutter.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Bricks Builder (optional, for the native element)
