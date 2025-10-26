#!/usr/bin/env node
const fs = require('fs');
const path = require('path');

const destDir = path.resolve(__dirname, '..', '..', 'backend', 'public_html', 'app');
const browserDir = path.join(destDir, 'browser');

if (!fs.existsSync(browserDir)) {
  process.exit(0);
}

for (const entry of fs.readdirSync(browserDir)) {
  const from = path.join(browserDir, entry);
  const to = path.join(destDir, entry);

  if (fs.existsSync(to)) {
    fs.rmSync(to, { recursive: true, force: true });
  }

  fs.renameSync(from, to);
}

fs.rmSync(browserDir, { recursive: true, force: true });
