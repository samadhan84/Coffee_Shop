// Exposes a small, safe API (window.ide) to the page. The page itself has no Node.js access.
const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('ide', {
  cliVersion: () => ipcRenderer.invoke('cli:version'),
  listPorts: () => ipcRenderer.invoke('boards:ports'),
  installedCores: () => ipcRenderer.invoke('cores:installed'),
  installCore: (core) => ipcRenderer.invoke('cores:install', core),
  installLibrary: (name) => ipcRenderer.invoke('lib:install', name),
  compile: (opts) => ipcRenderer.invoke('sketch:compile', opts),
  upload: (opts) => ipcRenderer.invoke('sketch:upload', opts),
  cancel: () => ipcRenderer.invoke('sketch:cancel'),
  openMonitor: (opts) => ipcRenderer.invoke('monitor:open', opts),
  sendMonitor: (text) => ipcRenderer.invoke('monitor:send', text),
  closeMonitor: () => ipcRenderer.invoke('monitor:close'),
  openFile: () => ipcRenderer.invoke('file:open'),
  saveFile: (opts) => ipcRenderer.invoke('file:save', opts),
  on: (channel, fn) => {
    const allowed = ['console', 'monitor-data', 'monitor-state', 'menu'];
    if (allowed.includes(channel)) ipcRenderer.on(channel, (_e, payload) => fn(payload));
  },
});
