// Installs the AVR and ESP32 cores with the bundled arduino-cli and compiles the built-in
// examples for an Uno and an ESP32. Used by CI; run locally with:  node scripts/smoke-test.js
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');

const ESP32_INDEX_URL = 'https://espressif.github.io/arduino-esp32/package_esp32_index.json';
const cli = path.join(__dirname, '..', 'bin', process.platform === 'win32' ? 'arduino-cli.exe' : 'arduino-cli');

global.window = {};
require('../src/boards.js');
const { EXAMPLES } = global.window;

function run(args) {
  console.log(`> arduino-cli ${args.join(' ')}`);
  execFileSync(cli, [...args, '--additional-urls', ESP32_INDEX_URL], { stdio: 'inherit' });
}

const jobs = [
  { fqbn: 'arduino:avr:uno', examples: ['Blink (any board)', 'Serial Hello (any board)', 'Analog Read (Uno A0)'] },
  { fqbn: 'esp32:esp32:esp32', examples: ['Blink (any board)', 'ESP32 WiFi Scan', 'ESP32 Web Server (LED on/off)', 'ESP32 Touch Sensor'] },
];

run(['core', 'update-index']);
run(['core', 'install', 'arduino:avr']);
run(['core', 'install', 'esp32:esp32']);

const root = fs.mkdtempSync(path.join(os.tmpdir(), 'ide-smoke-'));
for (const { fqbn, examples } of jobs) {
  for (const name of examples) {
    const sketch = name.replace(/\W+/g, '_');
    const dir = path.join(root, sketch);
    fs.mkdirSync(dir, { recursive: true });
    fs.writeFileSync(path.join(dir, `${sketch}.ino`), EXAMPLES[name]);
    run(['compile', '--fqbn', fqbn, dir]);
  }
}
console.log('All examples compiled.');
