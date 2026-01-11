#!/bin/bash

echo "🚀 Starting Jekyll documentation server..."
echo ""
echo "Prerequisites:"
echo "  - Ruby 2.7+ installed"
echo "  - Bundler installed (gem install bundler)"
echo ""

# Check if Ruby is installed
if ! command -v ruby &> /dev/null; then
    echo "❌ Ruby is not installed!"
    echo "Install it with:"
    echo "  Ubuntu/Debian: sudo apt-get install ruby-full build-essential zlib1g-dev"
    echo "  macOS: brew install ruby"
    exit 1
fi

echo "✅ Ruby version: $(ruby -v)"

# Check if Bundler is installed
if ! command -v bundle &> /dev/null; then
    echo "❌ Bundler is not installed!"
    echo "Install it with: gem install bundler"
    exit 1
fi

echo "✅ Bundler version: $(bundle -v)"
echo ""

# Install dependencies if needed
if [ ! -d "vendor/bundle" ]; then
    echo "📦 Installing dependencies..."
    bundle install
    echo ""
fi

# Serve the site
echo "🌐 Starting Jekyll server..."
echo ""
echo "The site will be available at:"
echo "  http://localhost:4000/symfony-hybrid-search-comparison-postgres-typesense/"
echo ""
echo "Press Ctrl+C to stop the server"
echo ""

bundle exec jekyll serve --livereload
