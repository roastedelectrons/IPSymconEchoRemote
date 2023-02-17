[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Symcon%20Version-5.0%20%3E-green.svg)](https://www.symcon.de/forum/threads/38222-IP-Symcon-5-0-verf%C3%BCgbar)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![Check Style](https://github.com/Wolbolar/IPSymconEchoRemote/workflows/Check%20Style/badge.svg)](https://github.com/Wolbolar/IPSymconEchoRemote/actions)


IPSymconEchoRemote 2
===
Dieses Modul ist ein Fork von https://github.com/Wolbolar/IPSymconEchoRemote und implementiert eine neue Authentifizierungsmethode. Dadurch sollte das Modul wieder auf allen Plattformen funktionsfähig sein (getestet: Windows, Symbox/Linux).

Die Authentifizierung erfolgt ausschließlich mittelt Refresh-Token, der einmalig generiert und im Modul hinterlegt werden muss (2FA und manueller Cookie sind nicht mehr verfügbar).
Eine optimierte Überwachung des Anmelde- und Instanzstatus stellt sicher, dass eine automatische Erneuerung der Cookies bzw. ein LogIn erfolgt.

**Disclaimer: Das Modul verwendet weiterhin eine nicht-öffentlich dokumentierte Schnittstelle zu alexa.amazon.de. Amazon hat bereits angekündigt die Funktionalität dieser Seite weiter einschränken zu werden. Daher ist es wohl nur eine Frage der Zeit, wie lange das Modul funktionsfähig bleibt. Bevorzugt sollte daher auf alternative Lösungen, die offizielle API's verwenden, gewechselt werden.**

## Einrichtung 

###  Refresh-Token generieren
Der Refresh-Token kann mit Hilfe des [Alexa-Cookie-CLI Tools (verfügbar für Windos, MacOS, Linux)](https://github.com/adn77/alexa-cookie-cli/releases/latest) auf einem beliebigen Rechner erstellt werden.
1. Starten von alexa-cookie-cli
2. Im Browser öffnen: http://localhost:8080/
3. Anmeldung bei Amazon durchführen
4. Token aus der Kommandozeile kopieren und im Konfigurationsformular der EchoIO-Instanz in IP-Symcon einfügen.


### Migration

#### Altes Modul deinstallieren
Module Store öffenen und unter *Installiert > Echo Remote* auf *Entfernen* klicken.
**Wichtig: Die Frage, ob auch die Instanzen entfernt werden sollen, mit NEIN beantworten!**

#### Neues Modul installieren
1. Im Objektbaum das Module Control öffnen (*Kerninstanzen > Modules*).
2. Mit Klick auf das +-Zeichen das folgende Repository hinzufügen: https://github.com/roastedelectrons/IPSymconEchoRemote
3. Im Objektbaum die EchoIO Instanz öffenen und den generierten Refresh-Token einfügen und auf *übernehmen* klicken.


## Dokumentation (nicht aktuell)

IP-Symcon PHP module for remote control of an Amazon Echo / Amazon Dot / Amazon Echo Show from IP-Symcon.

Modul für IP-Symcon ab Version 5.0. Ermöglicht die Fernsteuerung mit einem Amazon Echo / Amazon Dot / Amazon Echo Show von IP-Symcon aus.

 - [Deutsche Dokumentation](docs/de/README.md "Deutsche Dokumentation")
 
Module for IP-Symcon from Version 5.0. With this module IP-Symcon can remote control an  Amazon Echo / Amazon Dot / Amazon Echo Show.

 - [English Documentation](docs/en/README.md "English documentation") 

