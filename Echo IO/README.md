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
|TimerLastAction | CheckBox | Letzte Aktion auswerten|
|LogMessageEx | CheckBox | Erweiterte Log Meldungen|

## Statusvariablen und Profile

|Ident| Typ| Profil| Beschreibung |
|-----| -----| -----| ----- |
|last_device |string |Echo.LastDevice |letztes Gerät |
|cookie_expiration_date |int |~UnixTimestamp |Cookie expiration date |

## PHP-Befehlsreferenz

### LogIn
```php
ECHOIO_LogIn( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int | |

### GetLastActivity
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