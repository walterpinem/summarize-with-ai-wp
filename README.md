# Summarize with AI — WordPress Plugin

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)
![Version](https://img.shields.io/badge/version-1.1.0-success)

Adds a row of "Summarize with AI" buttons to your content. Each button opens ChatGPT, Claude, Grok, Perplexity or Google AI Mode with a prompt already filled in, asking it to summarize the page the visitor was reading.

The buttons are plain links. There is no API call, no API key, no cost and nothing sent from your server.

For the full write-up, see **[Summarize with AI WordPress Plugin](https://walterpinem.com/summarize-with-ai-wordpress-plugin/)**.

## Features

- **Five AI services** — ChatGPT, Claude, Grok, Perplexity and Google AI Mode, each toggled independently. Only assistants with a working prefill parameter are bundled; see [Why not Gemini or Copilot?](#why-not-gemini-or-copilot).
- **Customizable prompt** with ten placeholders resolved per page.
- **Automatic placement** before and/or after the content of the post types you pick — no theme editing.
- **Shortcode and block**, both rendered by the same PHP so they can never drift apart.
- **Three button styles**, two layouts and an icon-only mode.
- **Copy-prompt button** for AI tools that have no shareable link.
- **"Add as a preferred source on Google" button** — its own shortcode, block and settings tab, so readers can nominate your site in Google Search.
- **Optional click tracking** via the `gtag()` or `dataLayer` your site already loads. Off by default.
- **Conditional assets** — CSS and JS load only on pages that render the buttons.
- **Live preview in the settings** — the Appearance tab shows real buttons updating as you change style, layout, label and services, before you save.
- **Translation ready**, with `languages/summarize-with-ai.pot` included.

## Requirements

| | |
|---|---|
| WordPress | 6.0 or higher |
| PHP | 7.4 or higher |

## Installation

1. Upload the `summarize-with-ai` folder to `/wp-content/plugins/`.
2. Activate it through the **Plugins** menu.
3. Configure it under **Settings → Summarize with AI**.

## Plugin structure

```
summarize-with-ai/
├── admin/
│   └── settings.php                # Settings page view
├── assets/
│   ├── css/{admin,public}.css
│   ├── img/*.svg                   # Service icons
│   └── js/{block,frontend}.js
├── blocks/summarize-with-ai/
│   └── block.json                  # Block metadata
├── blocks/summarize-with-ai-google-source/
│   └── block.json
├── includes/
│   ├── admin.php                   # Menu, setting registration, upgrades
│   ├── frontend.php                # Prompt, renderer, shortcode, block, placement
│   ├── google-source.php           # "Preferred source on Google" button
│   ├── options.php                 # Defaults, accessors, sanitization
│   └── services.php                # AI service registry
├── languages/summarize-with-ai.pot
├── summarize-with-ai.php           # Bootstrap
└── uninstall.php
```

## Usage

### Automatic placement

**Settings → Summarize with AI → Placement**: choose before, after or both, and tick the post types. Posts that already contain the shortcode or the block are skipped, so you never get duplicates.

### Blocks

- **Summarize with AI** — the AI button row. Its sidebar controls which services show, the label, style, layout, service names and the copy-prompt button.
- **Add as Preferred Source on Google** — the Google button on its own, with label and alignment controls.

### Shortcode

```
[summarizewithai]
```

In a template file:

```php
<?php echo do_shortcode( '[summarizewithai]' ); ?>
```

Or call the renderer directly:

```php
<?php echo summarizewithai_render( array( 'services' => 'claude,chatgpt' ) ); ?>
```

#### Shortcode attributes

| Attribute | Values | Description |
|---|---|---|
| `services` | `chatgpt`, `grok`, `perplexity`, `claude`, `googleai` | Comma separated, rendered in the order given. |
| `label` | any text | Text before the buttons. Empty hides it. |
| `style` | `filled`, `outline`, `minimal` | Button style. |
| `layout` | `inline`, `stacked` | Inline wraps; stacked is full width. |
| `show_text` | `yes`, `no` | `no` gives icon-only buttons; names stay available to screen readers. |
| `copy` | `yes`, `no` | Show the copy-prompt button. |
| `prompt` | any text | A one-off prompt template for this placement. |
| `google_source` | `auto`, `no`, `first`, `last`, `row` | Where the Google button goes. `auto` follows the Google tab's setting. |
| `class` | CSS classes | Extra classes on the wrapper. |

```
[summarizewithai services="claude,chatgpt" style="outline" label="Ask AI about this:"]
```

### Add as a preferred source on Google

Google Search lets readers nominate sites they want to see more of. This button links to `google.com/preferences/source` for your domain, so a reader can add you in one click.

It is about the site, not the post: no prompt, no AI service, and the same link on every page. Google's mark is multicolour, so the button keeps a neutral pill surface rather than a brand fill.

**Settings → Summarize with AI → Google** offers seven placements:

| Placement | Behaviour |
|---|---|
| Only where I place it | Fully manual — shortcode or block only. The default. |
| As the first button | One more button in the AI row, before ChatGPT. |
| As the last button | One more button in the AI row, after the others. |
| In its own row below the AI buttons | A separate row under the AI buttons. |
| Before the content | On its own, above the post. |
| After the content | On its own, below the post. |
| Before and after the content | On its own, twice. |

The three placements that travel with the AI buttons follow the AI post types. The three standalone ones have their own post-type list and a default alignment.

Placed among the AI buttons it uses a second, shorter label (**Inline button text**, default "Add on Google") — "Add {site_name} as a preferred source on Google" dwarfs neighbours called ChatGPT and Claude. It keeps a light surface in every button style, because the four-colour G cannot sit on a brand fill and stay legible. Automatic placement is skipped on any post that already contains the Google shortcode or block, and when the AI buttons are set to appear both before *and* after the content the invitation is added only once, to the last copy — repeating the buttons is useful, repeating a follow request is not.

Place it by hand with the block or the shortcode:

```
[summarizewithai_google_source]
[summarizewithai_google_source align="center" label="Add us as a preferred source on Google &raquo;"]
```

| Attribute | Values | Description |
|---|---|---|
| `label` | any text | Button text. Supports the same placeholders as the prompt. |
| `align` | `left`, `center`, `right` | Horizontal alignment. |
| `class` | CSS classes | Extra classes on the wrapper. |

#### Two button options

| Option | What renders |
|---|---|
| **This plugin's button** (default) | A plain link you control: your wording, your styling, and no request to Google from your visitors' browsers. |
| **Google's official button** | Google's own branded widget, translated and maintained by them. Light and dark themes, plus a language override. |

The official button is the one in [Google's preferred sources guide](https://developers.google.com/search/docs/appearance/preferred-sources). Choosing it loads `https://news.google.com/swg/js/v1/publisher.js` in the page head, but only on pages where the button actually appears — a site on the default setting never contacts Google at all. That third-party request is why it is opt-in.

Its markup is Google's, so the plugin's label, colours and icon settings do not apply to it.

Google renders that button inside a **cross-origin iframe** and forces the host element to `width: 100%`. Nothing inside the frame can be styled, and left alone the button spans the full content width with alignment having no effect. The plugin caps it at `--swi-gs-official-width` (default `360px`), which restores its own footprint and makes `align` work. Widen it if a translated label needs more room.

The iframe is transparent in both themes, verified against the live widget. A solid band behind the button comes from your own CSS, not from Google: themes commonly ship `iframe { background: #fff }` to hide the load flash on video embeds. The plugin overrides that for its own button, without `!important`.

#### Eligibility and expectations

Google accepts **domains and subdomains only**, never subdirectories: `example.com` and `news.example.com` are eligible, `example.com/blog` is not. The plugin normalizes whatever you enter down to the host (`https://www.example.com/blog/` becomes `example.com`) and warns you when it has dropped a path. Override the domain under the Google tab if you are on a staging copy and need the live domain in the link.

Check that your site already appears in the source preferences tool before publishing the button; the settings screen links straight to it for your domain.

Preferred Sources changes what each reader who opts in sees in Top Stories, and in AI Overviews and AI Mode where those exist. It does not change your rankings for everyone, and it does not guarantee placement.

## Placeholders

Usable anywhere in the prompt:

| Placeholder | Replaced with |
|---|---|
| `{url}` | URL of the current post or page |
| `{title}` | Post title |
| `{excerpt}` | Post excerpt |
| `{author}` | Author display name |
| `{date}` | Publish date |
| `{categories}` | Comma separated category names |
| `{language}` | Locale of the current page |
| `{site_name}` | Site title |
| `{site_url}` | Site domain, without protocol or `www.` |
| `{site_description}` | Site tagline |

The whole generated link is trimmed to 1800 characters so browsers and CDNs accept it.

## Hooks

### Filters

| Filter | Purpose |
|---|---|
| `summarizewithai_services` | Register, remove or restyle AI services. |
| `summarizewithai_placeholders` | Add or change placeholder values. |
| `summarizewithai_prompt` | Rewrite the resolved prompt before encoding. |
| `summarizewithai_current_url` | Override the URL handed to the AI. |
| `summarizewithai_should_display` | Decide per request whether the buttons render. |
| `summarizewithai_output` | Filter the rendered markup. |
| `summarizewithai_google_source_link` | Change the preferred-source URL. |
| `summarizewithai_google_source_output` | Filter the preferred-source markup. |
| `summarizewithai_default_options` | Change the plugin defaults. |
| `summarizewithai_sanitize_options` | Post-process settings before they are saved. |

### Adding a service

```php
add_filter( 'summarizewithai_services', function ( $services ) {
	$services['mistral'] = array(
		'label'      => 'Le Chat',
		'url'        => 'https://chat.mistral.ai/chat?q=',
		'icon'       => '',          // File name inside assets/img/, or '' for no icon.
		'color'      => '#fa500f',
		'hover'      => '#c93d0b',
		'is_default' => false,       // Whether it is on for a fresh install.
	);

	return $services;
} );
```

A registered service appears on the settings screen with its own on/off switch and URL field, and becomes valid in the shortcode's `services` attribute. Its colours are applied through inline CSS custom properties, so no stylesheet change is needed.

### Why not Gemini or Copilot?

Every bundled service has a prefill parameter that was checked against the live site. Two popular assistants do not, so they are deliberately absent:

- **Google Gemini** — `gemini.google.com/app` ignores both `?q=` and `?prompt=`; the chat opens empty. The browser extensions that appear to add this work by injecting the text client-side, which a link cannot do. Google AI Mode is bundled instead: it is a Search surface, so the ordinary `q=` carries the prompt and `udm=50` selects AI Mode.
- **Microsoft Copilot** — `copilot.microsoft.com/?q=` used to prefill, but Microsoft [disabled it](https://www.theregister.com/research/2026/08/18/copilot-tricked-into-telling-reseachers-how-to-hack-itself/5288857) to harden Copilot against prompt injection.

A button that opens an empty chat is worse than no button, so the **copy-prompt button** covers these tools instead: the visitor copies the prompt and pastes it wherever they like.

If a service adds a prefill parameter later, register it with the filter above — no plugin change needed. Verify the link in a logged-out browser window first.

### Hiding the buttons on certain content

```php
add_filter( 'summarizewithai_should_display', function ( $display, $post_id ) {
	return has_term( 'sponsored', 'post_tag', $post_id ) ? false : $display;
}, 10, 2 );
```

## Styling

`public.css` contains **no `!important`**, so everything it sets stays overridable. It does state every property the layout depends on — padding, radius, colour, typography, image resets — rather than trusting inherited values, because themes restyle links and content images heavily. Selectors stay at two or three classes: enough to beat the usual `.entry-content a` rules, low enough that one more class overrides them.

### Custom properties

The intended way to retheme, with no specificity fight at all. Set them on the wrapper, a single button, or `:root`:

```css
.share-with-ai {
	--swi-radius: 999px;
	--swi-gap: 6px;
	--swi-pad-y: 6px;
	--swi-pad-x: 14px;
	--swi-font-size: 12px;
	--swi-icon-size: 18px;
}

.summarize-with-claude {
	--swi-bg: #b04c2f;
	--swi-bg-hover: #8f3d26;
}
```

| Property | Applies to |
|---|---|
| `--swi-radius`, `--swi-gap`, `--swi-pad-y`, `--swi-pad-x` | Button geometry |
| `--swi-font-size`, `--swi-icon-size` | Button typography and icon |
| `--swi-bg`, `--swi-bg-hover`, `--swi-fg` | Button colours (set inline per service) |
| `--swi-gs-surface`, `--swi-gs-border`, `--swi-gs-text` | Google preferred-source button |
| `--swi-gs-official-width` | Width cap for Google's official button (default `360px`) |
| `--swi-text` | The label before the buttons |

### Classes

| Class | Element |
|---|---|
| `.share-with-ai` | Row wrapper |
| `.swi-style-filled`, `.swi-style-outline`, `.swi-style-minimal` | Style variant on the wrapper |
| `.swi-layout-inline`, `.swi-layout-stacked` | Layout variant on the wrapper |
| `.swi-icons-only` | Icon-only mode on the wrapper |
| `.share-ai-text` | Label |
| `.share-ai` | One AI button container |
| `.swi-btn` | **Every** button the plugin renders |
| `.summarize-with-ai-icon` | AI service buttons and the copy button |
| `.summarize-with-{service}` | Per-service button, e.g. `.summarize-with-claude` |
| `.swi-copy-button` | Copy-prompt button |
| `.swi-google-source` | Standalone preferred-source wrapper (`.swi-align-left`, `-center`, `-right`) |
| `.swi-google-source-inline` | Preferred-source wrapper inside the AI row |
| `.swi-google-source-button` | Preferred-source button (`--inline` / `--standalone` modifiers) |

### One deliberate exception

The Google preferred-source button carries **neither `.summarize-with-ai-icon` nor `.share-ai`**, even when it sits inside the AI row. Themes that restyle the row commonly write rules like:

```css
.share-with-ai .share-ai a,
.share-with-ai .summarize-with-ai-icon { color: #fff !important; }
```

Those are correct for AI service buttons, which have brand fills and white glyphs. Applied to the Google button they render white text on the white surface its four-colour mark requires — and `!important` cannot be outranked without more `!important`. Staying out of those selectors is the only fix that works on every theme. Use `.swi-btn` to style all buttons at once, and `.swi-google-source-button` for the Google one:

```css
/* Match a theme that rounds the AI buttons into pills. */
.share-with-ai .swi-google-source-button--inline {
	border-radius: 999px;
}
```

## Security

- Settings post to `options.php`, so WordPress handles the nonce and the `manage_options` check.
- All input passes through one sanitization callback; invalid URLs, unknown services, post types and style values fall back to defaults.
- All output is escaped, and links carry `rel="nofollow noopener noreferrer"`.
- No database queries outside the Options API.

## Privacy

The plugin sets no cookies, stores no visitor data and sends nothing to the author. Click tracking is off by default; when enabled it forwards events to the analytics library your site already loads. Clicking a button takes the visitor to a third-party AI service under that service's own terms and privacy policy.

## Development

Regenerate the translation template after changing any string:

```bash
wp i18n make-pot . languages/summarize-with-ai.pot
```

### Building a release

Double-click **`build.bat`**, or run it from a terminal. It produces
`dist/summarize-with-ai-wp-<version>.zip`, ready to upload through
**Plugins → Add New → Upload Plugin**.

The folder inside the ZIP is `summarize-with-ai-wp`, matching this repository's
slug, so WordPress treats the upload as an update to an existing install rather
than a second copy of the plugin. Nothing in the code hardcodes a folder name;
every path derives from `__FILE__`.

It refuses to build if:

- the `Version:` header, the `SWI_VERSION` constant and `readme.txt`'s
  `Stable tag:` disagree, which would ship an update that never triggers or a
  changelog describing the wrong release;
- any PHP file has a syntax error (skipped if PHP is not on `PATH`, though it
  also looks in the usual Laragon locations).

Development files are excluded: `.git`, `_dev`, `dist`, `build.bat`,
`.gitignore` and `.gitattributes` never reach the ZIP.

It needs nothing installed beyond Windows itself: `robocopy` for the copy and
PowerShell for the archive.

The archive entries are written one at a time under names the script controls,
rather than with `ZipFile::CreateFromDirectory`. On .NET Framework, which is
what Windows PowerShell 5.1 runs on, that method writes the *platform*
separator into entry names. The ZIP format requires forward slashes, so the
result looks correct on Windows and then unpacks on a Linux host as a flat pile
of files literally named `summarize-with-ai-wpdmin\settings.php`. PowerShell
7 fixed it; 5.1 is what a double-clicked `.bat` gets. After writing the archive
the script reopens it and refuses to ship if any entry still contains a
backslash.

## Changelog

See [readme.txt](readme.txt) for the full changelog.

## Contributing

Issues and pull requests are welcome at
[walterpinem/summarize-with-ai-wp](https://github.com/walterpinem/summarize-with-ai-wp).

This plugin is distributed through GitHub and the author's site; it is not on the
WordPress.org plugin directory. `readme.txt` is still kept in valid WordPress.org
format so nothing blocks a future submission.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE). **Author:** [Walter Pinem](https://walterpinem.com/).
