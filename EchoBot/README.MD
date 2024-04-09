# EchoBot
Der EchoBot kann auf einen Sprachbefehl reagieren und verschiedene Aktionen ausführen, wie z.B. eine direkte Text-To-Speech Antwort auf dem Echo-Gerät ausgeben, das angesprochen wurde oder ein Skript in IP-Symcon ausführen.

## Inhaltsverzeichnis
1. [Funktionsumfang](#funktionsumfang)
2. [Einmalige Einrichtung](#einmalige-einrichtung)
3. [Einrichtung eines Echo Bots](#einrichtung-eines-echo-bots)
4. [PHP-Befehlsreferenz](#php-befehlsreferenz)

## Funktionsumfang
Wenn ein Sprachbefehl (dieser muss als Auslöser in einer Alexa-Routine definiert werden) von einem Echo-Gerät empfangen wurde, können folgende Aktionen ausgeführt werden:
* Text-to-speech Antwort auf dem angesprochenen Echo-Gerät ausgeben
* Skript in IP-Symcon ausführen

## Einmalige Einrichtung

Damit das Modul mitbekommt, dass ein Echo-Gerät angesprochen und eine Routine gestartet wurde, müssen einmalig vor der ersten Benutzung ein paar Einstellungen vorgenommen werden. Es gibt es zwei Optionen, wie das Modul Informationen über neue Aktivitäten erhalten kann:

|     | Vorteil| Nachteil |
|-----| -----  | -----    |
|Option 1: letzte Aktivität periodisch abfragen | + kein zusätzliches Modul erforderlich | - Antwort-Aktion wird ggf. ein paar Sekunden verzögert ausgeführt|
|Option 2: Amazon Alexa Modul als Rückkanal     | + Antwort-Aktion wird ohne Verzögerung ausgeführt | - zusätzlich Alexa Modul erforderlich <br> - in Routinen (Alexa App) muss die Trigger-Szene aufgerufen werden (siehe unten)|

### Option 1: letzte Aktivität periodisch abfragen

1. IP-Symcon: Splitter Instanzen -> EchoIO Instanz 
    - `letzte Aktivität periodisch abrufen` aktivieren
    - `Aktualisierungsintervall für letzte Aktivität` auf minimalen Wert stellen

### Option 2: Amazon Alexa Modul als Rückkanal

1. IP-Symcon: Kerninstanzen -> Alexa
    1. Unter Szenen -> `hinzufügen`
        - Name: TriggerLastActivity
        - Ziel: EchoIO
        - Aktion: Experte -> Führe erweiterte Instanzfunktion aus
        - Funktion: ECHOIO_GetLastActivity auswählen
    2. Speichern 
2. Einem beliebigen Echo-Gerät sagen: "Alexa, suche neue Geräte"
3. IP-Symcon: Splitter Instanzen -> EchoIO Instanz 
    1. `letzte Aktivität periodisch abrufen`  deaktivieren (optional - sofern nicht anderweitig benötigt)

## Einrichtung eines Echo Bots

### In Alexa App: 
1. Routine erstellen
    - Ereignis hinzufügen -> Stimme -> Einen oder mehrere Sätze definieren, die die Routine starten sollen. 
    - *[Nur bei Option 2]* Aktion hinzufügen -> SmartHome -> Szenen -> TriggerLastActivity auswählen

### In IP-Symcon: 
1. EchoBot Instanz erstellen
    - Routine als Auslöser auswählen
    - Aktionstyp auswählen und Aktion definieren


#### Aktionstyp: Text-to-Speech Antwort (einfach)

Es erfolgt eine Sprachantwort auf dem Echo-Gerät, das angesprochen wurde. Es kann für jedes Gerät eine unterschiedliche Antwort festgelegt werden. Soll bei jedem Gerät die gleiche Antwort gegeben werden, muss **Alle Geräte** im Feld **Angesprochenes Gerät** ausgewählt werden. Der Antworttext setzt sich zusammen aus zwei Textfeldern und einem Variablenwert. Beim Variablenwert wird der formatierte Wert inklusive Prä-/Suffix - z.B. 20 °C, geöffnet/geschlossen (bei Boolean) - gemäß Variablenprofil verwendet.

|Eigenschaft| Typ| Beschreibung |
|-----| -----| ----- |
|Text_1 | ValidationTextBox | Text|
|VariableID_1 | SelectVariable | Variable|
|Text_2 | ValidationTextBox | Text|



#### Aktionstyp: Text-to-Speech Antwort (erweitert)

Es erfolgt eine Sprachantwort auf dem Echo-Gerät, das angesprochen wurde. Der Antworttext kann im PHP-Skript beliebig angepasst werden. Weitere Informationen zur auslösenden Aktivität (wie Name, Seriennummer, Typ des angesprochenen Echo-Gerätes, Sprachausdruck, etc.) stehen in den Systemvariablen $_IPS zur Verfügung (siehe [Aktionstyp: Skript ausführen](#aktionstyp-skript-ausführen)).
Es ist zwingend erforderlich den Antworttext mittels `return` am Ende des Skipts als String zu übergeben.


Beispiel:
Abhängig vom angesprochenen Echo-Gerät soll die Temperatur des Raumes, in dem das Echo-Gerät steht, angesagt werden:
```php
<?php
//Note: always return the text message as string 

switch ($_IPS['deviceName']){
    
    case "Schlafzimmer - Echo Dot":
        $text = 'Im Schlafzimmer ist es '.GetValueFormatted(55617);
        break;

    case "Wohnzimmer - Echo 4":
        $text = 'Im Wohnzimmer ist es '.GetValueFormatted(18656);
        break;

    default:
        $text = 'Die Außentemperatur beträgt '.GetValueFormatted(13478);
        break;       
}

return $text;
```


#### Aktionstyp: Skript ausführen

Es kann ein beliebiges PHP-Skript ausgeführt werden, in dem die gewünschte Aktion selbst definiert werden kann. Weitere Informationen zur auslösenden Aktivität (wie Name, Seriennummer, Typ des angesprochenen Echo-Gerätes, Sprachausdruck, etc.) stehen im Skript als Systemvariablen $_IPS zur Verfügung.

    $_IPS['id']
    $_IPS['timestamp']
    $_IPS['timestampMilliseconds']
    $_IPS['deviceType']
    $_IPS['serialNumber']
    $_IPS['deviceName']
    $_IPS['utteranceType']
    $_IPS['domain']
    $_IPS['intent']
    $_IPS['utterance']
    $_IPS['response']
    $_IPS['person']
    $_IPS['instanceID']



## Statusvariablen und Profile

keine Statusvariablen vorhanden

## PHP-Befehlsreferenz

### TextToSpeech
```php
ECHOBOT_TextToSpeech( int $InstanceID, string $tts, string $deviceSerial, string $deviceType, array $options );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoBot-Instanz |
|$tts |string | |
|$deviceSerial |string | |
|$deviceType |string | |
|$options |array | |

### UpdateAutomations
```php
ECHOBOT_UpdateAutomations( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoBot-Instanz |
