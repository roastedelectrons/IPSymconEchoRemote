[![Symcon Module](https://img.shields.io/badge/Symcon-PHPModul-blue.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Symcon Version](https://img.shields.io/badge/dynamic/json?color=blue&label=Symcon%20Version&prefix=%3E%3D&query=compatibility.version&url=https%3A%2F%2Fraw.githubusercontent.com%2Froastedelectrons%2FIPSymconEchoRemote%2Fmaster%2Flibrary.json)
![Module Version](https://img.shields.io/badge/dynamic/json?color=green&label=Module%20Version&query=version&url=https%3A%2F%2Fraw.githubusercontent.com%2Froastedelectrons%2FIPSymconEchoRemote%2Fmaster%2Flibrary.json)
![GitHub](https://img.shields.io/github/license/roastedelectrons/IPSymconEchoRemote)

IPSymconEchoRemote 2
===
Modul für IP-Symcon zur Steuerung der Musikwiedergabe und Sprachansagen (Text-To-Speech) auf Echo-Geräten. Es ermöglicht außerdem das Starten von Routinen, Versand von Push-Nachrichten an die Alexa-App, Auswertung der letzten Aktionen der Echo-Geräte sowie diverser Informationen wie Weckzeiten und ToDo-Listen.

**BREAKING-CHANGE:**
Ab Version 2.0 erfolgt die Authentifizierung ausschließlich mittels Refresh-Token, der einmalig (mit einem externen Tool) generiert und im Modul hinterlegt werden muss.

*DISCLAIMER: Das Modul verwendet eine nicht-dokumentierte Schnittstelle zu alexa.amazon.de. Daher kann die Funktion ohne Ankündigung jederzeit eingestellt werden.*

## Inhaltsverzeichnis

1. [Dokumentation der Module](#dokumenation-der-module)
2. [Einrichten in IP-Symcon](#einrichtung)
3. [Changelog](#changelog)
4. [Quellen](#quellen)

## Dokumenation der Module
- __Echo Device__ ([Dokumentation](Echo%20Device/README.md))  
	Modul zur Steuerung der Musikwiedergabe, Text-Ansagen und Ausführung von Routinen auf Echo-Geräten.

- __Echo IO__ ([Dokumentation](Echo%20IO))  
	Modul zu Authenzifizierung mit dem Amazon-Alexa-Account.

- __Echo Configurator__ ([Dokumentation](Amazon%20Echo%20Configurator))  
	Konfigurator zum Erstellen und Einrichten der Echo Device Instanzen.

## Einrichtung 

### Modul und Instanzen installieren
1. Im Module-Store das Modul *Echo Remote 2* installieren.
2. Im Objektbaum, der IP-Symcon Verwaltungskonsole eine *Amazon Echo Remote IO*-Instanz erstellen und den Refresh-Token (siehe unten) einfügen.
3. Anschließend eine *Amazon Echo Remote Konfigurator*-Instanz erstellen.
4. *Amazon Echo Remote Konfigurator* öffnen und für die gewünschten Echo-Geräte jeweils eine *Echo Remote* Instanz erstellen.

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

Version 2.4.1 (2024-01-10)
* Fix: Fehler beim Updaten der Automationen
* Change: Unbekannte PlayerStates werden ins Debug geschrieben und erzeuge keine Fehlermeldng mehr
* Change: Timeouts erhöht
* Change: Fehlermeldungen, wenn Rate Limit überschritten wird

Version 2.4 (2023-10-29)
* Neu: Musikwiedergabe auf Multiroom-Gruppen (mittels PlayMusic() und Favoriten)
   * *Hinweis*: Wird Musik auf einer Multiroom-Gruppe gestartet, erfolgt die Anzeige und Steuerung nur in der Instanz der Multiroom-Gruppe und nicht mehr in den Instanzen der Einzelgeräte.
   * Sonstige Befehle an Multiroom-Gruppen werden nur auf dem ersten Einzelgerät ausgeführt, wenn der Befehl nicht Multiroom-fähig ist.

* Letzte Aktivität und letztes Gerät:
   * Change: Letzte Aktivität wird wieder zyklisch über neue API abgefragt (ggf. in EchoIO-Instanz aktivieren), da Websockets nicht mehr unterstützt werden. Der WebSocket-Client (seit 2.3) kann nach dem Update manuell gelöscht werden. 
   * Neu: Variablen für Antwort, Person (Anzeige ca. 20 Sekunden verzögert), Intent und Zeit der letzten Aktivität
   * Fix: Wenn mehrere Echo-Geräte einen Sprachbefehl erkannt habe, werden nur die Variablen LastDevice und LastAction des Gerätes aktualisiert, das die Aktion auch tatsächlich ausgeführt hat
   * Fix: GetLastActivity liefert wieder deviceName zurück
   * Change: Variable LastDevice ist nun vom Typ String: Variablen-Wert:DeviceSerial, Profil-Wert:Gerätename

* Variablen und Profile:
   * Neu: Nicht benutzte Variablenprofile werden automatisch gelöscht
   * Fix: Assoziationen von Variablenprofilen werden nur noch dann neu gespeichert, wenn sie sich geändert haben
   * Fix: Bevor Variablen-Werte gesetzt werden, wird geprüft, ob die Variable existiert
   * Change: Variable Remote verwendet nun das Profil ~PlaybackPreviousNextNoStop
   * Change: Namen von Variablenprofilen vereinheitlicht

* Sonstiges:
   * Fix: UpdateStatus verlässt Semaphore nun korrekt
   * Fix: Nutze namespaces um Konflikte mit anderen Modulen zu vermeiden
   * Echo Pop zu Konfigurator hinzugefügt


Version 2.3 (2023-08-21)
* Neu: Websockets 
   * Auswertung der letzten Aktivität (Sprachbefehl und Gerät) erfolgt nun sofort per Push - kein Polling mehr notwendig
   * EchoIO-Instanz ist nun ein Splitter (Name und Prefix bleiben aus Kompatibilitätsgründen bestehen)
* Optimierungen: 
   * Optimiertes Handling von mehreren gleichzeitigen/hintereinanderfolgenden Automations-Befehlen (z.B. TextToSpeech, StartAlexaRoutine,...) um das Rate-Limit der API nicht zu überschreiten
   * Optimierung beim Aktualisieren von Routinen und den entsprechenden Variablenprofilen
* Fix: Anpassungen für Symcon 7.0 (Php 8.2) zur Vermeidung von type_errors
* Fix: Dateipfad des Cookies konnte nach Migration von IP-Symcon auf andere Plattform nicht gefunden werden
* Fix: In der Konfiguration von Ereignissen werden die Aktionen dieses Moduls nur noch angezeigt, wenn als Ziel auch eine Echo Remote Instanz ausgewählt ist
* Change: Erfordert min. IP-Symcon 6.1 (wegen Custom Headers Support des Websockets)
* Change: GetLastDevice in GetLastActivity umbenannt


Version 2.2.1 (2023-06-23)
* Fix: TextToSpeech an ALL_DEVICES spielt Ansagen nur noch auf Geräte vom Typ ECHO, KNIGHT und ROOK
* Fix: Lautstärke bei Lautsprecher-Paaren wird nicht mehr auf Null gesetzt
* Fix: Nutze LogMessage bei allen Fehlern (behebt Problem beim Erstellen von Instanzen bei fehlerhafter Internetverbindung)
* Fix: CookieRefreshTimer wird maximal auf zwei Wochen gesetzt
* Fix: CopyTuneInStationsToFavorites nutzt nun UpdateFormField

Version 2.2 (2023-04-29)
* Neu: Favoriten
   * Variable zum einfachen Starten von Musik verschiedener Musikanbieter
   * Favoritenliste kann in Instanz-Konfiguration editiert werden
   * verwendet intern die Funktion PlayMusic() (siehe Dokumentation neuer Funktionen)
   * werden als Playlist in neuer MediaPlayer-Kachel verwendet werden können
   * Favoriten sollen zukünftig die TuneIn-Senderliste ersetzen. Eine Migrationsfunktion vereinfacht die Übernahme der TuneIn-Sender in die Favoritenliste
* Unterstützung für MediaPlayer-Kachel der neuen Visualisierung vorbereitet
   * Für vollen Funktionsumfang sollten Variablen für Mediaplayer-Steuerung, erweiterte Informationen und Favoriten aktiviert werden
   * Standard-Variablenprofile für Mediaplayer-Steuerung werden bevorzugt verwendet, sofern vorhanden
   * Assoziationen der Profile der Variablen Fernbedienung und Mute geändert (ggf. sind Anpassungen in Skripten, Ablaufplänen und Events notwendig)
* Ansagen:
   * TextToSpeechVolume() und TextToSpeechEx() ändern die Lautstärke der Ansage und setzen sie danach wieder zurück
   * AnnouncementToAll() führt Ansagen auf allen im Account registrierten Geräten aus
   * TextToSpeechToAll() führt Ansagen auf allen im Account registrierten Geräten aus
* Weitere Neuerungen:
   * StopAll() stoppt Musikwiedergabe auf allen im Account registrierten Geräten
   * Variable für Online-Status des Echo-Gerätes
   * Alle Variablen können in der EchoRemote-Instanz de-/aktiviert werden
   * Player-Status wird Ereignis-basiert aktualisiert (so kann das Aktualisierungintervall der EchoRemote-Instanz größer gewählt werden - empfohlen: 60 sec.)
* Change: 
   * TextToSpeechEx() zusätzlicher options-Parameter muss übergeben werden (siehe Dokumentation neuer Funktionen)
   * Variablen, die in der Instanz-Konfiguration deaktiviert wurden, werden nun gelöscht
   * Interner Datenfluss vereinheitlicht
   * Nicht-unterstützte Funktionen entfernt (PlayAlbum, PlaySong, PlayPlaylist, PlayAmazonMusic, PlayAmazonPrimePlaylist, GetAmazonPrimeStationSectionList, SendDelete, JumpToMediaId)
* Fix: 
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

## Dokumentation neuer Funktionen
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
|_$instanceIDList_   |  Array mit InstanzID's auf denen die Ankündigung erfolgen soll. Wird ein leeres Array übergeben, erfolgt keine Ansage. Wird 'ALL_DEVICES' im Array übergeben, erfolgt die Ansage auf allen im Account registirerten Echo-Geräten| `[ 12345, 23456, 34567]` oder `['ALL_DEVICES']` |
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
|_$instanceIDList_   |  Array mit InstanzID's auf denen die Ankündigung erfolgen soll. Wird ein leeres Array übergeben, erfolgt keine Ansage. Wird 'ALL_DEVICES' im Array übergeben, erfolgt die Ansage auf allen im Account registirerten Echo-Geräten| `[ 12345, 23456, 34567]` oder `['ALL_DEVICES']` |
|_$tts_ | Ankündigung | `Text`|
|_$options_ | Optionen als Array | `[]` |
|_$options['volume']_ | Lautsärke während Ansage| `['volume' => 35]` |

_Beispiel:_
```php
$InstanceID = 12345; // InstanzID des Echo Remote Devices
$instanceIDList = [12345, 23456, 34567];

ECHOREMOTE_TextToSpeechEx( $InstanceID,  'Die Waschmaschine ist fertig', $instanceIDList, [] );

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

