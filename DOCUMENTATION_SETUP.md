# Documentation GitHub Pages Setup

This file explains how to set up and deploy the GitHub Pages documentation for this project.

## What's Been Created

A complete GitHub Pages documentation site using Jekyll and the Just the Docs theme:

```
docs/
├── _config.yml          # Jekyll configuration
├── Gemfile             # Ruby dependencies
├── .gitignore          # Ignore Jekyll build files
├── README.md           # Documentation about the docs
├── index.md            # Home page (overview)
├── quick-start.md      # Quick start guide
├── architecture.md     # Architecture deep dive
├── symfony-ai.md       # Symfony AI configuration guide
├── typesense.md        # Typesense configuration guide
└── comparison.md       # Performance comparison

.github/workflows/
└── pages.yml           # GitHub Actions workflow for auto-deployment
```

## Features

✅ **Professional theme** - Just the Docs theme (clean, searchable, mobile-friendly)
✅ **Auto-deployment** - Automatic build and deploy via GitHub Actions
✅ **Search functionality** - Built-in search across all documentation
✅ **Navigation** - Automatic navigation menu with customizable order
✅ **Syntax highlighting** - Code blocks with syntax highlighting
✅ **Responsive design** - Works on desktop, tablet, and mobile
✅ **Edit on GitHub** - Every page has "Edit this page on GitHub" link
✅ **Back to top** - Smooth scroll to top on long pages
✅ **Callouts** - Highlighted boxes for notes, warnings, highlights

## Setup Instructions

### Step 1: Push to GitHub

```bash
git add docs/ .github/
git commit -m "docs: add GitHub Pages documentation"
git push origin main
```

### Step 2: Enable GitHub Pages

1. Go to your repository on GitHub: https://github.com/ahmed-bhs/symfony-hybrid-search-comparison-postgres-typesense
2. Click on **Settings** tab
3. Click on **Pages** in the left sidebar
4. Under **Source**, select:
   - **Deploy from a branch**
   - Branch: `main`
   - Folder: `/docs`
5. Click **Save**

### Step 3: Wait for Deployment

GitHub Actions will automatically build and deploy your site. You can monitor progress:

1. Go to the **Actions** tab in your repository
2. Look for the "Deploy GitHub Pages" workflow
3. Wait for it to complete (usually 1-2 minutes)

### Step 4: Access Your Documentation

Once deployed, your documentation will be available at:

**https://ahmed-bhs.github.io/symfony-hybrid-search-comparison-postgres-typesense/**

## Local Development

If you want to preview the documentation locally before pushing:

### Prerequisites

Install Ruby and Bundler:

```bash
# On Ubuntu/Debian
sudo apt-get install ruby-full build-essential zlib1g-dev

# On macOS
brew install ruby

# Install Bundler
gem install bundler
```

### Run Locally

```bash
cd docs
bundle install
bundle exec jekyll serve
```

Then open: http://localhost:4000/symfony-hybrid-search-comparison-postgres-typesense/

### Live Reload

For automatic page refresh when you edit files:

```bash
bundle exec jekyll serve --livereload
```

## Customization

### Change Repository Name

If your repository name is different, update these files:

**docs/_config.yml:**
```yaml
baseurl: "/your-repo-name"
repository: your-username/your-repo-name
```

### Change Color Scheme

**docs/_config.yml:**
```yaml
color_scheme: dark  # Options: light, dark
```

### Add Logo

1. Create `docs/assets/images/` directory
2. Add your logo as `docs/assets/images/logo.png`
3. Update **docs/_config.yml:**
```yaml
logo: "/assets/images/logo.png"
```

### Change Navigation Order

Edit the `nav_order` in each file's front matter:

```yaml
---
nav_order: 1  # Lower numbers appear first
---
```

Current order:
1. Home (index.md)
2. Quick Start
3. Architecture
4. Symfony AI Guide
5. Typesense Guide
6. Performance Comparison

### Add Google Analytics

**docs/_config.yml:**
```yaml
ga_tracking: UA-XXXXXXXX-X
ga_tracking_anonymize_ip: true
```

## Documentation Structure

### Home Page (index.md)
- Project overview
- Quick comparison table
- Key features
- Quick start (simplified)
- Links to detailed guides

### Quick Start (quick-start.md)
- Prerequisites
- Step-by-step installation
- First searches
- Troubleshooting
- Common issues

### Architecture (architecture.md)
- System overview diagrams
- Data flow (indexing and search)
- Database schemas
- RRF algorithm explanation
- Search strategies
- Performance optimizations

### Symfony AI Guide (symfony-ai.md)
- Installation
- Configuration reference
- Database schema
- Usage examples
- Tuning parameters
- Advanced features
- Troubleshooting

### Typesense Guide (typesense.md)
- Installation
- Configuration reference
- Schema definition
- Usage examples
- Advanced search
- Tuning parameters
- Troubleshooting

### Performance Comparison (comparison.md)
- Test environment
- Import performance
- Search performance
- Resource usage
- Scaling analysis
- Cost analysis
- Use case recommendations

## Theme Features

### Callouts

```markdown
{: .note }
> This is a note callout

{: .warning }
> This is a warning callout

{: .highlight }
> This is a highlight callout
```

### Navigation

```markdown
{: .no_toc }  # Exclude heading from navigation

## Table of contents
{: .no_toc .text-delta }

1. TOC
{:toc}
```

### Buttons

```markdown
[Get started](#link){: .btn .btn-primary }
[View on GitHub](#link){: .btn }
```

### Code Blocks

```markdown
```bash
# Command
curl http://localhost:8000
\`\`\`

```yaml
# YAML configuration
key: value
\`\`\`
```

## Troubleshooting

### Build Fails

Check the GitHub Actions logs:
1. Go to **Actions** tab
2. Click on the failed workflow
3. Check error messages

Common issues:
- Invalid YAML in front matter
- Missing dependencies in Gemfile
- Invalid markdown syntax

### Pages Not Updating

1. Clear GitHub Pages cache:
   - Settings → Pages → Remove site → Save
   - Wait 1 minute
   - Re-enable Pages

2. Force rebuild:
   - Go to Actions
   - Click "Deploy GitHub Pages"
   - Click "Run workflow"

### Local Preview Not Working

```bash
# Clean build files
cd docs
rm -rf _site .jekyll-cache

# Reinstall dependencies
bundle install

# Rebuild
bundle exec jekyll serve
```

## Next Steps

1. **Push to GitHub** - Commit and push all documentation files
2. **Enable Pages** - Configure GitHub Pages in repository settings
3. **Customize** - Update baseurl, repository name, colors as needed
4. **Share** - Add documentation link to your README.md

## Resources

- [Just the Docs Documentation](https://just-the-docs.github.io/just-the-docs/)
- [Jekyll Documentation](https://jekyllrb.com/docs/)
- [GitHub Pages Documentation](https://docs.github.com/en/pages)
- [Markdown Guide](https://www.markdownguide.org/)

## License

MIT
