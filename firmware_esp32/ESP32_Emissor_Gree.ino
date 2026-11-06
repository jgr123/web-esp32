#include <Arduino.h>
#include <IRremoteESP8266.h>
#include <IRsend.h>
#include <ir_Gree.h>

// Configuração do pino do LED Emissor IR (Altere se necessário)
const uint16_t kIrLedPin = 4; 

// Instancia o objeto de controle do Ar GREE
IRGreeAC acGree(kIrLedPin);

// Função auxiliar para capturar o estado em Hexadecimal e enviar via IR
void emitirEImprimir(String descricao) {
  // Obtém o estado bruto atual de 8 bytes (64 bits)
  uint8_t* estadoBruto = acGree.getRaw();
  
  // Monta a string Hexadecimal
  String codigoHex = "";
  for (int i = 0; i < kGreeStateLength; i++) {
    if (estadoBruto[i] < 16) codigoHex += "0";
    codigoHex += String(estadoBruto[i], HEX);
  }
  codigoHex.toUpperCase();

  // Exibe no Monitor Serial de forma organizada
  Serial.println("==================================================");
  Serial.println("AÇÃO: " + descricao);
  Serial.println("HEX : 0x" + codigoHex);
  Serial.println("--------------------------------------------------");

  // Envia o sinal físico IR
  acGree.send();
  
  // Aguarda 1 segundo antes do próximo comando
  delay(1000); 
}

void setup() {
  Serial.begin(115200);
  acGree.begin();
  
  Serial.println("--- INICIANDO VARREDURA COMPLETA DO PROTOCOLO GREE ---");
  delay(3000);

  // Garante que o objeto do ar comece ligado para os testes
  acGree.on();

  // Array com os modos solicitados e seus nomes para o Serial
  uint8_t modos[] = {kGreeHeat, kGreeCool, kGreeDry, kGreeFan};
  String nomesModos[] = {"QUENTE (Heat)", "FRIO (Cool)", "DESUMIDIFICAÇÃO (Dry)", "VENTILAÇÃO (Fan)"};

  // ==========================================
  // PARTE 1: Varredura de Todas as Temperaturas
  // ==========================================
  Serial.println("\n>>> FASE 1: TESTANDO TEMPERATURAS EM TODOS OS MODOS <<<");
  
  for (int m = 0; m < 4; m++) {
    acGree.setMode(modos[m]);
    
    // O protocolo GREE aceita estritamente temperaturas de 16°C a 30°C
    for (uint8_t temp = 16; temp <= 30; temp++) {
      acGree.setTemp(temp);
      
      String desc = "Modo: " + nomesModos[m] + " | Temp: " + String(temp) + "°C";
      emitirEImprimir(desc);
    }
  }

  // ==========================================
  // PARTE 2: Varredura de Velocidades do Fan (Ventilação)
  // ==========================================
  Serial.println("\n>>> FASE 2: TESTANDO VELOCIDADES DA VENTILAÇÃO (FAN) <<<");
  
  uint8_t velocidadesFan[] = {kGreeFanAuto, kGreeFanMin, kGreeFanMed, kGreeFanMax};
  String nomesFan[] = {"AUTOMÁTICO (Auto)", "MÍNIMO (Speed 1)", "MÉDIO (Speed 2)", "MÁXIMO (Speed 3)"};

  // Mantém uma temperatura padrão (ex: 22°C) para focar na mudança do Fan
  acGree.setTemp(22); 

  for (int m = 0; m < 4; m++) {
    acGree.setMode(modos[m]);
    
    for (int f = 0; f < 4; f++) {
      acGree.setFan(velocidadesFan[f]);
      
      String desc = "Modo: " + nomesModos[m] + " | Velocidade Fan: " + nomesFan[f];
      emitirEImprimir(desc);
    }
  }

  // ==========================================
  // PARTE 3: Varredura de Oscilação (Swing)
  // ==========================================
  Serial.println("\n>>> FASE 3: TESTANDO MODOS DE OSCILAÇÃO (SWING) <<<");
  
  uint8_t posicoesSwingFixo[] = {
    kGreeSwingLastPos, 
    kGreeSwingUp, 
    kGreeSwingMiddleUp, 
    kGreeSwingMiddle, 
    kGreeSwingMiddleDown, 
    kGreeSwingDown
  };
  
  String nomesSwingFixo[] = {
    "Última Posição Registrada", 
    "Fixa no Topo (Máximo Cima)", 
    "Fixa em Meio-Cima", 
    "Fixa no Centro (Meio)", 
    "Fixa em Meio-Baixo", 
    "Fixa na Base (Máximo Baixo)"
  };

  acGree.setFan(kGreeFanAuto); 

  for (int m = 0; m < 4; m++) {
    acGree.setMode(modos[m]);
    
    acGree.setSwingVertical(true, kGreeSwingLastPos);
    String descAuto = "Modo: " + nomesModos[m] + " | Swing Vertical: ATIVADO (Movimento Automático)";
    emitirEImprimir(descAuto);

    for (int s = 0; s < 6; s++) {
      acGree.setSwingVertical(false, posicoesSwingFixo[s]);
      
      String descFixa = "Modo: " + nomesModos[m] + " | Swing Vertical: FIXO -> " + nomesSwingFixo[s];
      emitirEImprimir(descFixa);
    }
  }

  // ==========================================
  // PARTE 4: Comando Final - Desligar o Aparelho
  // ==========================================
  Serial.println("\n>>> FASE COMPLEMENTAR: DESLIGANDO O APARELHO <<<");
  
  // Altera o estado interno especificamente para DESLIGADO (mantendo as últimas configs ativas de modo/swing)
  acGree.off();
  
  // Emite o sinal e exibe o Hexadecimal exato gerado para o desligamento
  emitirEImprimir("DESLIGAR (Power Off)");

  Serial.println("\n--- VARREDURA COMPLETA E COMANDO DE DESLIGAMENTO CONCLUÍDOS ---");
}

void loop() {
  // Mantém o loop vazio para rodar apenas uma vez
}
