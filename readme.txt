=== Summarize with AI ===
Contributors: walterpinem, onlinestorekit
Tags: ai, share-buttons, llm, chatgpt, claude
Donate link: https://www.paypal.me/WalterPinem
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 6.8.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt

Add "Summarize with AI" buttons to your posts, plus an "Add as a preferred source on Google" button, in one click each.

== Description ==

Summarize with AI adds a row of buttons to your content. Each button opens the visitor's AI assistant of choice with a prompt already filled in, asking it to summarize the page they were reading.

Nothing is sent anywhere from your server: the buttons are plain links, the prompt is built in PHP and encoded into the link.

**Features**

* Five AI services out of the box: ChatGPT, Claude, Grok, Perplexity and Google AI Mode, each one switched on or off individually.
* A customizable prompt with placeholders for the URL, title, excerpt, author, date, categories, locale, site name, site domain and tagline.
* Automatic placement before and/or after the content of the post types you choose, with no theme editing.
* A shortcode, `[summarizewithai]`, and a block, "Summarize with AI", both backed by the same renderer.
* Three button styles (filled, outline, minimal), two layouts (inline, stacked) and an icon-only mode.
* An optional copy-prompt button for AI tools that have no shareable link.
* Optional click tracking through the `gtag()` or `dataLayer` your site already loads. Off by default.
* Assets load only on pages that actually render the buttons.
* An "Add as a preferred source on Google" button, so readers can mark your site as a preferred source in Google Search.
* Translation ready, with a `.pot` file included.

**Placeholders**

`{url}`, `{title}`, `{excerpt}`, `{author}`, `{date}`, `{categories}`, `{language}`, `{site_name}`, `{site_url}`, `{site_description}`

== Installation ==

1. Upload the `summarize-with-ai` folder to `/wp-content/plugins/`, or install the ZIP through **Plugins > Add New > Upload Plugin**.
2. Activate the plugin through the **Plugins** menu.
3. Go to **Settings > Summarize with AI** to choose your services, prompt and placement.

== Frequently Asked Questions ==

= How do I show the buttons? =

Either turn on automatic placement in **Settings > Summarize with AI > Placement**, add the "Summarize with AI" block in the editor, or place the `[summarizewithai]` shortcode wherever you want it.

= Can I show only some of the services in one place? =

Yes. `[summarizewithai services="claude,chatgpt"]` renders just those two, in that order. The block has the same control in its sidebar.

= Does the plugin call an AI API or need an API key? =

No. The buttons are ordinary links to the public web interfaces. There is no API call, no key and no cost.

= Does it collect any data? =

No. The plugin sets no cookies and sends nothing to the author. Click tracking is off by default; when you turn it on, events go only to the analytics library your own site already loads.

= The buttons do not appear on my page. =

Check that at least one service is enabled, that the page is not listed under **Exclude post IDs**, and that the post type is selected if you rely on automatic placement.

= Can I add another AI service? =

Yes, through the `summarizewithai_services` filter. See the readme on GitHub for an example.

= Why are Gemini and Copilot not included? =

Neither has a working prefill parameter. Gemini's web app ignores `?q=` and `?prompt=` and opens an empty chat, and Microsoft disabled Copilot's `?q=` to harden it against prompt injection. Google AI Mode is bundled in Gemini's place, since it is a Search surface where `q=` does carry the prompt. For anything else, use the copy-prompt button: the visitor copies the prompt and pastes it wherever they like.

= What is the "Add as a preferred source on Google" button? =

Google Search lets readers nominate sites they want to see more of. The button links to `google.com/preferences/source` for your domain, so a reader can add you in one click. It is about the site rather than any single post, so it sends no prompt and carries no AI service.

**Settings > Summarize with AI > Google** offers seven placements: manual only (the default), as the first or last button among the AI buttons, in its own row below them, or on its own before the content, after it, or both. The inline placements use a second, shorter label so the button sits comfortably next to ChatGPT and Claude. You can also place it by hand with the `[summarizewithai_google_source]` shortcode or the "Add as Preferred Source on Google" block.

= Why is my very long prompt cut off? =

The whole link, prompt included, is trimmed to 1800 characters so browsers and CDNs accept it. Shorten the prompt if you need every word to arrive.

== Screenshots ==

1. The buttons rendered below a post.
2. The settings screen.

== Changelog ==

= 1.1.0 =
* Added: an "Add as a preferred source on Google" button, with its own shortcode, block and settings tab, seven placement modes including as the first or last button among the AI buttons, its own post-type list, a short inline label and an alignment setting.
* Added: Google AI Mode as a fifth AI service.
* Added: per-service on/off switches.
* Added: automatic placement before and/or after content, per post type, replacing the copy-and-paste theme functions.
* Added: a block, "Summarize with AI", rendered server side.
* Added: shortcode attributes `services`, `label`, `style`, `layout`, `show_text`, `copy`, `prompt` and `class`.
* Added: button styles (filled, outline, minimal), stacked layout and icon-only mode.
* Added: an optional copy-prompt button.
* Added: placeholders `{title}`, `{excerpt}`, `{author}`, `{date}`, `{categories}`, `{language}` and `{site_description}`.
* Added: optional click tracking through `gtag()` or `dataLayer`.
* Added: filters `summarizewithai_services`, `summarizewithai_placeholders`, `summarizewithai_prompt`, `summarizewithai_should_display`, `summarizewithai_output`, `summarizewithai_current_url`, `summarizewithai_default_options` and `summarizewithai_sanitize_options`.
* Added: an `uninstall.php` that removes the plugin options, on single sites and across a network.
* Added: a `.pot` file and a Settings link on the Plugins screen.
* Changed: the settings screen is split into Prompt, AI Services, Placement, Appearance, Behaviour and Usage tabs. Every tab stays in one form, so a single save writes all of them, and the sections simply stack when JavaScript is unavailable.
* Changed: settings now go through the Settings API, so saving is nonce checked, sanitized in one place and redirects instead of re-posting on refresh.
* Changed: the default ChatGPT URL is now `https://chatgpt.com/?q=`, migrated automatically for sites still on the old default.
* Changed: CSS and JS load only on pages that render the buttons.
* Fixed: the settings screen offered a different default prompt from the one the buttons actually used.
* Fixed: saved prompts and labels gained stray backslashes every time the settings were saved.
* Fixed: the admin stylesheet loaded on every admin screen under the front-end handle.
* Fixed: an empty or invalid service URL produced a broken link instead of falling back to the default.
* Fixed: icons were announced twice by screen readers, once as an image and once as button text.
* Fixed: the dark colour scheme flattened every button to the same grey and hid the label on dark themes.
* Fixed: overlong prompts produced links long enough to be rejected or truncated.
* Fixed: a prompt containing {excerpt} could exhaust PHP's memory on any post without a hand-written excerpt, because generating one re-runs the content filters and re-entered the placement filter.
* Changed: the excerpt and category placeholders are resolved only when the text being rendered actually uses them.
* Changed: the Usage tab is now a set of cards with copyable snippets rather than read-only form fields.
* Changed: the stylesheet now states every property the layout depends on instead of trusting inherited values, and exposes CSS custom properties (--swi-radius, --swi-bg, --swi-gs-surface and friends) as the override surface. Still no !important anywhere.
* Fixed: the Google button was invisible on themes that force a white text colour on the AI buttons, because it wore the same classes. It now uses its own, so theme rules written for AI service buttons no longer reach it.
* Note: only assistants with a verified prefill parameter are bundled. Gemini and Copilot are excluded because theirs do not work; the copy-prompt button covers them.

= 1.0.0 =
* Initial release.
* Support for ChatGPT, Grok, Perplexity and Claude.
* Dynamic placeholders `{url}`, `{site_name}` and `{site_url}`.
* Settings panel, responsive design and internationalization support.

== Upgrade Notice ==

= 1.1.0 =
Adds Google AI Mode, an "Add as a preferred source on Google" button, per-service toggles, automatic placement, blocks, a copy-prompt button and a tabbed settings screen, and fixes several settings, escaping and accessibility bugs. Existing settings are kept.
