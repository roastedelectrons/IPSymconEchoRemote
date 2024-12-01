# AlexaList
Modul zur Darstellung und Bearbeitung von Alexa Einkaufs- und Aufgabenlisten in IP-Symcon.

## Inhaltsverzeichnis
1. [Funktionsumfang](#funktionsumfang)
2. [Konfiguration der Instanz](#konfiguration-der-instanz)
3. [Statusvariablen und Profile](#statusvariablen-und-profile)
4. [PHP-Befehlsreferenz](#php-befehlsreferenz)

## Funktionsumfang
* Einträge hinzufügen, abhaken und löschen per Skript und TileVisu
* Eigene Darstellung für Tile-Visualisierung

## Konfiguration der Instanz

|Eigenschaft| Typ| Beschreibung| Wert |
|-----| -----| -----| ----- |
|ListID | Select | Liste | `Einkaufsliste (Standard)`, `Aufgabenliste (Standard)`, `Eigene Liste`|
|ShowCompletedItems | CheckBox | Zeige erledigte Einträge | `false`|
|DeleteCompletedItems | CheckBox | Lösche erledigte Einträge von Liste | `false`|
|UpdateInterval | NumberSpinner | Aktualisierungsintervall | |

## Statusvariablen und Profile

|Ident| Typ| Profil| Beschreibung |
|-----| -----| -----| ----- |
|List |string |~TextBox |Liste |
|AddItem |string | |Eintrag hinzufügen |

## PHP-Befehlsreferenz

### AddItem
```php
ALEXALIST_AddItem( int $InstanceID, string $text );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der AlexaList-Instanz |
|$text |string | |

### CheckItem
```php
ALEXALIST_CheckItem( int $InstanceID, string $itemText );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der AlexaList-Instanz |
|$itemText |string | |

### CheckItemByID
```php
ALEXALIST_CheckItemByID( int $InstanceID, string $itemID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der AlexaList-Instanz |
|$itemID |string | |

### DeleteItem
```php
ALEXALIST_DeleteItem( int $InstanceID, string $itemText );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der AlexaList-Instanz |
|$itemText |string | |

### DeleteItemByID
```php
ALEXALIST_DeleteItemByID( int $InstanceID, string $itemID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der AlexaList-Instanz |
|$itemID |string | |

### GetListItems
```php
ALEXALIST_GetItems( int $InstanceID, bool $includeCompletedItems );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der AlexaList-Instanz |
|$includeCompletedItems |bool | |

### UncheckItemByID
```php
ALEXALIST_UncheckItemByID( int $InstanceID, string $itemID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der AlexaList-Instanz |
|$itemID |string | |

### Update
```php
ALEXALIST_Update( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der AlexaList-Instanz |