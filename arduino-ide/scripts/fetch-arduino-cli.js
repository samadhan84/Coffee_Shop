// Downloads the official arduino-cli binary for this OS into ./bin.
// Run with:  npm run fetch-cli
// The packaged app ships this binary, so end users never install it themselves.
const fs = require('fs');
const path = require('path');
const https = require('https');
const { execFileSync } = require('child_process');

const VERSION = process.env.ARDUINO_CLI_VERSION || '1.1.1';
const binDir = path.join(__dirname, '..', 'bin');

const targets = {
  win32: `arduino-cli_${VERSION}_Windows_64bit.zip`,
  linux: `arduino-cli_${VERSION}_Linux_64bit.tar.gz`,
  darwin: `arduino-cli_${VERSION}_macOS_64bit.tar.gz`,
};

function download(url, dest) {
  return new Promise((resolve, reject) => {
    https.get(url, (res) => {
      if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
        res.resume();
        return download(res.headers.location, dest).then(resolve, reject);
      }
      if (res.statusCode !== 200) {
        res.resume();
        return reject(new Error(`Download failed (${res.statusCode}): ${url}`));
      }
      const out = fs.createWriteStream(dest);
      res.pipe(out);
      out.on('finish', () => out.close(resolve));
      out.on('error', reject);
    }).on('error', reject);
  });
}

(async () => {
  const file = targets[process.platform];
  if (!file) throw new Error(`Unsupported platform: ${process.platform}`);
  fs.mkdirSync(binDir, { recursive: true });
  const archive = path.join(binDir, file);
  const url = `https://github.com/arduino/arduino-cli/releases/download/v${VERSION}/${file}`;
  console.log(`Downloading ${url}`);
  await download(url, archive);

  if (file.endsWith('.zip')) {
    execFileSync('powershell', ['-NoProfile', '-Command',
      `Expand-Archive -Force -Path '${archive}' -DestinationPath '${binDir}'`], { stdio: 'inherit' });
  } else {
    execFileSync('tar', ['-xzf', archive, '-C', binDir], { stdio: 'inherit' });
  }
  fs.unlinkSync(archive);
  for (const extra of ['LICENSE.txt']) {
    const p = path.join(binDir, extra);
    if (fs.existsSync(p)) fs.renameSync(p, path.join(binDir, 'arduino-cli-LICENSE.txt'));
  }
  console.log(`arduino-cli ${VERSION} is in ${binDir}`);
})().catch((err) => {
  console.error(err.message);
  process.exit(1);
});
