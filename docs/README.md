# Documentation

This directory contains the GitHub Pages documentation for the Hybrid Search Comparison project.

## Local Development

### Prerequisites

- Ruby (version 2.7 or higher)
- Bundler

### Setup

```bash
cd docs
bundle install
```

### Run Locally

```bash
bundle exec jekyll serve
```

Then open http://localhost:4000/symfony-hybrid-search-comparison-postgres-typesense/ in your browser.

### Live Reload

```bash
bundle exec jekyll serve --livereload
```

## Deployment

The documentation is automatically deployed to GitHub Pages when you push to the `main` branch.

### Enable GitHub Pages

1. Go to your repository on GitHub
2. Click on "Settings"
3. Scroll down to "Pages" in the left sidebar
4. Under "Source", select "Deploy from a branch"
5. Select branch: `main`
6. Select folder: `/docs`
7. Click "Save"

The documentation will be available at:
https://ahmed-bhs.github.io/symfony-hybrid-search-comparison-postgres-typesense/

## Structure

```
docs/
├── _config.yml          # Jekyll configuration
├── Gemfile             # Ruby dependencies
├── index.md            # Home page
├── quick-start.md      # Quick start guide
├── architecture.md     # Architecture deep dive
├── symfony-ai.md       # Symfony AI HybridStore guide
├── typesense.md        # Typesense guide
└── comparison.md       # Performance comparison
```

## Theme

This documentation uses the [Just the Docs](https://just-the-docs.github.io/just-the-docs/) theme.

## Customization

### Change Color Scheme

Edit `_config.yml`:

```yaml
color_scheme: dark  # or light
```

### Add Logo

1. Add your logo to `assets/images/logo.png`
2. Edit `_config.yml`:

```yaml
logo: "/assets/images/logo.png"
```

### Edit Navigation Order

In each markdown file, edit the front matter:

```yaml
---
nav_order: 1  # Lower numbers appear first
---
```

## License

MIT
