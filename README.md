[![Symcon Module](https://img.shields.io/badge/Symcon-PHPModul-blue.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Symcon Version](https://img.shields.io/badge/dynamic/json?color=blue&label=Symcon%20Version&prefix=%3E%3D&query=compatibility.version&url=https%3A%2F%2Fraw.githubusercontent.com%2Froastedelectrons%2FIPSymconEchoRemote%2Fmaster%2Flibrary.json)
![Module Version](https://img.shields.io/badge/dynamic/json?color=green&label=Module%20Version&query=version&url=https%3A%2F%2Fraw.githubusercontent.com%2Froastedelectrons%2FIPSymconEchoRemote%2Fmaster%2Flibrary.json)
![GitHub](https://img.shields.io/github/license/roastedelectrons/IPSymconEchoRemote)

IPSymconEchoRemote 2
===
Modul für IP-Symcon zur Steuerung der Musikwiedergabe und Sprachansagen (Text-To-Speech) auf Echo-Geräten. Es ermöglicht außerdem das Starten von Routinen, Versand von Push-Nachrichten an die Alexa-App, Auswertung der letzten Aktionen der Echo-Geräte sowie diverser Informationen wie Weckzeiten und ToDo-Listen.

**BREAKING-CHANGE:**
Ab Version 2.0 erfolgt die Authentifizierung ausschließlich mittels Refresh-Token, der einmalig (mit einem externen Tool) generiert und im Modul hinterlegt werden muss.

*DISCLAIMER: Bei diesem Modul handelt es sich um ein privates Projekt für die persönliche Nutzung. Es ist keine offizielle Integration von oder für Amazon Alexa. Daher kann die Funktion ohne Ankündigung jederzeit eingestellt werden.*

## Inhaltsverzeichnis

1. [Dokumentation der Module](#dokumentation-der-module)
2. [Einrichten in IP-Symcon](#einrichtung)
3. [Changelog](#changelog)
4. [Quellen](#quellen)

## Dokumentation der Module
- __Echo Device__ ([Dokumentation](Echo%20Device/README.md))  
	Modul zur Steuerung der Musikwiedergabe, Text-Ansagen und Ausführung von Routinen auf Echo-Geräten.

- __Echo IO__ ([Dokumentation](Echo%20IO))  
	Modul zu Authenzifizierung mit dem Amazon-Alexa-Account.

- __Echo Configurator__ ([Dokumentation](Echo%20Configurator))  
	Konfigurator zum Erstellen und Einrichten der Echo Device Instanzen.

- __Echo Bot__ ([Dokumentation](EchoBot))  
	Der EchoBot kann auf einen Sprachbefehl reagieren und verschiedene Aktionen ausführen, wie z.B. eine direkte Text-To-Speech Antwort auf dem Echo-Gerät ausgeben, das angesprochen wurde oder ein Skript in IP-Symcon ausführen.

- __Alexa List__ ([Dokumentation](AlexaList))  
	Modul zur Darstellung und Bearbeitung von Alexa Einkaufs- und Aufgabenlisten in IP-Symcon.

- __Alexa Smart Home Device__ ([Dokumentation](AlexaSmartHomeDevice))  
	Modul zum Steuern von mit Alexa verbundenen Smart-Home-Geräten aus IP-Symcon heraus.

- __Alexa Smart Home Configurator__ ([Dokumentation](AlexaSmartHomeConfigurator))  
	Konfigurator zum Erstellen und Einrichten von Alexa Smart Home Device Instanzen.

## Einrichtung 

### Modul und Instanzen installieren
1. Im Module-Store das Modul *Echo Remote 2* installieren.
2. Im Objektbaum, der IP-Symcon Verwaltungskonsole eine *Echo IO*-Instanz erstellen und den Refresh-Token (siehe unten) einfügen.
3. Anschließend eine *Echo Konfigurator*-Instanz erstellen.
4. *Echo Konfigurator* öffnen und für die gewünschten Echo-Geräte jeweils eine *Echo Device* Instanz erstellen.

###  Refresh-Token generieren
Der Refresh-Token kann mit Hilfe des [Alexa-Cookie-CLI Tools (verfügbar für Windos, MacOS, Linux)](https://github.com/adn77/alexa-cookie-cli/releases/latest) auf einem beliebigen Rechner erstellt werden. Hierzu sind die folgenden Schritte notwendig:

#### Voraussetzungen
Im Amazon-Konto muss **Zwei-Schritt-Verifizierung (2FA) mit Authentifizierungs-App** aktiviert sein. Verifizierungscodes per SMS/Email funktionieren nicht!

#### Windows
1. Tool vom o.g. Link herunterladen und mit Doppelklick die Datei `alexa-cookie-cli-win-x64.exe`ausführen. Es öffnet sich die Konsole, in der folgende Meldung angezeigt wird:
   ```
   Error: You can try to get the cookie manually by opening http://127.0.0.1:8080 with your browser. / null
   ```
2. Browser öffnen und die Seite http://127.0.0.1:8080 aufrufen. Es wird nun die Amazon-Login Seite angezeigt. Hier mit dem Amazon-Account einloggen.
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
   Error: You can try to get the cookie manually by opening http://127.0.0.1:8080 with yout browser.  / null
   ```
4. Browser öffenen und die Seite http://127.0.0.1:8080 aufrufen. Es wird nun eine Amazon-Login Seite angezeigt. Hier mit dem Amazon-Account einloggen.
5. Wenn der Login erfolgreich war, wieder ins Terminal wechseln und den nun angezeigten Refresh-Token (beginnend mit `Atnr|...`) kopieren.
6. In der IP-Symcon Verwaltungskonsole im Objektbaum die EchoIO-Instanz öffnen und den generierten Refresh-Token im Feld *Refresh-Token* einfügen und auf *übernehmen* klicken.


## Changelog

Version 2.7 (2025-07-22)
* Neu: Alexa Smart Home Geräte
   * Geräte, die mit Alexa verbunden sind, können direkt aus Symcon gesteuert werden. Besonders geeignet für Geräte, für die kein Symcon Modul verfügbar ist, die aber eine Alexa-Integration haben.
   * Unterstützung für Thermostate, Klimaanlagen, Licht, Steckdosen, Rolläden, Schlösser, Szenen und weitere
   * Einrichtung: Alexa Smart Home Konfigurator anlegen und aus diesem heraus die gewünschten Geräte-Instanzen erstellen
* EchoIO: Verbesserte Fehlerbehandlung und -meldungen

Version 2.6 (2025-05-18)
* Neu: Alexa Einkauf- und ToDo-Listen (Modul)
   * Einträge hinzufügen, abhaken und löschen per Skript
   * Eigene Darstellung für Tile-Visualisierung  
* Änderung: Einkauf- und Aufgabenlisten aus EchoDevice Instanz entfernt

Version 2.5.1 (2024-09-24)
* Optimierung des Ratelimits von GetLastActivity
* Fix: Bei Einkaufs- und ToDo-Listen werden nun alle Einträge geladen 

Version 2.5 (2024-05-05)
* EchoBot
   * Wenn ein Sprachbefehl (dieser muss als Auslöser in einer Alexa-Routine definiert werden) von einem Echo-Gerät empfangen wurde, können folgende Aktionen ausgeführt werden:
      * Text-to-speech Antwort in Abhängkeit vom angesprochenen Echo-Gerät ausgeben
      * Unterschiedliche Aktionen in Abhängigkeit vom angesprochenen Echo-Gerät ausführen
      * Skript in IP-Symcon ausführen
* Neue Aktion zum Ausführen von unterschiedlichen Aktionen in Abhängigkeit vom zuletzt angesprochenen Echo-Gerätes (zur Verwendung in Szenen des Symcon Alexa Moduls)
* Änderungen bei letzter Aktivität (GetLastActivity):
   * Möglichkeit zur periodischen Abfrage entfernt (alternativ kann der EchoBot oder die neue Aktion in Verbindung mit dem Symcon Alexa Modul verwendet werden)
   * Limit um zu verhindern, dass GetLastActivity zu häufig aufgerufen wird 
   * GetLastActivity liefert die InstanzID des Gerätes im Array zurück
* Neu: Variablen zum De-/Aktivieren der Wecker
* Neu: neue DeviceTypes hinzugefügt


Version 2.4.1 (2024-02-07) 
* Change: Nutze neue API für letzte Aktivität
* New: Abfrageintervall für letzte Aktivität kann in Experteneinstellungen eingestellt werden
* Fix: Wartezeit zwischen zwei Befehlen erhöht, um Rate Limit nicht zu überschreiten
* Change: Fehlermeldungen, wenn Rate Limit überschritten wird
* Fix: Fehler beim Updaten der Automationen
* Change: Unbekannte PlayerStates werden ins Debug geschrieben und erzeugen keine Fehlermeldng mehr
* Change: Timeouts erhöht

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
