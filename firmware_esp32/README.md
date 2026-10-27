# 📡 Firmware ESP32 — Emissor Infravermelho (GPIO 4)

Este diretório contém o firmware C++ para o microcontrolador **ESP32**, projetado para receber comandos da aplicação web **IR Control** e transmiti-los via LED emissor infravermelho no **GPIO 4**.

---

## 🔌 Esquema de Ligação do Hardware

### Pino Utilizado:
- **Transmissor IR (LED IR):** `GPIO 4`
- **LED Indicador de Status:** `GPIO 2` (LED azul onboard do ESP32)

---

### Opção 1: Circuito com Transistor (Alcance Máximo: 5 a 10 metros) — *Recomendado*

Para atingir a potência ideal do sinal infravermelho em aparelhos de ar-condicionado e projetores distantes, recomenda-se o uso de um transistor NPN (como **2N2222**, **BC547** ou **2N3904**):

```text
       ESP32 (3.3V / 5V)
           │
          [+] 5V (VIN do ESP32)
           │
          [R] Resistor de 47Ω a 100Ω (1/2W)
           │
          ─── Ânodo (+) [Perna longa]
          \ / LED Emissor Infravermelho (940nm)
          ─── Cátodo (-) [Perna curta]
           │
           ├── Coletor (C)
  GPIO 4 ──┤ [R 1kΩ] ── Base (B)  [Transistor NPN: 2N2222 / BC547]
           └── Emissor (E)
           │
          GND (GND do ESP32)
```

### Opção 2: Ligação Direta (Para bancada / testes a curta distância < 1 metro)

```text
  ESP32 GPIO 4 ─── [Resistor 100Ω a 220Ω] ─── Ânodo (+) [LED IR] Cátodo (-) ─── GND
```

---

## 📦 Bibliotecas Necessárias (Arduino IDE)

Abra o **Arduino IDE** e acesse:
`Sketch` ➔ `Incluir Biblioteca` ➔ `Gerenciar Bibliotecas...` (ou `Ctrl + Shift + I`):

1. **IRremoteESP8266** (por *David Conroy, crankyoldgit*)
   - Suporta Coolix, NEC, Samsung AC, Sony, LG, Panasonic e sinais RAW.
2. **ArduinoJson** (por *Benoit Blanchon*)
   - Versão 6.x ou 7.x recomendada.

---

## ⚙️ Configuração e Gravação do Código

1. Abra o arquivo [firmware_esp32.ino](firmware_esp32.ino) no Arduino IDE.
2. No início do código, configure o nome e a senha da sua rede Wi-Fi:
   ```cpp
   const char* WIFI_SSID     = "SUA_REDE_WIFI";
   const char* WIFI_PASSWORD = "SUA_SENHA_WIFI";
   ```
3. Selecione sua placa em `Ferramentas` ➔ `Placa` (ex: `ESP32 Dev Module` ou `NodeMCU-32S`).
4. Selecione a porta COM correspondente em `Ferramentas` ➔ `Porta`.
5. Clique em **Carregar (Upload)** (`Ctrl + U`).
6. Abra o **Monitor Serial** configurado em `115200 baud` para visualizar o IP atribuído ao ESP32.

---

## 🌐 Como Funciona a Comunicação

O ESP32 opera como um servidor HTTP leve na porta `80`.

### 1. Endpoint de Disparo: `POST /ir/send`

Recebe o JSON emitido pela aplicação PHP:

```json
{
  "protocolo": "COOLIX",
  "codigo": "0xB21F00",
  "frequencia": 38,
  "log_id": 1
}
```

**Resposta do ESP32:**
```json
{
  "success": true,
  "message": "Comando IR emitido no GPIO 4 com sucesso.",
  "protocolo": "COOLIX",
  "codigo": "0xB21F00",
  "pino_ir": 4
}
```

### 2. Protocolos Suportados no Firmware:
- **COOLIX:** Elgin, Midea, Springer (códigos de 24 bits, ex: `0xB21F00`).
- **NEC:** LG, TVs, Projetores (códigos de 32 bits, ex: `0x88B44BB4`).
- **SAMSUNG / SAMSUNG_AC:** Climatizadores e TVs Samsung.
- **SONY:** TVs e reprodutores Sony.
- **RAW:** Arrays de pulsos em microssegundos JSON (ex: `[9000, 4500, 560, 560, ...]`).

### 3. Painel de Diagnóstico Embutido no ESP32:
Basta digitar o IP do ESP32 no navegador:
```text
http://<IP_DO_ESP32>/
```
Você verá uma página de status com o Uptime, intensidade do Wi-Fi, último comando transmitido e um botão para disparar um pulso de teste no GPIO 4.

---

## 🔗 Vinculação com o IR Control PHP

Para que o servidor PHP envie os comandos diretamente para o IP do seu ESP32:
1. Abra o arquivo `config/database.php` no projeto PHP.
2. Defina a constante com o IP do ESP32:
   ```php
   define('ESP32_IP', '192.168.1.150'); // Insira o IP real do seu ESP32
   ```
Pronto! Ao clicar em qualquer comando no Dashboard, o PHP fará o POST automático para o ESP32, emitindo o sinal no GPIO 4.
