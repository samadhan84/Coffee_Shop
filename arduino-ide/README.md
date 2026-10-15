# Mini Arduino IDE (Windows)

A small Arduino IDE style desktop app. Write a sketch, then compile and upload it to an
**Arduino Uno / Nano / Mega / Leonardo / Pro Mini** or an **ESP32 / ESP32-S3 / ESP32-C3 / ESP32-CAM**,
and watch the output in the built-in **Serial Monitor**.

It is built with Electron and uses the official [`arduino-cli`](https://arduino.github.io/arduino-cli/),
the same engine the real Arduino IDE 2 uses, which ships inside the app.

## Download

Every push to `arduino-ide/` builds the app on GitHub Actions (**Actions → Arduino IDE (Windows)**).
- **Artifact** `MiniArduinoIDE-windows` on each run: an installer (`…-nsis.exe`) and a portable exe that runs without installing.
- On the default branch (or when run by hand) the same files are published as the **`arduino-ide-v1.0`** release.

Windows SmartScreen may warn because the exe isn't code-signed: click **More info → Run anyway**.

## First run

1. The **Board Setup** window opens. Click **Install** next to *Arduino AVR Boards* (Uno etc.)
   and/or *ESP32 by Espressif*. This is a one-time download that needs internet (ESP32 is large, several hundred MB).
2. Plug the board in with a USB cable. Choose it under **Board**, and its COM port under **Port**.
   Uno/Mega are detected automatically. For an ESP32, choose the board yourself.
3. Click **✔** to compile or **➜** to upload.

### ESP32 tips
- No COM port shows up? Install the USB driver for the board's USB chip:
  [CP210x](https://www.silabs.com/developers/usb-to-uart-bridge-vcp-drivers) or
  [CH340](https://www.wch-ic.com/downloads/CH341SER_EXE.html).
- Upload stuck at `Connecting.....`? Hold the **BOOT** button on the board until writing starts.
- Most ESP32 sketches use `Serial.begin(115200)`, so set the Serial Monitor to **115200** baud.

## Features

| | |
|---|---|
| Editor | C++ syntax highlighting, line numbers, bracket matching, auto-close brackets |
| Sketch menu | Verify/Compile (Ctrl+R), Upload (Ctrl+U), Stop |
| Files | New / Open / Save / Save As. Saved sketches follow the Arduino layout `MySketch/MySketch.ino` |
| Boards | Uno, Nano, Mega 2560, Leonardo, Pro Mini, ESP32 Dev Module, DOIT DevKit V1, ESP32-S3, ESP32-C3, ESP32-CAM |
| Ports | Lists COM ports, refreshes on its own, auto-picks the board for genuine Arduinos |
| Serial Monitor | Baud rate, line ending, send box, autoscroll, clear |
| Libraries | Tools → Install Library… (any library from the Arduino Library Manager) |
| Examples | Blink, Serial Hello, Analog Read, ESP32 WiFi Scan, ESP32 Web Server, ESP32 Touch |

Board cores and libraries are stored where arduino-cli keeps them (`%LOCALAPPDATA%\Arduino15`
and `Documents\Arduino\libraries`), so they are shared with the official Arduino IDE if it's installed.

## Develop / build it yourself (Windows)

Requires [Node.js 20+](https://nodejs.org/).

```bat
cd arduino-ide
npm install
npm run fetch-cli      :: downloads arduino-cli into bin\
npm start              :: run the app
npm run dist:win       :: build the installer + portable exe into dist\
```

`node scripts/smoke-test.js` installs both cores and compiles every example for an Uno and an ESP32.
CI runs it before it builds the app.

| File | Role |
|---|---|
| `main.js` | Electron main process: window, menus, files, all `arduino-cli` calls, serial monitor |
| `preload.js` | The small API (`window.ide`) the page is allowed to use |
| `src/index.html`, `src/styles.css`, `src/renderer.js` | The IDE screen |
| `src/boards.js` | Board list and example sketches. Add a board here using its FQBN |
