/**
 * ============================================================================
 * IR Control — Firmware ESP32 para Disparo Infravermelho
 * ============================================================================
 *
 * Microcontrolador: ESP32 (NodeMCU-32S, ESP32 DevKit v1, etc.)
 * Pino Emissor IR:  GPIO 4 (conforme especificação do projeto)
 * Pino LED Status:  GPIO 2 (LED onboard para feedback visual)
 *
 * Bibliotecas necessárias (Gerenciador de Bibliotecas do Arduino IDE):
 *   1. "IRremoteESP8266" por David Conroy, crankyoldgit (versão 2.8.x ou superior)
 *   2. "ArduinoJson" por Benoit Blanchon (versão 6.x ou 7.x)
 *
 * Modos de Operação Suportados:
 *   [x] Modo A - Servidor HTTP (Recomendado):
 *       O ESP32 fica ouvindo requisições POST em http://<IP_DO_ESP32>/ir/send
 *       contendo o JSON gerado pelo sistema IR Control.
 *
 *   [x] Modo B - Polling Cliente (Opcional):
 *       O ESP32 consulta periodicamente o servidor PHP em busca de novos comandos
 *       pendentes para a sala configurada.
 * ============================================================================
 */

#include <WiFi.h>
#include <WebServer.h>
#include <ArduinoJson.h>
#include <HTTPClient.h>

// Bibliotecas IR
#include <IRremoteESP8266.h>
#include <IRsend.h>
#include <ir_Coolix.h>
#include <ir_Samsung.h>
#include <ir_NEC.h>
#include <ir_Sony.h>

// ============================================================================
// CONFIGURAÇÕES DE HARDWARE E PINAGEM
// ============================================================================
#define IR_PIN            4    // PINO GPIO 4: Saída para o LED Infravermelho
#define STATUS_LED_PIN    2    // PINO GPIO 2: LED onboard para feedback visual

// ============================================================================
// CONFIGURAÇÕES DE WI-FI
// ============================================================================
const char* WIFI_SSID     = "SUA_REDE_WIFI";       // Nome da rede Wi-Fi
const char* WIFI_PASSWORD = "SUA_SENHA_WIFI";      // Senha da rede Wi-Fi

// ============================================================================
// CONFIGURAÇÕES DO SERVIDOR HTTP LOCAL NO ESP32
// ============================================================================
WebServer server(80);

// ============================================================================
// CONFIGURAÇÕES DO MODO POLLING (Consulta a cada 1 segundo)
// ============================================================================
const bool   ENABLE_POLLING    = true;                        // Habilitado: consulta o servidor a cada 1 segundo
const char*  SERVER_HOST       = "http://192.168.1.100";     // IP do computador rodando XAMPP (ajuste para seu IP)
const int    SALA_ID           = 1;                           // ID da Sala deste ESP32
const unsigned long POLL_DELAY = 1000;                        // Intervalo exato de 1000 ms (1 segundo)
unsigned long lastPollTime     = 0;

// ============================================================================
// INSTÂNCIAS DE CONTROLE IR
// ============================================================================
IRsend irsend(IR_PIN);

// Variáveis de monitoramento e status
unsigned long totalDisparos = 0;
String ultimoComando        = "Nenhum comando executado";
String ultimoProtocolo      = "-";
String ultimoCodigo         = "-";
unsigned long ultimoDisparoTempo = 0;

// ============================================================================
// FUNÇÃO AUXILIAR: PISCAR LED DE STATUS
// ============================================================================
void blinkLed(int times = 1, int durationMs = 80) {
  for (int i = 0; i < times; i++) {
    digitalWrite(STATUS_LED_PIN, HIGH);
    delay(durationMs);
    digitalWrite(STATUS_LED_PIN, LOW);
    if (i < times - 1) delay(durationMs);
  }
}

// ============================================================================
// FUNÇÃO PRINCIPAL: EXECUÇÃO DO DISPARO INFRAVERMELHO
// ============================================================================
bool executarComandoIR(String protocolo, String codigoStr, int frequencia = 38) {
  protocolo.toUpperCase();
  protocolo.trim();
  codigoStr.trim();

  Serial.println("\n----------------------------------------");
  Serial.printf("[IR] Iniciando transmissão no GPIO %d\n", IR_PIN);
  Serial.printf("[IR] Protocolo: %s | Frequência: %d kHz\n", protocolo.c_str(), frequencia);
  Serial.printf("[IR] Código: %s\n", codigoStr.c_str());

  digitalWrite(STATUS_LED_PIN, HIGH); // Acende LED durante envio

  bool sucesso = false;

  // 1. PROTOCOLO RAW (Array JSON de durações de pulso em microssegundos)
  if (protocolo == "RAW" || codigoStr.startsWith("[")) {
    DynamicJsonDocument docRaw(4096);
    DeserializationError err = deserializeJson(docRaw, codigoStr);

    if (err) {
      Serial.printf("[IR ERROR] Falha ao desserializar array RAW: %s\n", err.c_str());
    } else {
      JsonArray arr = docRaw.as<JsonArray>();
      int rawLength = arr.size();
      uint16_t* rawArray = new uint16_t[rawLength];

      for (int i = 0; i < rawLength; i++) {
        rawArray[i] = arr[i].as<uint16_t>();
      }

      Serial.printf("[IR] Enviando sinal RAW com %d pulsos...\n", rawLength);
      irsend.sendRaw(rawArray, rawLength, frequencia);
      delete[] rawArray;
      sucesso = true;
    }
  }

  // 2. PROTOCOLO COOLIX (Ar-condicionado Elgin, Midea, Springer, etc.)
  else if (protocolo == "COOLIX") {
    // Normaliza HEX para uint32_t (códigos Coolix possuem 24 bits, ex: 0xB21F00)
    uint32_t codigoHex = strtoul(codigoStr.c_str(), NULL, 16);
    Serial.printf("[IR] Enviando comando COOLIX: 0x%06X (24 bits)\n", codigoHex);
    irsend.sendCOOLIX(codigoHex, 24);
    sucesso = true;
  }

  // 3. PROTOCOLO NEC (Projetores, TVs, Climatizadores LG, etc.)
  else if (protocolo == "NEC") {
    uint32_t codigoHex = strtoul(codigoStr.c_str(), NULL, 16);
    Serial.printf("[IR] Enviando comando NEC: 0x%08X (32 bits)\n", codigoHex);
    irsend.sendNEC(codigoHex, 32);
    sucesso = true;
  }

  // 4. PROTOCOLO SAMSUNG / SAMSUNG_AC
  else if (protocolo == "SAMSUNG_AC" || protocolo == "SAMSUNG") {
    uint64_t codigoHex = strtoull(codigoStr.c_str(), NULL, 16);
    Serial.printf("[IR] Enviando comando Samsung AC: %s\n", codigoStr.c_str());
    irsend.sendSAMSUNG(codigoHex, 32);
    sucesso = true;
  }

  // 5. PROTOCOLO SONY
  else if (protocolo == "SONY") {
    uint32_t codigoHex = strtoul(codigoStr.c_str(), NULL, 16);
    Serial.printf("[IR] Enviando comando Sony: 0x%X (12 bits)\n", codigoHex);
    irsend.sendSony(codigoHex, 12);
    sucesso = true;
  }

  // 6. GENÉRICO / DESCONHECIDO (Tenta conversão HEX padrão)
  else {
    uint64_t codigoHex = strtoull(codigoStr.c_str(), NULL, 16);
    Serial.printf("[IR AVISO] Protocolo '%s' não mapeado diretamente. Tentando envio NEC como fallback...\n", protocolo.c_str());
    irsend.sendNEC((uint32_t)codigoHex, 32);
    sucesso = true;
  }

  digitalWrite(STATUS_LED_PIN, LOW); // Apaga LED

  if (sucesso) {
    totalDisparos++;
    ultimoProtocolo      = protocolo;
    ultimoCodigo         = codigoStr;
    ultimoDisparoTempo   = millis();
    ultimoComando        = protocolo + " -> " + codigoStr;
    Serial.println("[IR SUCCESS] Sinal IR emitido com sucesso pelo pino 4!");
  } else {
    Serial.println("[IR FAILED] Falha na emissão do sinal IR.");
  }
  Serial.println("----------------------------------------\n");

  return sucesso;
}

// ============================================================================
// ROTAS DO SERVIDOR WEB NO ESP32
// ============================================================================

// Middleware para habilitar CORS (Cross-Origin Resource Sharing)
void habilitarCORS() {
  server.sendHeader("Access-Control-Allow-Origin", "*");
  server.sendHeader("Access-Control-Allow-Methods", "POST, GET, OPTIONS");
  server.sendHeader("Access-Control-Allow-Headers", "Content-Type, Authorization");
}

// Handler para requisições OPTIONS (Pre-flight do navegador)
void handleOptions() {
  habilitarCORS();
  server.send(204);
}

// ROTA: POST /ir/send ou /api/ir
// Recebe JSON: { "protocolo": "COOLIX", "codigo": "0xB21F00", "frequencia": 38, "log_id": 1 }
void handleIRSend() {
  habilitarCORS();

  if (server.method() != HTTP_POST) {
    server.send(405, "application/json", "{\"success\":false,\"message\":\"Utilize método POST.\"}");
    return;
  }

  String body = server.arg("plain");
  if (body.length() == 0) {
    server.send(400, "application/json", "{\"success\":false,\"message\":\"Corpo da requisição vazio.\"}");
    return;
  }

  DynamicJsonDocument doc(2048);
  DeserializationError error = deserializeJson(doc, body);

  if (error) {
    server.send(422, "application/json", "{\"success\":false,\"message\":\"JSON malformado.\"}");
    return;
  }

  String protocolo  = doc["protocolo"] | "NEC";
  String codigo     = doc["codigo"]    | "";
  int frequencia    = doc["frequencia"] | 38;
  int logId         = doc["log_id"]     | 0;

  if (codigo.length() == 0) {
    server.send(422, "application/json", "{\"success\":false,\"message\":\"Campo 'codigo' é obrigatório.\"}");
    return;
  }

  bool enviado = executarComandoIR(protocolo, codigo, frequencia);

  DynamicJsonDocument resDoc(512);
  resDoc["success"]    = enviado;
  resDoc["message"]    = enviado ? "Comando IR emitido no GPIO 4 com sucesso." : "Erro ao processar sinal IR.";
  resDoc["protocolo"]  = protocolo;
  resDoc["codigo"]     = codigo;
  resDoc["frequencia"] = frequencia;
  resDoc["pino_ir"]    = IR_PIN;
  resDoc["log_id"]     = logId;

  String resJson;
  serializeJson(resDoc, resJson);
  server.send(enviado ? 200 : 500, "application/json", resJson);
}

// ROTA: GET /ir/test (Teste rápido de emissão de pulso)
void handleIRTest() {
  habilitarCORS();
  Serial.println("[IR TEST] Disparo de pulso teste acionado.");
  bool ok = executarComandoIR("COOLIX", "0xB21F00", 38);
  server.send(200, "application/json", ok ? "{\"success\":true,\"message\":\"Pulso teste enviado no GPIO 4!\"}" : "{\"success\":false}");
}

// ROTA: GET / (Página Web de Status e Diagnóstico)
void handleRoot() {
  habilitarCORS();

  unsigned long uptimeSegundos = millis() / 1000;
  int dias  = uptimeSegundos / 86400;
  int horas = (uptimeSegundos % 86400) / 3600;
  int mins  = (uptimeSegundos % 3600) / 60;
  int segs  = uptimeSegundos % 60;

  char uptimeStr[40];
  snprintf(uptimeStr, sizeof(uptimeStr), "%dd %02dh %02dm %02ds", dias, horas, mins, segs);

  String html = "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1.0'>";
  html += "<title>ESP32 IR Controller</title>";
  html += "<style>";
  html += "body{background:#0a0e17;color:#e2e8f0;font-family:system-ui,-apple-system,sans-serif;margin:0;padding:2rem;display:flex;justify-content:center;}";
  html += ".card{background:#151d2f;border:1px solid #1e293b;border-radius:1rem;max-width:520px;width:100%;padding:2rem;box-shadow:0 10px 25px rgba(0,0,0,0.5);}";
  html += "h1{color:#38bdf8;font-size:1.5rem;margin-top:0;display:flex;align-items:center;gap:.5rem;}";
  html += ".badge{background:#0ea5e9;color:#fff;padding:.2rem .6rem;border-radius:999px;font-size:.75rem;font-weight:600;}";
  html += ".item{display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid #1e293b;font-size:.9rem;}";
  html += ".label{color:#94a3b8;} .val{font-weight:600;font-family:monospace;}";
  html += ".btn{background:#0284c7;color:#fff;border:none;padding:.75rem 1.25rem;border-radius:.5rem;cursor:pointer;font-weight:600;width:100%;margin-top:1.5rem;}";
  html += ".btn:hover{background:#0369a1;}";
  html += "</style></head><body><div class='card'>";
  html += "<h1>📡 ESP32 IR Controller <span class='badge'>Online</span></h1>";
  html += "<div class='item'><span class='label'>Pino IR Emissor:</span><span class='val' style='color:#38bdf8'>GPIO " + String(IR_PIN) + "</span></div>";
  html += "<div class='item'><span class='label'>Endereço IP:</span><span class='val'>" + WiFi.localIP().toString() + "</span></div>";
  html += "<div class='item'><span class='label'>Rede Wi-Fi:</span><span class='val'>" + String(WIFI_SSID) + " (" + String(WiFi.RSSI()) + " dBm)</span></div>";
  html += "<div class='item'><span class='label'>Uptime:</span><span class='val'>" + String(uptimeStr) + "</span></div>";
  html += "<div class='item'><span class='label'>Total de Disparos:</span><span class='val'>" + String(totalDisparos) + "</span></div>";
  html += "<div class='item'><span class='label'>Último Comando:</span><span class='val' style='color:#10b981'>" + ultimoComando + "</span></div>";
  html += "<button class='btn' onclick=\"fetch('/ir/test').then(r=>r.json()).then(d=>alert(d.message))\">⚡ Testar Pulso IR (GPIO 4)</button>";
  html += "</div></body></html>";

  server.send(200, "text/html", html);
}

// ============================================================================
// MODO POLLING: CONSULTA PERIODICAMENTE O SERVIDOR PHP
// ============================================================================
void verificarComandosPendentes() {
  if (!ENABLE_POLLING) return;
  if (WiFi.status() != WL_CONNECTED) return;

  if (millis() - lastPollTime < POLL_DELAY) return;
  lastPollTime = millis();

  HTTPClient http;
  String url = String(SERVER_HOST) + "/esp32-ir/api/ir/pendente.php?sala_id=" + String(SALA_ID);

  http.begin(url);
  http.setTimeout(2000);
  int httpCode = http.GET();

  if (httpCode == 200) {
    String payload = http.getString();
    DynamicJsonDocument doc(2048);
    DeserializationError error = deserializeJson(doc, payload);

    if (!error && doc["success"].as<bool>() && doc.containsKey("data")) {
      JsonObject cmd = doc["data"].as<JsonObject>();
      int logId        = cmd["log_id"] | 0;
      String protocolo = cmd["protocolo"] | "NEC";
      String codigo    = cmd["codigo"] | "";
      int freq         = cmd["frequencia"] | 38;

      if (codigo.length() > 0) {
        Serial.printf("[POLLING] Novo comando recebido! Log #%d - %s\n", logId, codigo.c_str());
        bool enviado = executarComandoIR(protocolo, codigo, freq);

        // Confirma execução para o servidor PHP
        if (enviado && logId > 0) {
          HTTPClient httpConfirm;
          String confirmUrl = String(SERVER_HOST) + "/esp32-ir/api/ir/confirmar.php";
          httpConfirm.begin(confirmUrl);
          httpConfirm.addHeader("Content-Type", "application/json");

          String postBody = "{\"log_id\":" + String(logId) + "}";
          httpConfirm.POST(postBody);
          httpConfirm.end();
        }
      }
    }
  }

  http.end();
}

// ============================================================================
// CONEXÃO WI-FI
// ============================================================================
void conectarWiFi() {
  Serial.println("\n[WIFI] Conectando à rede: " + String(WIFI_SSID));
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  int tentativas = 0;
  while (WiFi.status() != WL_CONNECTED && tentativas < 40) {
    delay(500);
    Serial.print(".");
    digitalWrite(STATUS_LED_PIN, !digitalRead(STATUS_LED_PIN)); // Pisca enquanto conecta
    tentativas++;
  }

  digitalWrite(STATUS_LED_PIN, LOW);

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[WIFI SUCCESS] Conectado com sucesso!");
    Serial.print("[WIFI] IP Obtido: ");
    Serial.println(WiFi.localIP());
    Serial.printf("[WIFI] Gateway: %s | Máscara: %s\n", WiFi.gatewayIP().toString().c_str(), WiFi.subnetMask().toString().c_str());
    blinkLed(3, 100); // 3 piscadas de confirmação
  } else {
    Serial.println("\n[WIFI ERROR] Falha ao conectar. Verifique o SSID e a Senha no código.");
  }
}

// ============================================================================
// SETUP
// ============================================================================
void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("\n==================================================");
  Serial.println("  IR CONTROL — FIRMWARE ESP32 (GPIO 4)");
  Serial.println("==================================================");

  // Inicialização dos pinos
  pinMode(STATUS_LED_PIN, OUTPUT);
  digitalWrite(STATUS_LED_PIN, LOW);

  // Inicialização do transmissor infravermelho no pino 4
  irsend.begin();
  Serial.printf("[HARDWARE] Emissor IR inicializado no GPIO %d.\n", IR_PIN);

  // Conexão Wi-Fi
  conectarWiFi();

  // Configuração das rotas do Web Server
  server.on("/", HTTP_GET, handleRoot);
  server.on("/ir/send", HTTP_POST, handleIRSend);
  server.on("/ir/send", HTTP_OPTIONS, handleOptions);
  server.on("/api/ir", HTTP_POST, handleIRSend);
  server.on("/api/ir", HTTP_OPTIONS, handleOptions);
  server.on("/ir/test", HTTP_GET, handleIRTest);

  server.onNotFound([]() {
    habilitarCORS();
    server.send(404, "application/json", "{\"error\":\"Rota não encontrada.\"}");
  });

  server.begin();
  Serial.println("[HTTP SERVER] Servidor Web iniciado na porta 80.");
  Serial.print("[HTTP SERVER] Pronto para receber comandos em: http://");
  Serial.print(WiFi.localIP());
  Serial.println("/ir/send");
  Serial.println("==================================================\n");
}

// ============================================================================
// LOOP PRINCIPAL
// ============================================================================
void loop() {
  // Reconexão automática de Wi-Fi se cair
  if (WiFi.status() != WL_CONNECTED) {
    conectarWiFi();
  }

  // Atende requisições HTTP recebidas (Modo A)
  server.handleClient();

  // Executa polling caso habilitado (Modo B)
  verificarComandosPendentes();
}
