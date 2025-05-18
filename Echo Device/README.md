# Echo Device
Modul zur Steuerung der Musikwiedergabe, Text-Ansagen und Ausführung von Routinen auf Echo-Geräten.

## Inhaltsverzeichnis
1. [Funktionsumfang](#funktionsumfang)
2. [Konfiguration der Instanz](#konfiguration-der-instanz)
3. [Statusvariablen und Profile](#statusvariablen-und-profile)
4. [PHP-Befehlsreferenz](#php-befehlsreferenz)

## Funktionsumfang
* Musiksteuerung
  * Fernbedienung (Zurück, Stop, Play, Pause, Weiter)
  * Zufallswiedergabe (Aus, An)
  * Wiederholen (Aus, An)
  * Lautstärke
  * Stummschaltung (Aus, An)
  * Favoriten (Starten von Musik verschiedener Musikanbieter)
  * Favoriten (Playlist) (wird für den MediaPlayer der neuen Visu verwendet)
  * TuneIn Radio
* Sprachansagen (Text to Speech) und Ankündigungen auf einem, mehreren oder allen Echo Geräten
* Push-Nachrichten an Alexa-App senden
* Bitte nicht stören (Bitte nicht stören aufheben, Bitte nicht stören)
* Starten von Alexa-Routinen
* Aktionen ausführen (Wetter, Verkehr, Kurzes Briefing, Guten Morgen, Singe ein Lied, Erzähle eine Geschichte, Erzähle einen Witz, Erzähle eine Funfact)
* Uhrzeit der nächsten Weckzeit auslesen
* letzte Aktion auslesen

## Konfiguration der Instanz

|Eigenschaft| Typ| Beschreibung |
|-----| -----| ----- |
|Devicetype | ValidationTextBox | Geräte Typ|
|Devicenumber | ValidationTextBox | Geräte Nummer|
|updateinterval | NumberSpinner | Aktualisierungsintervall|

***Variablen***

|Eigenschaft| Typ| Beschreibung |
|-----| -----| ----- |
|PlayerControl | CheckBox | Variablen für Mediaplayer-Steuerung anlegen (Fernbedienung, Zufallswiedergabe, Wiederholen, Lautstärke)|
|ExtendedInfo | CheckBox | Variablen für erweiterte Informationen anlegen (Titel, Interpret, Album, Cover)|
|Mute | CheckBox | Variable für Mute anlegen|
|DND | CheckBox | Variable für Bitte nicht stören anlegen|
|AlarmInfo | CheckBox | Variablen für Weckzeiten anlegen (nächste Weckzeit, letzte Weckzeit)|
|EchoActions | CheckBox | Variable für Aktionen anlegen (z.B. Kurzes Briefing, Verkehr, Wetter, etc.)|
|EchoTTS | CheckBox | Variable für Text-To-Speech anlegen|
|LastAction | CheckBox | Variablen für letzte Aktion anlegen (Funktion muss in EchoIO-Instanz aktiviert werden)|
|OnlineStatus | CheckBox | Variable für Online-Status anlegen|

***Favoriten***

|Eigenschaft| Typ| Beschreibung |
|-----| -----| ----- |
|EchoFavorites | CheckBox | Variable für Favoriten anlegen|
|FavoritesList | List | Favoriten|

***Alexa Routinen***

|Eigenschaft| Typ| Beschreibung |
|-----| -----| ----- |
|routines_wf | CheckBox | Variable für Alexa Routinen anlegen|
|routines | List | Alexa Routinen|

***Layouteinstellungen für erweiterte Informationen***

|Eigenschaft| Typ| Beschreibung |
|-----| -----| ----- |
|Cover | CheckBox | seperate Variable für das Cover als HTML Bild anlegen|
|Title | CheckBox | seperate Variable für den Titel als HTML anlegen|
|TitleColor | SelectColor | Farbe des Titels|
|TitleSize | Select | Größe des Titels|
|Subtitle1 | CheckBox | seperate Variable für den Subtitel 1 als HTML anlegen|
|Subtitle1Color | SelectColor | Farbe des Subtitels 1|
|Subtitle1Size | Select | Größe des Subtitels 1|
|Subtitle2 | CheckBox | seperate Variable für den Subtitel 2 als HTML anlegen|
|Subtitle2Color | SelectColor | Farbe des Subtitels 2|
|Subtitle2Size | Select | Größe des Subtitels 2|

***TuneIn Stationen (wird zukünftig durch Favoriten ersetzt)***

|Eigenschaft| Typ| Beschreibung |
|-----| -----| ----- |
|EchoTuneInRemote | CheckBox | Variable für TuneIn-Stationen anlegen|
|TuneInStations | List | TuneIn stations|

## Statusvariablen und Profile

|Ident| Typ| Profil| Beschreibung |
|-----| -----| -----| ----- |
|EchoRemote |integer |~PlaybackPreviousNextNoStop |Fernbedienung |
|EchoShuffle |boolean |~Shuffle |Zufallswiedergabe |
|EchoRepeat |boolean |~Switch |Wiederholen |
|EchoVolume |integer |~Volume |Lautstärke |
|Mute |boolean |~Mute |Stummschaltung |
|EchoInfo |string |~HTMLBox |Info |
|EchoFavorites |string |Echo.Favorites.&lt;InstanceID&gt; |Favoriten |
|EchoFavoritesPlaylist |string |~Playlist |Favoriten (Playlist) |
|EchoTuneInRemote_&lt;Deviceserial&gt; |integer |Echo.TuneInStation.&lt;InstanceID&gt;|TuneIn Radio |
|Title |string |~Song |Titel |
|Subtitle_1 |string |~Artist |Interpret (Untertitel 1) |
|Subtitle_2 |string | |Album (Untertitel 2) |
|EchoActions |integer |Echo.Actions |Aktionen |
|EchoTTS |string | |Text zu Sprache |
|last_action |integer |~UnixTimestamp |Letzte Aktion |
|summary |string | |Letzter Befehl |
|DND |boolean |Echo.Remote.DND |Bitte nicht stören |
|nextAlarmTime |integer |~UnixTimestamp |nächster Alarm |
|lastAlarmTime |integer |~UnixTimestamp |letzter Alarm |
|Automation |integer |Echo.Automation |Automation |
|OnlineStatus |boolean |~Switch |Online Status (Offline-Status 10 Minuten verzögert)|

## PHP-Befehlsreferenz

### Announcement
Führt eine Ansage auf dem Echo-Gerät aus. 
* Annoucements müssen pro Gerät in der Alexa-App de-/aktiviert werden (Geräte > Echo und Alexa > Echo Gerät auswählen > Geräteeinstellungen (Zahnrad) > Kommunikation > Ankündigungen)
* Wenn *Do-not-Disturb* aktiviert ist, erfolgen auf dem jeweiligen Gerät keine Ansagen

```php
ECHOREMOTE_Announcement( int $InstanceID, string $tts );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |integer |ID der EchoRemote-Instanz |
|$tts |string |Ansagetext |

### AnnouncementEx
Führt eine Ansage auf mehreren Echo-Geräten aus.
* Annoucements müssen pro Gerät in der Alexa-App de-/aktiviert werden (Geräte > Echo und Alexa > Echo Gerät auswählen > Geräteeinstellungen (Zahnrad) > Kommunikation > Ankündigungen)
* Wenn *Do-not-Disturb* aktiviert ist, erfolgen auf dem jeweiligen Gerät keine Ansagen

```php
ECHOREMOTE_AnnouncementEx( int $InstanceID, string $tts, array $instanceIDList, array $options );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |integer |ID der EchoRemote-Instanz |
|$tts |string |Ansagetext |
|$instanceIDList |array |Array mit InstanzID's auf denen die Ankündigung erfolgen soll `[ 12345, 23456, 34567]`. Wird ein leeres Array übergeben, erfolgt keine Ansage. Wird `['ALL_DEVICES']` übergeben, erfolgt die Ansage auf allen im Account registirerten Echo-Geräten  |
|$options |array | Optionen (aktuell keine verfügbar) `[]` |

_Beispiel:_
```php
$InstanceID = 12345; // InstanzID des Echo Remote Devices
$instanceIDList = [12345, 23456, 34567];

ECHOREMOTE_AnnouncementEx( $InstanceID,  'Die Waschmaschine ist fertig', $instanceIDList, [] );

```

### AnnouncementToAll
Führt eine Ansage auf allen im Account registrierten Echo-Geräten aus.
* Annoucements müssen pro Gerät in der Alexa-App de-/aktiviert werden (Geräte > Echo und Alexa > Echo Gerät auswählen > Geräteeinstellungen (Zahnrad) > Kommunikation > Ankündigungen)
* Wenn *Do-not-Disturb* aktiviert ist, erfolgen auf dem jeweiligen Gerät keine Ansagen

```php
ECHOREMOTE_AnnouncementToAll( int $InstanceID, string $tts );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |integer |ID der EchoRemote-Instanz |
|$tts |string | |

### CalendarNext
```php
ECHOREMOTE_CalendarNext( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### CalendarToday
```php
ECHOREMOTE_CalendarToday( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### CalendarTomorrow
```php
ECHOREMOTE_CalendarTomorrow( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### CleanUp
```php
ECHOREMOTE_CleanUp( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### ConnectBluetooth
Es wird der Verbindungsaufbau zu dem angegeben Gerät initiiert.

```php
ECHOREMOTE_ConnectBluetooth( int $InstanceID, string $bluetooth_address );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$bluetooth_address |string |Adresse des zu verbindenden Gerätes  |

*Beispiel:*
```php
ECHOREMOTE_ConnectBluetooth(47111, '00:16:94:25:7B:93');
```


### CopyTuneInStationsToFavorites
```php
ECHOREMOTE_CopyTuneInStationsToFavorites( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### CustomCommand
```php
ECHOREMOTE_CustomCommand( int $InstanceID, string $url, array $postfields, string $method );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$url |string | |
|$postfields |string | |
|$method |string | |

### DecreaseVolume
```php
ECHOREMOTE_DecreaseVolume( int $InstanceID, int $increment );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$increment |int | |

### DisconnectBluetooth
Es wird eine bestehende Bluetooth Verbindung getrennt.

```php
ECHOREMOTE_DisconnectBluetooth( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### DisplayOff
```php
ECHOREMOTE_DisplayOff( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### DisplayOn
```php
ECHOREMOTE_DisplayOn( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### DoNotDisturb
```php
ECHOREMOTE_DoNotDisturb( int $InstanceID, bool $state );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$state |bool | |

### FlashBriefing
```php
ECHOREMOTE_FlashBriefing( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### Forward30s
```php
ECHOREMOTE_Forward30s( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### GetAllAutomations
```php
ECHOREMOTE_GetAllAutomations( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### GetDeviceSettings
```php
ECHOREMOTE_GetDeviceSettings( int $InstanceID, string $settingName );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$settingName |string | |

### GetDoNotDisturbState
```php
ECHOREMOTE_GetDoNotDisturbState( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### GetLastActivities
```php
ECHOREMOTE_GetLastActivities( int $InstanceID, int $count );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$count |int | |

### GetMediaState
```php
ECHOREMOTE_GetMediaState( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### GetMusicProviders
```php
ECHOREMOTE_GetMusicProviders( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### GetNotifications
Liefert eine Liste mit den aktuellen Weckern und Timern.

```php
ECHOREMOTE_GetNotifications( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### GetPlayerInformation
Liefert eine Liste mit Statuseinträgen des Players und abgespielten Medien.

```php
ECHOREMOTE_GetPlayerInformation( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### GetQueueInformation
Liefert eine Liste mit Informationen zum aktuell abgespielten Titel bzw. zum aktuellen Sender.  

```php
ECHOREMOTE_GetQueueInformation( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### GoodMorning
```php
ECHOREMOTE_GoodMorning( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### IncreaseVolume
```php
ECHOREMOTE_IncreaseVolume( int $InstanceID, int $increment );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$increment |int | |

### ListPairedBluetoothDevices
Es werden die für das Gerät angelegten Bluetooth Verbindungen ermittelt. Hinweis: die Bluetootheinrichtung selber hat mit der Amazon App oder im Dialog zu erfolgen.

```php
ECHOREMOTE_ListPairedBluetoothDevices( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

*Beispiel:*
```php
$devices = ECHOREMOTE_ListPairedBluetoothDevices(47111);

var_dump($devices);
```

Es wird eine Liste der eingerichteten Bluetooth Verbindungen und deren Eigenschaften ausgegeben:
```php
array(1) {
  [0]=>
  array(5) {
    ["address"]=>
    string(17) "00:16:94:25:7B:93"
    ["connected"]=>
    bool(false)
    ["deviceClass"]=>
    string(5) "OTHER"
    ["friendlyName"]=>
    string(7) "PXC 550"
    ["profiles"]=>
    array(2) {
      [0]=>
      string(9) "A2DP-SINK"
      [1]=>
      string(5) "AVRCP"
    }
  }
}
```

### Mute
```php
ECHOREMOTE_Mute( int $InstanceID, bool $mute );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$mute |bool | |

### Next
```php
ECHOREMOTE_Next( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### Pause
```php
ECHOREMOTE_Pause( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### Play
```php
ECHOREMOTE_Play( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### PlayMusic
Startet Musik von verschiedenen Musikanbietern.

```php
ECHOREMOTE_PlayMusic( int $InstanceID, string $searchPhrase, string $musicProviderId );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$searchPhrase |string |Suchanfrage z.B. "_Songname_ von _Interpret_", "_Albumname_ von _Interpret_",  "_Playlistname_", "_Radiosender-Name_" |
|$musicProviderId |string |Anbieter z.b. 'DEFAULT', 'TUNEIN', 'AMAZON_MUSIC', 'CLOUDPLAYER', 'SPOTIFY', 'APPLE_MUSIC', 'DEEZER', 'I_HEART_RADIO' |

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


### Previous
```php
ECHOREMOTE_Previous( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### RaiseAlarm
```php
ECHOREMOTE_RaiseAlarm( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### Repeat
```php
ECHOREMOTE_Repeat( int $InstanceID, bool $value );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$value |bool | |

### Rewind30s
```php
ECHOREMOTE_Rewind30s( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### SeekPosition
```php
ECHOREMOTE_SeekPosition( int $InstanceID, int $position );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$position |int | |

### SendMobilePush
Sendet eine Push-Nachricht an die Alexa-App.

```php
ECHOREMOTE_SendMobilePush( int $InstanceID, string $title, string $message );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$title |string |Titel |
|$message |string |Nachricht |

_Beispiele:_
```php
$InstanceID = 12345; // InstanzID des Echo Remote Devices

ECHOREMOTE_SendMobilePush( $InstanceID, 'IP-Symcon', 'Die Waschmaschine ist fertig');

```


### SetDeviceSettings
```php
ECHOREMOTE_SetDeviceSettings( int $InstanceID, string $settingName, string $value );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$settingName |string | |
|$value |string | |

### SetVolume
```php
ECHOREMOTE_SetVolume( int $InstanceID, int $volume );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$volume |int | |

### ShowAlarmClock
```php
ECHOREMOTE_ShowAlarmClock( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### Shuffle
```php
ECHOREMOTE_Shuffle( int $InstanceID, bool $value );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$value |bool | |

### SingASong
```php
ECHOREMOTE_SingASong( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### StartAlexaRoutine
Es wird die zum Sprachausdruck passende Routine gestartet. Im Fehlerfall wird false zurückgegeben.

```php
ECHOREMOTE_StartAlexaRoutine( int $InstanceID, string $utterance );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$utterance |string |'Sprachausdruck' der zu startenden Routine. Routinen können in der Alexa App definiert, 
konfiguriert und aktiviert werden. |

### StartAlexaRoutineByName
```php
ECHOREMOTE_StartAlexaRoutineByName( int $InstanceID, string $routine_name );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$routine_name |string | |

### Stop
```php
ECHOREMOTE_Stop( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### StopAll
```php
ECHOREMOTE_StopAll( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### TellFunFact
```php
ECHOREMOTE_TellFunFact( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### TellJoke
```php
ECHOREMOTE_TellJoke( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### TellStory
```php
ECHOREMOTE_TellStory( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### TextCommand
```php
ECHOREMOTE_TextCommand( int $InstanceID, string $command );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$command |string | |


### TextToSpeech
Führt eine Ansage auf dem Echo-Gerät durch.

```php
ECHOREMOTE_TextToSpeech( integer $InstanceID, string $tts );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |integer |ID der EchoRemote-Instanz |
|$tts |string |Ansagetext |

_Beispiel:_
```php
$InstanceID = 12345; // InstanzID des Echo Remote Devices

ECHOREMOTE_TextToSpeech( $InstanceID,  'Die Waschmaschine ist fertig');

```

### TextToSpeechEx
Führt eine Ansage auf mehreren Echo-Geräten durch. Es können erweiterte Optionen, wie die Lautstärke übergeben werden.

```php
ECHOREMOTE_TextToSpeechEx( integer $InstanceID, string $tts, array $instanceIDList, array $options );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |integer |ID der EchoRemote-Instanz |
|$tts |string |Ansagetext |
|$instanceIDList |array | Array mit InstanzID's auf denen die Ankündigung erfolgen soll `[ 12345, 23456, 34567]`.<br>Wird ein leeres Array `[]` übergeben, erfolgt keine Ansage.<br>Wird `['ALL_DEVICES']` übergeben, erfolgt die Ansage auf allen im Account registirerten Echo-Geräten  |
|$options |array |Optionen als Array default `[]` <br> Lautstärke während Ansage `['volume' => 35]` |

_Beispiel:_
```php
$InstanceID = 12345; // InstanzID des Echo Remote Devices
$instanceIDList = [12345, 23456, 34567];

ECHOREMOTE_TextToSpeechEx( $InstanceID,  'Die Waschmaschine ist fertig', $instanceIDList, [] );

```

### TextToSpeechToAll
Ansage auf allem im Account registrierten Echo-Geräten.

```php
ECHOREMOTE_TextToSpeechToAll( integer $InstanceID, string $tts );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |integer |ID der EchoRemote-Instanz |
|$tts |string |Ansagetext |

### TextToSpeechVolume
Wie TextToSpeech, jedoch kann die Lautstärke der Ansage übergeben werden. Nach der Ansage wird die Lautstärke wieder auf den vorherigen Wert zurückgesetzt.

```php
ECHOREMOTE_TextToSpeechVolume( integer $InstanceID, string $tts, integer $volume );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |integer |ID der EchoRemote-Instanz |
|$tts |string |Ansagetext |
|$volume |integer |Lautsärke der Ansage |

_Beispiel:_
```php
$InstanceID = 12345; // InstanzID des Echo Remote Devices

ECHOREMOTE_TextToSpeechVolume( $InstanceID,  'Die Waschmaschine ist fertig', 50 );

```
### Traffic
```php
ECHOREMOTE_Traffic( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### TuneIn
```php
ECHOREMOTE_TuneIn( int $InstanceID, string $guideId );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$guideId |string |Station ID ist die guideId die entsprechend der Anleitung pro Sender einmal ausgelesen werden muss |

### TuneInPreset
```php
ECHOREMOTE_TuneInPreset( int $InstanceID, int $preset );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$preset |int |Positions ID der Radiostation im Modul  |

### UpdateAlarm
```php
ECHOREMOTE_UpdateAlarm( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### UpdatePlayerStatus
```php
ECHOREMOTE_UpdatePlayerStatus( int $InstanceID, int $waitSeconds );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |
|$waitSeconds |int | |

### UpdateStatus
```php
ECHOREMOTE_UpdateStatus( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### VolumeDown
```php
ECHOREMOTE_VolumeDown( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### VolumeUp
```php
ECHOREMOTE_VolumeUp( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

### Weather
```php
ECHOREMOTE_Weather( int $InstanceID );
```
|Parameter| Typ| Beschreibung |
|-----| -----| ----- |
|$InstanceID |int |ID der EchoRemote-Instanz |

