# AlexaSmartHome Device
Modul zum Steuern von mit Alexa verbundenen Smart-Home-Geräten aus IP-Symcon heraus.

## Inhaltsverzeichnis
1. [Funktionsumfang](#funktionsumfang)
2. [Konfiguration der Instanz](#konfiguration-der-instanz)
3. [Statusvariablen und Profile](#statusvariablen-und-profile)
4. [PHP-Befehlsreferenz](#php-befehlsreferenz)

## Funktionsumfang
* Status (Aus, An)
* Prozent
* Helligkeit
* Farbe
* Farbtemperatur
* Solltemperatur
* Szene (Deactivate, Activate)

## Konfiguration der Instanz

|Eigenschaft| Typ| Beschreibung| Wert |
|-----| -----| -----| ----- |
|EntityID | ValidationTextBox | EntityID | |
|UpdateInterval | Select | Aktualisierungsintervall | `disabled`, `15 minutes`, `60 minutes`, `24 hours`|
|DeviceInformation | Label | Geräteinformationen | |

## Statusvariablen und Profile

|Ident| Typ| Profil| Beschreibung |
|-----| -----| -----| ----- |
|connectivity |bool |~Switch |Konnektivität |
|powerState |bool |~Switch |Status |
|percentage |int |~Intensity.100 |Prozent |
|brightness |int |~Intensity.100 |Helligkeit |
|color |int |~HexColor |Farbe |
|colorTemperatureInKelvin |int |~TWColor |Farbtemperatur |
|temperature |float |~Temperature |Temperatur |
|illuminance |int |~Illumination |Beleuchtungsstärke |
|targetSetpoint |float |~Temperature |Solltemperatur |
|scene |int |Alexa.Scene |Szene |

## PHP-Befehlsreferenz

### Schalten von Statusvariablen
```php
RequestAction( int $VariableID , $Value);
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der Statusvariablen |
|$Value |mixed |Zu schaltender Wert |

