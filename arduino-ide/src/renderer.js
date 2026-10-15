// Page logic: editor, toolbar, output console and serial monitor.
const $ = (id) => document.getElementById(id);

const CORE_INFO = {
  'arduino:avr': { title: 'Arduino AVR Boards', desc: 'Uno, Nano, Mega, Leonardo, Pro Mini (≈ 50 MB)' },
  'esp32:esp32': { title: 'ESP32 by Espressif', desc: 'ESP32, ESP32-S3, ESP32-C3, ESP32-CAM (≈ 500 MB+)' },
};

const state = {
  filePath: null,
  dirty: false,
  busy: false,
  monitorOpen: false,
  installedCores: [],
  portSignature: null,
};

const editor = CodeMirror.fromTextArea($('editor'), {
  mode: 'text/x-c++src',
  theme: 'material-darker',
  lineNumbers: true,
  indentUnit: 2,
  tabSize: 2,
  matchBrackets: true,
  autoCloseBrackets: true,
  styleActiveLine: true,
});
editor.setValue(EXAMPLES['Blink (any board)']);
editor.clearHistory();
editor.on('change', () => setDirty(true));
editor.on('cursorActivity', () => {
  const c = editor.getCursor();
  $('cursorPos').textContent = `Ln ${c.line + 1}, Col ${c.ch + 1}`;
});

// ---------- helpers ----------

function setDirty(d) {
  state.dirty = d;
  $('dirty').classList.toggle('hidden', !d);
}

function setFile(filePath) {
  state.filePath = filePath;
  const name = filePath ? filePath.split(/[\\/]/).pop() : 'sketch.ino';
  $('fileName').textContent = name;
  document.title = `${name} — Mini Arduino IDE`;
}

function status(text) { $('statusText').textContent = text; }

function log(text, kind = 'out') {
  const out = $('output');
  const span = document.createElement('span');
  span.className = kind;
  span.textContent = text;
  out.appendChild(span);
  out.scrollTop = out.scrollHeight;
}

function showPane(name) {
  document.querySelectorAll('.paneTabs [data-pane]').forEach((b) => b.classList.toggle('active', b.dataset.pane === name));
  $('output').classList.toggle('hidden', name !== 'output');
  $('monitor').classList.toggle('hidden', name !== 'monitor');
}

function currentBoard() { return BOARDS.find((b) => b.fqbn === $('board').value); }

function setBusy(busy, label) {
  state.busy = busy;
  $('btnVerify').disabled = busy;
  $('btnUpload').disabled = busy;
  $('btnCancel').classList.toggle('hidden', !busy);
  if (label) status(label);
}

// Small modal used for confirmations, the setup screen and the library prompt.
function modal({ title, body, okText = 'OK', cancelText = 'Cancel', hideOk = false }) {
  return new Promise((resolve) => {
    $('dialogTitle').textContent = title;
    const bodyEl = $('dialogBody');
    bodyEl.innerHTML = '';
    if (typeof body === 'string') { const p = document.createElement('p'); p.textContent = body; bodyEl.appendChild(p); } else bodyEl.appendChild(body);
    $('dialogOk').textContent = okText;
    $('dialogOk').classList.toggle('hidden', hideOk);
    $('dialogCancel').textContent = cancelText;
    $('dialog').classList.remove('hidden');
    const done = (val) => {
      $('dialog').classList.add('hidden');
      $('dialogOk').onclick = $('dialogCancel').onclick = null;
      resolve(val);
    };
    $('dialogOk').onclick = () => done(true);
    $('dialogCancel').onclick = () => done(false);
    const input = bodyEl.querySelector('input');
    if (input) { input.focus(); input.onkeydown = (e) => { if (e.key === 'Enter') done(true); }; }
  });
}

async function confirmDiscard() {
  if (!state.dirty) return true;
  return modal({ title: 'Unsaved changes', body: 'Discard the changes to the current sketch?', okText: 'Discard' });
}

// ---------- boards, ports, cores ----------

function fillBoards() {
  const sel = $('board');
  const groups = {};
  for (const b of BOARDS) {
    if (!groups[b.group]) {
      groups[b.group] = document.createElement('optgroup');
      groups[b.group].label = b.group;
      sel.appendChild(groups[b.group]);
    }
    const o = document.createElement('option');
    o.value = b.fqbn;
    o.textContent = b.name;
    groups[b.group].appendChild(o);
  }
  sel.value = localStorage.getItem('board') || BOARDS[0].fqbn;
  if (!sel.value) sel.value = BOARDS[0].fqbn;
}

function fillExamples() {
  for (const name of Object.keys(EXAMPLES)) {
    const o = document.createElement('option');
    o.value = o.textContent = name;
    $('examples').appendChild(o);
  }
}

async function refreshPorts() {
  const sel = $('port');
  const previous = sel.value || localStorage.getItem('port');
  const ports = await window.ide.listPorts();
  const signature = JSON.stringify(ports);
  if (signature === state.portSignature) return; // unchanged: don't disturb an open dropdown
  state.portSignature = signature;
  sel.innerHTML = '';
  if (!ports.length) {
    sel.innerHTML = '<option value="">(no port — plug in a board)</option>';
    return;
  }
  for (const p of ports) {
    const o = document.createElement('option');
    o.value = p.address;
    o.textContent = p.board ? `${p.label} — ${p.board}` : p.label;
    o.dataset.fqbn = p.fqbn || '';
    sel.appendChild(o);
  }
  if (ports.some((p) => p.address === previous)) sel.value = previous;
  autoPickBoard();
}

// An Uno/Mega etc. identifies itself over USB; ESP32 boards use generic USB chips and don't.
function autoPickBoard() {
  const opt = $('port').selectedOptions[0];
  const detected = opt && opt.dataset.fqbn;
  const match = detected && BOARDS.find((b) => b.fqbn.startsWith(detected));
  if (match && $('board').value !== match.fqbn) {
    $('board').value = match.fqbn;
    onBoardChange();
  }
}

async function refreshCores() {
  state.installedCores = await window.ide.installedCores();
  updateCoreBanner();
}

function updateCoreBanner() {
  const b = currentBoard();
  const missing = b && !state.installedCores.includes(b.core);
  $('coreBanner').classList.toggle('hidden', !missing);
  if (missing) $('coreBannerText').textContent = `"${CORE_INFO[b.core].title}" is not installed yet — needed to compile for ${b.name}.`;
}

function onBoardChange() {
  const b = currentBoard();
  localStorage.setItem('board', b.fqbn);
  $('statusBoard').textContent = b.name;
  updateCoreBanner();
}

async function installCore(core) {
  setBusy(true, `Installing ${CORE_INFO[core].title}…`);
  showPane('output');
  log(`\nInstalling ${CORE_INFO[core].title}. The first install downloads compilers and can take several minutes.\n`, 'info');
  const ok = await window.ide.installCore(core);
  setBusy(false, ok ? `${CORE_INFO[core].title} installed` : 'Install failed');
  log(ok ? `\n✔ ${CORE_INFO[core].title} installed.\n` : '\n✖ Install failed — check your internet connection and try again.\n', ok ? 'ok' : 'err');
  await refreshCores();
}

async function openSetup() {
  await refreshCores();
  const wrap = document.createElement('div');
  const p = document.createElement('p');
  p.textContent = 'Install support for the boards you use. This is a one-time download.';
  wrap.appendChild(p);
  for (const [core, info] of Object.entries(CORE_INFO)) {
    const row = document.createElement('div');
    row.className = 'coreRow';
    const installed = state.installedCores.includes(core);
    row.innerHTML = `<div><strong></strong><small></small></div>`;
    row.querySelector('strong').textContent = info.title;
    row.querySelector('small').textContent = info.desc;
    const btn = document.createElement('button');
    btn.textContent = installed ? 'Installed ✔ (update)' : 'Install';
    if (!installed) btn.className = 'primary';
    btn.onclick = () => { $('dialogCancel').click(); installCore(core); };
    row.appendChild(btn);
    wrap.appendChild(row);
  }
  await modal({ title: 'Board Setup', body: wrap, cancelText: 'Close', hideOk: true });
}

async function installLibrary() {
  const wrap = document.createElement('div');
  wrap.innerHTML = '<p>Library name exactly as in the Arduino Library Manager, e.g. <em>Servo</em>, <em>DHT sensor library</em>, <em>Adafruit NeoPixel</em>, <em>PubSubClient</em>.</p><input type="text">';
  if (!(await modal({ title: 'Install Library', body: wrap, okText: 'Install' }))) return;
  const name = wrap.querySelector('input').value.trim();
  if (!name) return;
  setBusy(true, `Installing library ${name}…`);
  showPane('output');
  const ok = await window.ide.installLibrary(name);
  setBusy(false, ok ? `Library ${name} installed` : 'Library install failed');
  log(ok ? `\n✔ Library "${name}" installed.\n` : `\n✖ Could not install "${name}".\n`, ok ? 'ok' : 'err');
}

// ---------- sketch actions ----------

async function ensureReady(needPort) {
  const b = currentBoard();
  if (!state.installedCores.includes(b.core)) {
    const go = await modal({ title: 'Board support missing', body: `${CORE_INFO[b.core].title} must be installed before you can compile for ${b.name}. Install it now?`, okText: 'Install' });
    if (go) await installCore(b.core);
    if (!state.installedCores.includes(b.core)) return false;
  }
  if (needPort && !$('port').value) {
    await modal({ title: 'No port selected', body: 'Connect your board with a USB cable, press ⟳ to refresh, and choose its COM port.', hideOk: true, cancelText: 'OK' });
    return false;
  }
  return true;
}

async function verify() {
  if (state.busy || !(await ensureReady(false))) return;
  const b = currentBoard();
  $('output').textContent = '';
  showPane('output');
  setBusy(true, `Compiling for ${b.name}…`);
  const ok = await window.ide.compile({ filePath: state.filePath, code: editor.getValue(), fqbn: b.fqbn });
  setBusy(false, ok ? 'Done compiling' : 'Compilation error');
  log(ok ? '\n✔ Done compiling.\n' : '\n✖ Compilation error — see messages above.\n', ok ? 'ok' : 'err');
}

async function upload() {
  if (state.busy || !(await ensureReady(true))) return;
  const b = currentBoard();
  const port = $('port').value;
  localStorage.setItem('port', port);
  $('output').textContent = '';
  showPane('output');
  setBusy(true, `Uploading to ${b.name} on ${port}…`);
  if (b.core === 'esp32:esp32') log('Tip: if the upload hangs at "Connecting...", hold the BOOT button on the ESP32 until it starts writing.\n', 'info');
  const ok = await window.ide.upload({ filePath: state.filePath, code: editor.getValue(), fqbn: b.fqbn, port });
  setBusy(false, ok ? 'Done uploading' : 'Upload failed');
  log(ok ? '\n✔ Done uploading.\n' : '\n✖ Upload failed — check the board, the port and the cable.\n', ok ? 'ok' : 'err');
}

async function newSketch() {
  if (!(await confirmDiscard())) return;
  editor.setValue('void setup() {\n  // put your setup code here, to run once:\n\n}\n\nvoid loop() {\n  // put your main code here, to run repeatedly:\n\n}\n');
  editor.clearHistory();
  setFile(null);
  setDirty(false);
}

async function openSketch() {
  if (!(await confirmDiscard())) return;
  const res = await window.ide.openFile();
  if (!res) return;
  editor.setValue(res.code);
  editor.clearHistory();
  setFile(res.filePath);
  setDirty(false);
}

async function saveSketch(saveAs = false) {
  const saved = await window.ide.saveFile({ filePath: state.filePath, code: editor.getValue(), saveAs });
  if (!saved) return;
  setFile(saved);
  setDirty(false);
  status(`Saved ${saved}`);
}

// ---------- serial monitor ----------

async function toggleMonitor() {
  if (state.monitorOpen) return window.ide.closeMonitor();
  const port = $('port').value;
  if (!port) {
    await modal({ title: 'No port selected', body: 'Choose the COM port of your board first.', hideOk: true, cancelText: 'OK' });
    return;
  }
  $('monitorOut').textContent = '';
  await window.ide.openMonitor({ port, baud: $('baud').value });
}

function sendMonitor() {
  const input = $('monitorInput');
  if (!state.monitorOpen || !input.value) return;
  window.ide.sendMonitor(input.value + $('lineEnding').value);
  input.value = '';
}

window.ide.on('monitor-data', (text) => {
  const out = $('monitorOut');
  out.textContent += text;
  if (out.textContent.length > 200000) out.textContent = out.textContent.slice(-150000);
  if ($('autoscroll').checked) out.scrollTop = out.scrollHeight;
});

window.ide.on('monitor-state', ({ open, port, baud }) => {
  state.monitorOpen = open;
  $('btnMonitorToggle').textContent = open ? 'Disconnect' : 'Connect';
  $('btnMonitorToggle').classList.toggle('primary', !open);
  status(open ? `Serial monitor on ${port} @ ${baud} baud` : 'Serial monitor closed');
});

window.ide.on('console', ({ text, kind }) => log(text, kind));

window.ide.on('menu', (cmd) => {
  const actions = {
    new: newSketch, open: openSketch, save: () => saveSketch(false), saveAs: () => saveSketch(true),
    verify, upload, setup: openSetup, library: installLibrary,
    monitor: () => { showPane('monitor'); if (!state.monitorOpen) toggleMonitor(); },
  };
  if (actions[cmd]) actions[cmd]();
});

// ---------- wiring ----------

$('btnVerify').onclick = verify;
$('btnUpload').onclick = upload;
$('btnCancel').onclick = () => window.ide.cancel();
$('btnNew').onclick = newSketch;
$('btnOpen').onclick = openSketch;
$('btnSave').onclick = () => saveSketch(false);
$('btnRefresh').onclick = refreshPorts;
$('btnSetup').onclick = openSetup;
$('btnInstallCore').onclick = () => installCore(currentBoard().core);
$('btnMonitor').onclick = () => { showPane('monitor'); if (!state.monitorOpen) toggleMonitor(); };
$('btnMonitorToggle').onclick = toggleMonitor;
$('btnClear').onclick = () => {
  if ($('monitor').classList.contains('hidden')) $('output').textContent = '';
  else $('monitorOut').textContent = '';
};
$('monitorInput').onkeydown = (e) => { if (e.key === 'Enter') sendMonitor(); };
$('baud').onchange = () => { if (state.monitorOpen) window.ide.openMonitor({ port: $('port').value, baud: $('baud').value }); };
$('board').onchange = onBoardChange;
$('port').onchange = () => { localStorage.setItem('port', $('port').value); autoPickBoard(); };
$('examples').onchange = async (e) => {
  const name = e.target.value;
  e.target.value = '';
  if (!name || !(await confirmDiscard())) return;
  editor.setValue(EXAMPLES[name]);
  editor.clearHistory();
  setFile(null);
  setDirty(false);
};
document.querySelectorAll('.paneTabs [data-pane]').forEach((b) => { b.onclick = () => showPane(b.dataset.pane); });

// Drag the bar between the editor and the output panel to resize them.
$('splitter').onmousedown = (e) => {
  const startY = e.clientY;
  const startH = $('bottom').offsetHeight;
  const move = (ev) => { $('bottom').style.height = `${Math.max(60, startH - (ev.clientY - startY))}px`; editor.refresh(); };
  const up = () => { document.removeEventListener('mousemove', move); document.removeEventListener('mouseup', up); };
  document.addEventListener('mousemove', move);
  document.addEventListener('mouseup', up);
};

(async function init() {
  fillBoards();
  fillExamples();
  setFile(null);
  setDirty(false);
  onBoardChange();
  const version = await window.ide.cliVersion();
  $('cliVersion').textContent = version ? `arduino-cli ${version}` : 'arduino-cli not found';
  if (!version) log('arduino-cli was not found. Developers: run "npm run fetch-cli". Users: reinstall the app.\n', 'err');
  await refreshCores();
  await refreshPorts();
  if (!state.installedCores.length) openSetup();
  setInterval(() => { if (!state.busy && !state.monitorOpen) refreshPorts(); }, 4000);
})();
