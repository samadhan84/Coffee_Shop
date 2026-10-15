// Boards shown in the board picker. `core` is the arduino-cli platform that must be installed.
window.BOARDS = [
  { group: 'Arduino AVR', name: 'Arduino Uno', fqbn: 'arduino:avr:uno', core: 'arduino:avr' },
  { group: 'Arduino AVR', name: 'Arduino Nano', fqbn: 'arduino:avr:nano:cpu=atmega328', core: 'arduino:avr' },
  { group: 'Arduino AVR', name: 'Arduino Nano (Old Bootloader)', fqbn: 'arduino:avr:nano:cpu=atmega328old', core: 'arduino:avr' },
  { group: 'Arduino AVR', name: 'Arduino Mega 2560', fqbn: 'arduino:avr:mega:cpu=atmega2560', core: 'arduino:avr' },
  { group: 'Arduino AVR', name: 'Arduino Leonardo', fqbn: 'arduino:avr:leonardo', core: 'arduino:avr' },
  { group: 'Arduino AVR', name: 'Arduino Pro Mini (5V, 16 MHz)', fqbn: 'arduino:avr:pro:cpu=16MHzatmega328', core: 'arduino:avr' },
  { group: 'ESP32', name: 'ESP32 Dev Module', fqbn: 'esp32:esp32:esp32', core: 'esp32:esp32' },
  { group: 'ESP32', name: 'ESP32-WROOM (DOIT DevKit V1)', fqbn: 'esp32:esp32:esp32doit-devkit-v1', core: 'esp32:esp32' },
  { group: 'ESP32', name: 'ESP32-S3 Dev Module', fqbn: 'esp32:esp32:esp32s3', core: 'esp32:esp32' },
  { group: 'ESP32', name: 'ESP32-C3 Dev Module', fqbn: 'esp32:esp32:esp32c3', core: 'esp32:esp32' },
  { group: 'ESP32', name: 'ESP32-CAM (AI Thinker)', fqbn: 'esp32:esp32:esp32cam', core: 'esp32:esp32' },
];

window.EXAMPLES = {
  'Blink (any board)': `// Blinks the on-board LED once per second.
#ifndef LED_BUILTIN
#define LED_BUILTIN 2   // most ESP32 dev boards use GPIO 2
#endif

void setup() {
  pinMode(LED_BUILTIN, OUTPUT);
}

void loop() {
  digitalWrite(LED_BUILTIN, HIGH);
  delay(1000);
  digitalWrite(LED_BUILTIN, LOW);
  delay(1000);
}
`,
  'Serial Hello (any board)': `void setup() {
  Serial.begin(115200);
}

void loop() {
  Serial.print("Hello! Running for ");
  Serial.print(millis() / 1000);
  Serial.println(" s");
  delay(1000);
}
`,
  'Analog Read (Uno A0)': `void setup() {
  Serial.begin(9600);
}

void loop() {
  int value = analogRead(A0);
  Serial.println(value);
  delay(200);
}
`,
  'ESP32 WiFi Scan': `#include <WiFi.h>

void setup() {
  Serial.begin(115200);
  WiFi.mode(WIFI_STA);
  WiFi.disconnect();
  delay(100);
}

void loop() {
  Serial.println("Scanning...");
  int n = WiFi.scanNetworks();
  for (int i = 0; i < n; i++) {
    Serial.printf("%2d  %-32s  %4d dBm  ch %d\\n", i + 1, WiFi.SSID(i).c_str(), WiFi.RSSI(i), WiFi.channel(i));
  }
  Serial.println();
  delay(5000);
}
`,
  'ESP32 Web Server (LED on/off)': `#include <WiFi.h>
#include <WebServer.h>

const char* ssid = "YOUR_WIFI_NAME";
const char* password = "YOUR_WIFI_PASSWORD";
const int LED_PIN = 2;

WebServer server(80);

void handleRoot() {
  server.send(200, "text/html",
    "<h1>ESP32</h1><p><a href='/on'>LED ON</a> | <a href='/off'>LED OFF</a></p>");
}

void setup() {
  Serial.begin(115200);
  pinMode(LED_PIN, OUTPUT);
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) { delay(500); Serial.print("."); }
  Serial.print("\\nOpen http://");
  Serial.println(WiFi.localIP());

  server.on("/", handleRoot);
  server.on("/on",  [] { digitalWrite(LED_PIN, HIGH); handleRoot(); });
  server.on("/off", [] { digitalWrite(LED_PIN, LOW);  handleRoot(); });
  server.begin();
}

void loop() {
  server.handleClient();
}
`,
  'ESP32 Touch Sensor': `// Touch GPIO 4 (T0) with a finger and watch the value drop.
void setup() {
  Serial.begin(115200);
}

void loop() {
  Serial.println(touchRead(T0));
  delay(200);
}
`,
};
