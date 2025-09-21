# AmazonEchoIO
Anmeldung am Amazon-Account.

## Inhaltsverzeichnis
1. [Funktionsumfang](#funktionsumfang)
2. [Konfiguration der Instanz](#konfiguration-der-instanz)
3. [Statusvariablen und Profile](#statusvariablen-und-profile)
4. [PHP-Befehlsreferenz](#php-befehlsreferenz)

## Funktionsumfang

## Konfiguration der Instanz

|Eigenschaft| Typ| Beschreibung |
|-----| -----| ----- |
|active | CheckBox | aktiv|
|language | Select | Echo Sprache|
|refresh_token | PasswordTextBox | Refresh-Token|
|VariablesLastActivity | CheckBox | Variablen für letzte Aktivität anlegen (zum Aktualiseren der Variablen muss die Funktion ECHOIO_GetLastActivity() aufgerufen werden)|

***Experteneinstellungen***

|Eigenschaft| Typ| Beschreibung |
|-----| -----| ----- |
|UpdateInterval | NumberSpinner | Aktualisierungsintervall|
|LogMessageEx | CheckBox | Erweiterte Log Meldungen|

## Statusvariablen und Profile

|Ident| Typ| Profil| Beschreibung |
|-----| -----| -----| ----- |
|CookieExpirationDate |int |~UnixTimestamp |Ablaufdatum des Cookies |
|LastActivityPerson |string | |Letzte Aktivität: Person (Anzeige ca. 20 Sekunden verzögert)|
|LastActivityTimestamp |int |~UnixTimestamp |Letzte Aktivität: Zeit |
|LastActivityResponse |string | |Letzte Aktivität: Antwort |
|LastAction |string | |Letzte Aktivität: Befehl |
|LastDevice |string |Echo.LastDevice.&lt;InstanceID&gt; |Letzte Aktivität: Gerät |
|LastActivityIntent |string | |Letzte Aktivität: Intent |

## PHP-Befehlsreferenz

### LogIn
```php
ECHOIO_LogIn( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int | |

### GetLastActivity
Funktion liefert Informationen über die letzte Aktivität als Array und aktualisiert die Statusvariablen der letzten Aktivität.
**Wichtig: nicht zyklisch aufrufen, da zu häufige Anfragen vom Server blockiert werden.**
```php
ECHOIO_GetLastActivity( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int | |

### CheckLoginStatus
```php
ECHOIO_CheckLoginStatus( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int | |

### LogOff
```php
ECHOIO_LogOff( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int | |

### GetDeviceList
```php
ECHOIO_GetDeviceList( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int | |

### GetDevice
```php
ECHOIO_GetDevice( int $InstanceID, string $deviceSerial, string $deviceType );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int | |
|$deviceSerial |string | |
|$deviceType |string | |

### UpdateAllDeviceVolumes
```php
ECHOIO_UpdateAllDeviceVolumes( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int | |

### UpdateDeviceList
```php
ECHOIO_UpdateDeviceList( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int | |

### UpdateStatus
```php
ECHOIO_UpdateStatus( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int | |