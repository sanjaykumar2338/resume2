# FixResume WordPress Theme

This theme packages the FixResume landing page inside a WordPress theme bundle. It is meant for brochure-style installs where you want a single-page marketing site that demonstrates the “upload resume → receive suggestions” flow.

## Files

- `style.css` – theme metadata for WordPress.
- `functions.php` – enqueues Google Fonts and the bundled stylesheet, and registers core theme supports.
- `index.php` – renders the landing page markup using WordPress helpers for titles, localisation, and footer data.
- `assets/css/main.css` – all layout and visual styling.
- `assets/images/` – favicon and touch icon used in the `<head>`.
- `languages/` – placeholder for future translations (loadable with `fixresume` text domain).

## Installation

1. Copy the entire `PixResumeTheme/` directory into your WordPress installation under `wp-content/themes/`.
2. In the WordPress admin, navigate to **Appearance → Themes** and activate **FixResume Theme**.
3. Update the Site Title and Tagline under **Settings → General** to change the brand text in the header and footer.
4. Optional: edit `index.php` to customise copy sections or convert calls-to-action into real forms/integrations.

The upload button is a visual demo only; wire it to your backend or plugin when you are ready to support resume submissions.***
