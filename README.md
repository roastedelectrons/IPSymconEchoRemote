[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Symcon%20Version-5.0%20%3E-green.svg)](https://www.symcon.de/forum/threads/38222-IP-Symcon-5-0-verf%C3%BCgbar)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![Check Style](https://github.com/Wolbolar/IPSymconEchoRemote/workflows/Check%20Style/badge.svg)](https://github.com/Wolbolar/IPSymconEchoRemote/actions)


IPSymconEchoRemote 2
===
Dieses Modul ist ein Fork von https://github.com/Wolbolar/IPSymconEchoRemote und implementiert eine neue Authentifizierungsmethode. Dadurch sollte das Modul wieder auf allen Plattformen funktionsfähig sein (getestet: Windows, Symbox/Linux).

Die Authentifizierung erfolgt ausschließlich mittels Refresh-Token, der einmalig (mit einem externen Tool) generiert und im Modul hinterlegt werden muss (2FA und manueller Cookie sind nicht mehr verfügbar).
Eine optimierte Überwachung des Anmelde- und Instanzstatus stellt sicher, dass eine automatische Erneuerung der Cookies bzw. ein LogIn erfolgt.

**Disclaimer: Das Modul verwendet weiterhin eine nicht-dokumentierte Schnittstelle zu alexa.amazon.de. Amazon hat bereits angekündigt die Funktionalität dieser Seite weiter einschränken zu werden. Daher ist es wohl nur eine Frage der Zeit, wie lange das Modul funktionsfähig bleibt. Bevorzugt sollte daher auf alternative Lösungen, die offizielle API's verwenden, gewechselt werden.**

## Einrichtung 

###  Refresh-Token generieren
Der Refresh-Token kann mit Hilfe des [Alexa-Cookie-CLI Tools (verfügbar für Windos, MacOS, Linux)](https://github.com/adn77/alexa-cookie-cli/releases/latest) auf einem beliebigen Rechner erstellt werden. Hierzu sind die folgenden Schritte notwendig:

#### Windows
1. Tool vom o.g. Link herunterladen und mit Doppelklick die Datei `alexa-cookie-cli-win-x64.exe`ausführen. Es öffnet sich die Konsole, in der folgende Meldung angezeigt wird:
   ```
   Error: You can try to get the cookie manually by opening http://localhost:8080/ with your browser. / null
   ```
2. Browser öffnen und die Seite http://localhost:8080/ aufrufen. Es wird nun die Amazon-Login Seite angezeigt. Hier mit mit dem Amazon-Account einloggen.
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
4. Browser öffenen und die Seite http://localhost:8080/ aufrufen. Es wird nun eine Amazon-Login Seite angezeigt. Hier mit mit dem Amazon-Account einloggen.
5. Wenn der Login erfolgreich war, wieder ins Terminal wechseln und den nun angezeigten Refresh-Token (beginnend mit `Atnr|...`) kopieren.
6. In der IP-Symcon Verwaltungskonsole im Objektbaum die EchoIO-Instanz öffnen und den generierten Refresh-Token im Feld *Refresh-Token* einfügen und auf *übernehmen* klicken.


### Migration

#### Altes Modul deinstallieren
1. In der IP-Symcon Verwaltungskonsole den Module Store öffenen.
2. Unter *Installiert > Echo Remote* das Modul öffnen und auf *Entfernen* klicken.

   **Wichtig: Die Frage, ob auch die Instanzen entfernt werden sollen, mit NEIN beantworten!**

#### Neues Modul installieren
1. Im Objektbaum das Module Control öffnen (*Kerninstanzen > Modules*).
2. Mit Klick auf das +-Zeichen das folgende Repository hinzufügen: https://github.com/roastedelectrons/IPSymconEchoRemote
3. Im Objektbaum die EchoIO Instanz öffenen und den generierten Refresh-Token einfügen und auf *übernehmen* klicken.

## Changelog

Version 2.0 (2023-02-19)

* BREAKING-CHANGE: Authentifizierung erfolgt ausschließlich mittel Token, der über ein externes Tool erzeugt werden muss (kein Benutzername/Passwort, 2FA oder Cookie Anmeldung mehr möglich)
* Neu: Automatischer Reconnect
* Neu: weitere DeviceTypes
* Fix: TuneIn Sender können gestartet werden
* Fix: Zeiten für nächsten Alarm (Wecker) werden korrekt ausgewertet
* Change: Unknown DeviceType-Meldung wird nicht mehr im Message-Log, sondern im Debug ddes Konfigurators angezeigt

## Quellen
1. Alexa-Cookie-CLI: https://github.com/adn77/alexa-cookie-cli
2. Anleitung für alexa_remote_control.sh und Alexa-Cookie-CLI: https://blog.loetzimmer.de/2021/09/alexa-remote-control-shell-script.html
3. alexa_remote_control.sh: https://github.com/adn77/alexa-remote-control

## Dokumentation (nicht aktuell)

IP-Symcon PHP module for remote control of an Amazon Echo / Amazon Dot / Amazon Echo Show from IP-Symcon.

Modul für IP-Symcon ab Version 5.0. Ermöglicht die Fernsteuerung mit einem Amazon Echo / Amazon Dot / Amazon Echo Show von IP-Symcon aus.

 - [Deutsche Dokumentation](docs/de/README.md "Deutsche Dokumentation")
 
Module for IP-Symcon from Version 5.0. With this module IP-Symcon can remote control an  Amazon Echo / Amazon Dot / Amazon Echo Show.

 - [English Documentation](docs/en/README.md "English documentation") 

