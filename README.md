[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Symcon%20Version-5.5%20%3E-green.svg)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v54-v55-q4-2020/)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![Check Style](https://github.com/Wolbolar/IPSymconEchoRemote/workflows/Check%20Style/badge.svg)](https://github.com/Wolbolar/IPSymconEchoRemote/actions)


IPSymconEchoRemote 2
===
Dieses Modul ist ein Fork von https://github.com/Wolbolar/IPSymconEchoRemote und implementiert eine neue Authentifizierungsmethode analog zum [alexa_remote_control.sh](https://github.com/adn77/alexa-remote-control) Script. Dadurch sollte das Modul wieder auf allen Plattformen funktionsfähig sein (getestet: Windows, Symbox/Linux).

Die Authentifizierung erfolgt ausschließlich mittels Refresh-Token, der einmalig (mit einem externen Tool) generiert und im Modul hinterlegt werden muss. Damit verhält sich das Modul nun so wie die Alexa App.
Eine optimierte Überwachung des Anmelde- und Instanzstatus stellt sicher, dass ein automatischer Reconnect erfolgt, falls erforderlich.

**Disclaimer: Das Modul verwendet weiterhin eine nicht-dokumentierte Schnittstelle zu alexa.amazon.de. Amazon hat bereits angekündigt die Funktionalität dieser Seite weiter einschränken zu werden. Daher ist es wohl nur eine Frage der Zeit, wie lange das Modul funktionsfähig bleibt. Bevorzugt sollte daher auf alternative Lösungen, die offizielle API's verwenden, gewechselt werden.**

## Einrichtung 

### Migration

#### Altes Modul deinstallieren
1. In der IP-Symcon Verwaltungskonsole den Module Store öffenen.
2. Unter *Installiert > Echo Remote* das Modul öffnen und auf *Entfernen* klicken.

   **Wichtig: Die Frage, ob auch die Instanzen entfernt werden sollen, mit NEIN beantworten!**

#### Neues Modul installieren
1. Im Objektbaum das Module Control öffnen (*Kerninstanzen > Modules*).
2. Mit Klick auf das +-Zeichen das folgende Repository hinzufügen: https://github.com/roastedelectrons/IPSymconEchoRemote
3. Im Objektbaum die EchoIO Instanz öffenen und den generierten Refresh-Token einfügen und auf *übernehmen* klicken.


###  Refresh-Token generieren
Der Refresh-Token kann mit Hilfe des [Alexa-Cookie-CLI Tools (verfügbar für Windos, MacOS, Linux)](https://github.com/adn77/alexa-cookie-cli/releases/latest) auf einem beliebigen Rechner erstellt werden. Hierzu sind die folgenden Schritte notwendig:

#### Windows
1. Tool vom o.g. Link herunterladen und mit Doppelklick die Datei `alexa-cookie-cli-win-x64.exe`ausführen. Es öffnet sich die Konsole, in der folgende Meldung angezeigt wird:
   ```
   Error: You can try to get the cookie manually by opening http://localhost:8080/ with your browser. / null
   ```
2. Browser öffnen und die Seite http://localhost:8080/ aufrufen. Es wird nun die Amazon-Login Seite angezeigt. Hier mit dem Amazon-Account einloggen.
3. Wenn der Login erfolgreich war, wieder in die Konsole wechseln und den nun angezeigten Refresh-Token (beginnend mit `Atnr|...`) kopieren.
4. In der IP-Symcon Verwaltungskonsole im Objektbaum die EchoIO-Instanz öffnen und den generierten Refresh-Token im Feld *Refresh-Token* einfügen und auf *übernehmen* klicken.

#### MacOS
1. Tool vom o.g. Link herunterladen und im Finder den Download-Ordner öffnen. Rechts-Klick auf die Datei `alexa-cookie-cli-macos-x64`, `option`-Taste drücken und halten und `"alexa-cookie-cli-macos-x64" als Pfadname kopieren` auswählen.
2. Terminal.app öffenen, den Befehl `chmod 755 ` eingeben und den Pfadnamen dahinter einfügen. Durch Bestätigen mit `ENTER`-Taste wird die Datei ausführbar gemacht. Z.B.
   ```
   chmod 755 /User/<USERNAME>/Downloads/alexa-cookie-cli-macos-x64
   ```
3. Danach die Datei ausführen, in dem wieder der Pfad ins Terminal kopiert wird und mit `ENTER`-Taste ausführen. Z.B.
   ```
   /User/<USERNAME>/Downloads/alexa-cookie-cli-macos-x64
   ```
   Im Terminal wird nun folgende Meldung angezeigt:
   ```
   Error: You can try to get the cookie manually by opening http://localhost:8080/ with yout browser.  / null
   ```
4. Browser öffenen und die Seite http://localhost:8080/ aufrufen. Es wird nun eine Amazon-Login Seite angezeigt. Hier mit dem Amazon-Account einloggen.
5. Wenn der Login erfolgreich war, wieder ins Terminal wechseln und den nun angezeigten Refresh-Token (beginnend mit `Atnr|...`) kopieren.
6. In der IP-Symcon Verwaltungskonsole im Objektbaum die EchoIO-Instanz öffnen und den generierten Refresh-Token im Feld *Refresh-Token* einfügen und auf *übernehmen* klicken.



## Changelog

Version 2.2 (development)
* Neu: 
   * TextToSpeechVolume() und TextToSpeechEx() ändern die Lautstärke der Ansage und setzen sie danach wieder zurück
   * Favoriten: Variable zum Starten von Musik verschiedener Musikanbieter (Favoritenliste wird in Instanz-Konfiguration angelegt. Siehe Dokumentation von PlayMusic() zur Verwendung von SearchPhrase)
   * Variable für Online-Status
   * Variablen für Player-Steuerung können de-/aktiviert werden
   * Variable für TuneIn Radio kann de-/aktiviert werden
* Change: 
   * TextToSpeechEx() zusätzlicher options-Parameter muss übergeben werden (siehe Funktions-Doku)
   * Neues Profil für Mute-Variable (invertiert zum vorherigen Profil)
   * Neues Profil für Remote-Variable
   * Interner Datenfluss vereinheitlicht
   * Nicht-unterstützte Funktionen entfernt (PlayAlbum, PlaySong, PlayPlaylist, PlayAmazonMusic, PlayAmazonPrimePlaylist, GetAmazonPrimeStationSectionList, SendDelete, JumpToMediaId)
* Fix: 
   * Variablen, die in der Instanz-Konfiguration deaktiviert wurden, werden nun korrekt gelöscht
   * SetVolume() nutzt alternative Methode, sofern der Aufruf fehlgeschlagen ist

Version 2.1 (2023-03-14)

* Announcement
   * Neu: Announcement() für Einzelgeräte und Multiroom-Gruppen (Ansagen laufen parallel, aber nicht immer synchron)
   * Neu: AnnouncementEx() für mehrere Einzelgeräte
   * Annoucements müssen pro Gerät in der Alexa-App de-/aktiviert werden (Geräte > Echo und Alexa > Echo Gerät auswählen > Geräteeinstellungen (Zahnrad) > Kommunikation > Ankündigungen)
   * Wenn *Do-not-Disturb* aktiviert ist, erfolgen auf dem jeweiligen Gerät keine Ansagen
* TextToSpeech
   * Neu: TextToSpeech() für Einzelgeräte und Multiroom-Gruppen (Ansagen laufen parallel, aber nicht immer synchron)
   * Neu: TextToSpeechEx() für mehrere Einzelgeräte
   * Ansagen werden im Gegensatz zu Announcements immer ausgegeben
* Neu: Aktionen zur einfachen Ausführung von Announcements und TextToSpeech auf mehreren Echo-Geräten
* Neu: SendMobilePush() sendet Push Nachrichten an die Alexa-App
* Change: PlayMusic() ersetzt die meisten anderen Funktionen zum Starten von Musik wie PlaySong, PlayAlbum, PlayPlaylist, etc.
* Fix: MusicAlarm wird bei Auswertung der nächsten Alarmzeit auch berücksichtigt

*Dokumentation zu neuen Funktionen siehe unten*


Version 2.0 (2023-03-04)

* **BREAKING-CHANGE: Authentifizierung erfolgt ausschließlich mittels Token, der über ein externes Tool erzeugt werden muss (keine Benutzername/Passwort/2FA oder Cookie Anmeldung mehr möglich)**
* Neu: Automatischer Reconnect
* Neu: weitere DeviceTypes
* Neu: Schalter in EchoIO-Instanz für erweiterte Fehlermeldungen im MessageLog
* Fix: TuneIn Sender können gestartet werden
* Fix: Zeiten für nächsten Alarm (Wecker) werden korrekt ausgewertet
* Fix: Variablen für *letzte Aktion* und *letzter Befehl* (Echo Device) werden nun nur noch aktualisiert, wenn eine Aktion ausgeführt wurde
* Fix: Variable für *letztes Gerät* (EchoIO) wird nun bei jeder neuen Aktion aktualisiert (auch wenn zwei oder mehr Aktionen hintereinander vom selben Gerät ausgingen)
* Fix: Mehrere EchoIO-Instanzen mit unterschiedlichen Amazon-Accounts funktionieren nun korrekt
* Fix: Konfigurator zeigt nun alle Echo Remote Device Instanzen an, auch wenn diese falsch konfiguriert oder nicht im Amazon Account vorhanden sind
* Change: Unknown DeviceType-Meldung wird nicht mehr im Message-Log, sondern im Debug des Konfigurators angezeigt
* Change: Fehlerbehandlung optimiert
* Change: Erfordert mindestens IP-Symcon Version 5.5 oder neuer 

## Quellen
1. Alexa-Cookie-CLI: https://github.com/adn77/alexa-cookie-cli
2. Anleitung für alexa_remote_control.sh und Alexa-Cookie-CLI: https://blog.loetzimmer.de/2021/09/alexa-remote-control-shell-script.html
3. alexa_remote_control.sh (Shell): Dieses Modul implementiert die Funktionalität des Shell-Scripts https://github.com/adn77/alexa-remote-control
4. alexapy (Python): (genutzt für Announcements, SendMobilePush): https://gitlab.com/keatontaylor/alexapy/-/blob/dev/alexapy/alexaapi.py 
5. alexa-cookie (NodeJS): Authentifizierung und Generierung Refresh-Token https://github.com/Apollon77/alexa-cookie
6. alexa-remote (NodeJS): Echo Steuerung https://github.com/Apollon77/alexa-remote
7. openhab-addon Amazon Echo Control (Java): https://github.com/openhab/openhab-addons/tree/main/bundles/org.openhab.binding.amazonechocontrol/src/main/java/org/openhab/binding/amazonechocontrol/internal
8. Sequence Command Discovery: https://github.com/custom-components/alexa_media_player/wiki/Developers%3A-Sequence-Discovery
9. Amazon Alexa Logo by [icons8]( https://icons8.com/icon/X28a9yj_gkpy/amazon-alexa-logo)

## Dokumentation

### Neue Funktionen
**PlayMusic**

```php
ECHOREMOTE_PlayMusic(int $InstanceID, string $searchPhrase, string $musicProviderId);
``` 
| Parameter        |  Beschreibung | Wert |
|------------------|---------------|------|
|_$InstanceID_     | InstanzID des Echo Remote Devices| |
|_$searchPhrase_   |  Suchanfrage |z.B. "_Songname_ von _Interpret_", "_Albumname_ von _Interpret_",  "_Playlistname_", "_Radiosender-Name_" |
|_$musicProviderId_ | Anbieter |z.b. 'DEFAULT', 'TUNEIN', 'AMAZON_MUSIC', 'CLOUDPLAYER', 'SPOTIFY', 'APPLE_MUSIC', 'DEEZER', 'I_HEART_RADIO' |

_Beispiele:_
```php
$InstanceID = 12345; // InstanzID des Echo Remote Devices

// Song von Amazon Music abspielen (Amazon Music Unlimited notwendig, sonst wird irgendein Song abgespielt)
ECHOREMOTE_PlayMusic( $InstanceID, 'Songname von Interpret', 'AMAZON_MUSIC');

// Album von Spotify abspielen
ECHOREMOTE_PlayMusic( $InstanceID, 'Ablumname von Interpret', 'SPOTIFY');

// Playlist 'Mein Discovery Mix' von Amazon Music abspielen (für andere Playlisten ist Amazon Music Unlimited notwendig)
ECHOREMOTE_PlayMusic( $InstanceID, 'Mein Discovery Mix', 'CLOUDPLAYER');

// Radiosender abspielen
ECHOREMOTE_PlayMusic( $InstanceID, 'NDR 2 Niedersachsen', 'TUNEIN');

```
**SendMobilePush**

Sendet eine Push-Nachricht an die Alexa-App.

```php
ECHOREMOTE_SendMobilePush(int $InstanceID, string $title, string $message);
``` 
| Parameter        |  Beschreibung | Wert |
|------------------|---------------|------|
|_$InstanceID_     | InstanzID des Echo Remote Devices| |
|_$title_   |  Titel | |
|_$message_ | Nachricht | |

_Beispiele:_
```php
$InstanceID = 12345; // InstanzID des Echo Remote Devices

ECHOREMOTE_SendMobilePush( $InstanceID, 'IP-Symcon', 'Die Waschmaschine ist fertig');

```

**AnnouncementEx**

Annoucements müssen pro Gerät in der Alexa-App de-/aktiviert werden (Geräte > Echo und Alexa > Echo Gerät auswählen > Geräteeinstellungen (Zahnrad) > Kommunikation > Ankündigungen).

Wenn *Do-not-Disturb* aktiviert ist, erfolgen auf dem jeweiligen Gerät keine Ansagen.

```php
ECHOREMOTE_AnnouncementEx(int $InstanceID, string $tts, array $instanceIDList, array $options );
``` 
| Parameter        |  Beschreibung | Wert |
|------------------|---------------|------|
|_$InstanceID_     | InstanzID des ausführenden Echo Remote Devices| |
|_$instanceIDList_   |  Array mit InstanzID's auf denen die Ankündigung erfolgen soll. Wird ein leeres Array übergeben, erfolgt keine Ansage| `[ 12345, 23456, 34567]` |
|_$tts_ | Ankündigung | `Text`|
|_$options_ | Optionen (aktuell keine verfügbar) | `[]` |

_Beispiel:_
```php
$InstanceID = 12345; // InstanzID des Echo Remote Devices
$instanceIDList = [12345, 23456, 34567];

ECHOREMOTE_AnnouncementEx( $InstanceID,  'Die Waschmaschine ist fertig', $instanceIDList, [] );

```

**TextToSpeechEx**

```php
ECHOREMOTE_TextToSpeechEx(int $InstanceID, string $tts, array $instanceIDList, array $options );
``` 
| Parameter        |  Beschreibung | Wert |
|------------------|---------------|------|
|_$InstanceID_     | InstanzID des ausführenden Echo Remote Devices| |
|_$instanceIDList_   |  Array mit InstanzID's auf denen die Ankündigung erfolgen soll. Wird ein leeres Array übergeben, erfolgt keine Ansage| `[ 12345, 23456, 34567]` |
|_$tts_ | Ankündigung | `Text`|
|_$options_ | Optionen als Array | `[]` |
|_$options['volume']_ | Lautsärke während Ansage| `['volume' => 35]` |

_Beispiel:_
```php
$InstanceID = 12345; // InstanzID des Echo Remote Devices
$instanceIDList = [12345, 23456, 34567];

ECHOREMOTE_TextToSpeechEx( $InstanceID,  'Die Waschmaschine ist fertig', $instanceIDList, ['volume' => 50] );

```

**TextToSpeechVolume**

Wie TextToSpeech, jedoch kann die Lautstärke der Ansage übergeben werden. Nach der Ansage wird die Lautstärke wieder auf den vorherigen Wert zurückgesetzt.

```php
ECHOREMOTE_TextToSpeechVolume(int $InstanceID, string $tts, int $volume );
``` 
| Parameter        |  Beschreibung | Wert |
|------------------|---------------|------|
|_$InstanceID_     | InstanzID des ausführenden Echo Remote Devices| |
|_$tts_ | Ankündigung | `Text`|
|_$volume_ | Lautsärke der Ansage| `50` |

_Beispiel:_
```php
$InstanceID = 12345; // InstanzID des Echo Remote Devices

ECHOREMOTE_TextToSpeechVolume( $InstanceID,  'Die Waschmaschine ist fertig', 50 );

```


IP-Symcon PHP module for remote control of an Amazon Echo / Amazon Dot / Amazon Echo Show from IP-Symcon.

Modul für IP-Symcon ab Version 5.0. Ermöglicht die Fernsteuerung mit einem Amazon Echo / Amazon Dot / Amazon Echo Show von IP-Symcon aus.

 - [Deutsche Dokumentation](docs/de/README.md "Deutsche Dokumentation") (Nicht Aktuell)
 
Module for IP-Symcon from Version 5.0. With this module IP-Symcon can remote control an  Amazon Echo / Amazon Dot / Amazon Echo Show.

 - [English Documentation](docs/en/README.md "English documentation")  (Nicht Aktuell)

