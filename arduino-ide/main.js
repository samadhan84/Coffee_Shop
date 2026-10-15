// Main (Node.js) process: owns the window, the file system and every arduino-cli call.
const { app, BrowserWindow, ipcMain, dialog, Menu, shell } = require('electron');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

// Espressif's board package index, so arduino-cli can find and install the ESP32 core.
const ESP32_INDEX_URL = 'https://espressif.github.io/arduino-esp32/package_esp32_index.json';

let win;
let monitorProc = null;
let busyProc = null;

function cliPath() {
  const exe = process.platform === 'win32' ? 'arduino-cli.exe' : 'arduino-cli';
  const candidates = [
    path.join(process.resourcesPath || '', 'bin', exe), // packaged app
    path.join(__dirname, 'bin', exe),                    // npm start after `npm run fetch-cli`
  ];
  return candidates.find((p) => fs.existsSync(p)) || exe; // fall back to arduino-cli on PATH
}

function send(channel, payload) {
  if (win && !win.isDestroyed()) win.webContents.send(channel, payload);
}

// Runs arduino-cli, streaming its output to the IDE console. Resolves with { code, stdout }.
function runCli(args, { stream = true } = {}) {
  return new Promise((resolve) => {
    const fullArgs = [...args, '--additional-urls', ESP32_INDEX_URL];
    if (stream) send('console', { text: `> arduino-cli ${args.join(' ')}\n`, kind: 'cmd' });
    let stdout = '';
    let proc;
    try {
      proc = spawn(cliPath(), fullArgs, { windowsHide: true });
    } catch (err) {
      send('console', { text: `${err.message}\n`, kind: 'err' });
      return resolve({ code: -1, stdout: '' });
    }
    if (stream) busyProc = proc;
    proc.stdout.on('data', (d) => {
      stdout += d.toString();
      if (stream) send('console', { text: d.toString(), kind: 'out' });
    });
    proc.stderr.on('data', (d) => send('console', { text: d.toString(), kind: 'err' }));
    proc.on('error', (err) => {
      const hint = err.code === 'ENOENT'
        ? 'arduino-cli was not found. Run "npm run fetch-cli" (developers) or reinstall the app.\n'
        : `${err.message}\n`;
      send('console', { text: hint, kind: 'err' });
      resolve({ code: -1, stdout });
    });
    proc.on('close', (code) => {
      if (busyProc === proc) busyProc = null;
      resolve({ code, stdout });
    });
  });
}

// arduino-cli needs the sketch to live in a folder of the same name (Blink/Blink.ino).
// Sketches that don't follow that rule are compiled from a copy in the app's data folder.
function prepareSketch(filePath, code) {
  if (filePath) {
    const dir = path.dirname(filePath);
    const base = path.basename(filePath, path.extname(filePath));
    if (path.basename(dir) === base && filePath.endsWith('.ino')) {
      fs.writeFileSync(filePath, code);
      return dir;
    }
  }
  const name = filePath ? path.basename(filePath, path.extname(filePath)).replace(/[^\w-]/g, '_') : 'sketch';
  const dir = path.join(app.getPath('userData'), 'build-sketches', name);
  fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(path.join(dir, `${name}.ino`), code);
  return dir;
}

// Stops the serial monitor and waits until the port is released.
function stopMonitor() {
  const proc = monitorProc;
  if (!proc) return Promise.resolve();
  monitorProc = null;
  send('monitor-state', { open: false });
  return new Promise((resolve) => {
    proc.once('close', resolve);
    proc.kill();
    setTimeout(resolve, 1500);
  });
}

// ---------- IPC handlers used by the renderer ----------

ipcMain.handle('cli:version', async () => {
  const { code, stdout } = await runCli(['version', '--format', 'json'], { stream: false });
  if (code !== 0) return null;
  try { return JSON.parse(stdout).VersionString; } catch { return stdout.trim(); }
});

ipcMain.handle('boards:ports', async () => {
  const { code, stdout } = await runCli(['board', 'list', '--format', 'json'], { stream: false });
  if (code !== 0) return [];
  try {
    const data = JSON.parse(stdout);
    const list = Array.isArray(data) ? data : data.detected_ports || [];
    return list.map((d) => ({
      address: d.port.address,
      label: d.port.label || d.port.address,
      protocol: d.port.protocol,
      board: d.matching_boards && d.matching_boards[0] ? d.matching_boards[0].name : null,
      fqbn: d.matching_boards && d.matching_boards[0] ? d.matching_boards[0].fqbn : null,
    })).filter((p) => p.protocol === 'serial');
  } catch {
    return [];
  }
});

ipcMain.handle('cores:installed', async () => {
  const { code, stdout } = await runCli(['core', 'list', '--format', 'json'], { stream: false });
  if (code !== 0) return [];
  try {
    const data = JSON.parse(stdout);
    const list = Array.isArray(data) ? data : data.platforms || [];
    return list.map((p) => p.id);
  } catch {
    return [];
  }
});

ipcMain.handle('cores:install', async (_e, core) => {
  let r = await runCli(['core', 'update-index']);
  if (r.code !== 0) return false;
  r = await runCli(['core', 'install', core]);
  return r.code === 0;
});

ipcMain.handle('lib:install', async (_e, name) => {
  const r = await runCli(['lib', 'install', name]);
  return r.code === 0;
});

ipcMain.handle('sketch:compile', async (_e, { filePath, code, fqbn }) => {
  const dir = prepareSketch(filePath, code);
  const r = await runCli(['compile', '--fqbn', fqbn, dir]);
  return r.code === 0;
});

ipcMain.handle('sketch:upload', async (_e, { filePath, code, fqbn, port }) => {
  const wasMonitoring = Boolean(monitorProc);
  await stopMonitor(); // the serial port can only be used by one program at a time
  const dir = prepareSketch(filePath, code);
  const r = await runCli(['compile', '--upload', '--fqbn', fqbn, '--port', port, dir]);
  if (wasMonitoring) send('console', { text: 'Serial monitor was closed for the upload — reopen it if needed.\n', kind: 'info' });
  return r.code === 0;
});

ipcMain.handle('sketch:cancel', () => {
  if (busyProc) busyProc.kill();
});

ipcMain.handle('monitor:open', async (_e, { port, baud }) => {
  await stopMonitor();
  const args = ['monitor', '--port', port, '--config', `baudrate=${baud}`, '--quiet', '--raw'];
  monitorProc = spawn(cliPath(), args, { windowsHide: true });
  const proc = monitorProc;
  proc.stdout.on('data', (d) => send('monitor-data', d.toString()));
  proc.stderr.on('data', (d) => send('monitor-data', d.toString()));
  proc.on('error', (err) => send('monitor-data', `\n[${err.message}]\n`));
  proc.on('close', () => {
    if (monitorProc === proc) {
      monitorProc = null;
      send('monitor-state', { open: false });
    }
  });
  send('monitor-state', { open: true, port, baud });
  return true;
});

ipcMain.handle('monitor:send', (_e, text) => {
  if (monitorProc) monitorProc.stdin.write(text);
});

ipcMain.handle('monitor:close', () => stopMonitor());

ipcMain.handle('file:open', async () => {
  const res = await dialog.showOpenDialog(win, {
    filters: [{ name: 'Arduino sketch', extensions: ['ino', 'cpp', 'h', 'c'] }],
    properties: ['openFile'],
  });
  if (res.canceled || !res.filePaths[0]) return null;
  const filePath = res.filePaths[0];
  return { filePath, code: fs.readFileSync(filePath, 'utf8') };
});

ipcMain.handle('file:save', async (_e, { filePath, code, saveAs }) => {
  let target = filePath;
  if (!target || saveAs) {
    const res = await dialog.showSaveDialog(win, {
      defaultPath: path.join(app.getPath('documents'), 'Arduino', 'sketch', 'sketch.ino'),
      filters: [{ name: 'Arduino sketch', extensions: ['ino'] }],
    });
    if (res.canceled || !res.filePath) return null;
    target = res.filePath;
    // Keep the Arduino layout: put MySketch.ino inside a MySketch folder.
    const base = path.basename(target, path.extname(target));
    if (path.basename(path.dirname(target)) !== base) target = path.join(path.dirname(target), base, `${base}.ino`);
  }
  fs.mkdirSync(path.dirname(target), { recursive: true });
  fs.writeFileSync(target, code);
  return target;
});

// ---------- Window ----------

function buildMenu() {
  const menu = Menu.buildFromTemplate([
    {
      label: 'File',
      submenu: [
        { label: 'New', accelerator: 'CmdOrCtrl+N', click: () => send('menu', 'new') },
        { label: 'Open…', accelerator: 'CmdOrCtrl+O', click: () => send('menu', 'open') },
        { label: 'Save', accelerator: 'CmdOrCtrl+S', click: () => send('menu', 'save') },
        { label: 'Save As…', accelerator: 'CmdOrCtrl+Shift+S', click: () => send('menu', 'saveAs') },
        { type: 'separator' },
        { role: 'quit' },
      ],
    },
    { role: 'editMenu' },
    {
      label: 'Sketch',
      submenu: [
        { label: 'Verify / Compile', accelerator: 'CmdOrCtrl+R', click: () => send('menu', 'verify') },
        { label: 'Upload', accelerator: 'CmdOrCtrl+U', click: () => send('menu', 'upload') },
      ],
    },
    {
      label: 'Tools',
      submenu: [
        { label: 'Serial Monitor', accelerator: 'CmdOrCtrl+Shift+M', click: () => send('menu', 'monitor') },
        { label: 'Board Setup (install cores)…', click: () => send('menu', 'setup') },
        { label: 'Install Library…', click: () => send('menu', 'library') },
      ],
    },
    {
      label: 'View',
      submenu: [{ role: 'zoomIn' }, { role: 'zoomOut' }, { role: 'resetZoom' }, { type: 'separator' }, { role: 'toggleDevTools' }],
    },
    {
      label: 'Help',
      submenu: [
        { label: 'Arduino reference', click: () => shell.openExternal('https://www.arduino.cc/reference/en/') },
        { label: 'ESP32 Arduino docs', click: () => shell.openExternal('https://docs.espressif.com/projects/arduino-esp32/en/latest/') },
      ],
    },
  ]);
  Menu.setApplicationMenu(menu);
}

app.whenReady().then(() => {
  win = new BrowserWindow({
    width: 1200,
    height: 800,
    minWidth: 800,
    minHeight: 500,
    backgroundColor: '#1e1f22',
    title: 'Mini Arduino IDE',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
    },
  });
  buildMenu();
  win.loadFile(path.join(__dirname, 'src', 'index.html'));
});

app.on('window-all-closed', () => {
  stopMonitor();
  if (busyProc) busyProc.kill();
  app.quit();
});
