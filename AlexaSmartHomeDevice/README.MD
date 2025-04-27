# Alexa Smarthome Device
Modul zum Steuern von mit Alexa verbundenen Smart-Home-Geräten aus IP-Symcon heraus.

## Inhaltsverzeichnis
1. [Funktionsumfang](#funktionsumfang)
2. [Konfiguration der Instanz](#konfiguration-der-instanz)
3. [Statusvariablen und Profile](#statusvariablen-und-profile)
4. [PHP-Befehlsreferenz](#php-befehlsreferenz)

## Funktionsumfang

**Steuerung**
* Gerätestatus (Aus, An)
* Licht (Helligkeit, Farbe, Farbtemperatur)
* Thermostat (Solltemperatur, Modus)
* Szene (Deactivate, Activate)
* Schloß (Verrigeln, Entriegeln)
* Modus (gerätespezifisch z.B. Lichtszene, Lüftergeschwindigkeit, etc.)
* Bereichseinstellung (gerätespezifisch z.B. Öffnungsgrad von Rolläden)
* Prozent

**Sensoren**
* Temperatur
* Luftfeuchte
* Beleuchtung
* Füllstand (z.B. Batterie, Tintenpatronen, etc.)


## Konfiguration der Instanz

|Eigenschaft| Typ| Beschreibung| Wert |
|-----| -----| -----| ----- |
|EntityID | ValidationTextBox | EntityID | |
|UpdateInterval | Select | Aktualisierungsintervall | `disabled`, `15 minutes`, `60 minutes`, `24 hours`|
|DeviceInformation | Label | Geräteinformationen | |

## Statusvariablen und Profile

Die verfügbaren Statusvariablen variieren nach Gerät und Funktionalität.

|Ident| Typ| Profil| Beschreibung |
|-----| -----| -----| ----- |
|AlexaPowerController_powerState |bool | |Status |
|AlexaThermostatController_targetSetpoint |float | |Solltemperatur |
|AlexaThermostatController_thermostatMode |string | |Thermostat Modus |
|AlexaColorTemperatureController_colorTemperatureInKelvin |int | |Farbtemperatur |
|AlexaBrightnessController_brightness |int | |Helligkeit |
|AlexaSceneController_scene |int | |Szene |
|AlexaToggleController_toggleState_<...> |bool | |gerätespezifischer An/Ausschalter z.B. für Modi|
|AlexaModeController_mode_<...> |string | |gerätespezifischer Modus z.B. Lichtszene, Lüftergeschwindigkeit, etc. |
|AlexaRangeController_rangeValue_<...> |int | |gerätespezifisch z.B. Öffnungsgrad von Rolläden|
|AlexaTemperatureSensor_temperature |float | |Temperatur |
|AlexaLightSensor_illuminance |int | |Beleuchtungsstärke |
|AlexaBrightnessController_brightness |int | |Helligkeit |
|AlexaInventoryLevelSensor_level_<...> |int | |Füllstandssensor (z.B. Batterie) |
|AlexaEndpointHealth_connectivity |bool | |Konnektivität |


## PHP-Befehlsreferenz

### Schalten von Statusvariablen
```php
RequestAction( int $VariableID , $Value);
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der Statusvariablen |
|$Value |mixed |Zu schaltender Wert. Mögliche Werte sind in den Variablenprofilen definiert. |

