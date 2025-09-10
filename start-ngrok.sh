#!/bin/bash

# GoHighLevel + Tap Integration - ngrok Setup Script
# This script helps you expose your localhost to the internet for GoHighLevel testing

echo "🚀 Starting ngrok for GoHighLevel + Tap Integration"
echo "=================================================="

# Check if ngrok is installed
if ! command -v ngrok &> /dev/null; then
    echo "❌ ngrok is not installed!"
    echo "Please install ngrok first:"
    echo "  - Download from: https://ngrok.com/download"
    echo "  - Or install via Homebrew: brew install ngrok"
    echo "  - Or install via npm: npm install -g ngrok"
    exit 1
fi

# Check if Laravel server is running
if ! curl -s http://localhost:8000 > /dev/null; then
    echo "❌ Laravel server is not running on port 8000!"
    echo "Please start your Laravel server first:"
    echo "  php artisan serve --host=0.0.0.0 --port=8000"
    exit 1
fi

echo "✅ Laravel server is running on localhost:8000"
echo "🌐 Starting ngrok tunnel..."

# Start ngrok
ngrok http 8000 --log=stdout
