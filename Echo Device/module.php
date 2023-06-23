<?php

//<editor-fold desc="declarations">
declare(strict_types=1);

require_once __DIR__ . '/../libs/EchoBufferHelper.php';
require_once __DIR__ . '/../libs/EchoDebugHelper.php';
//</editor-fold>

// Modul für Amazon Echo Remote

class EchoRemote extends IPSModule
{
    use EchoBufferHelper;
    use EchoDebugHelper;
    private const STATUS_INST_DEVICETYPE_IS_EMPTY = 210; // devicetype must not be empty.
    private const STATUS_INST_DEVICENUMBER_IS_EMPTY = 211; // devicenumber must not be empty

    const PREVIOUS = 0;
    const STOP = 1;
    const PLAY = 2;
    const PAUSE = 3;
    const NEXT = 4;

    private $customerID = '';
    private $ParentID = 0;
    private int $position = 0;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.

        $this->RegisterPropertyString('Devicetype', '');
        $this->RegisterPropertyString('Devicenumber', '');
        $this->RegisterPropertyString(
            'TuneInStations', '[{"position":1,"station":"Hit Radio FFH","station_id":"s17490"},
            {"position":2,"station":"FFH Lounge","station_id":"s84483"},
            {"position":3,"station":"FFH Rock","station_id":"s84489"},
            {"position":4,"station":"FFH Die 80er","station_id":"s84481"},
            {"position":5,"station":"FFH iTunes Top 40","station_id":"s84486"},
            {"position":6,"station":"FFH Eurodance","station_id":"s84487"},
            {"position":7,"station":"FFH Soundtrack","station_id":"s97088"},
            {"position":8,"station":"FFH Die 90er","station_id":"s97089"},
            {"position":9,"station":"FFH Schlagerkult","station_id":"s84482"},
            {"position":10,"station":"FFH Leider Geil","station_id":"s254526"},
            {"position":11,"station":"The Wave - relaxing radio","station_id":"s140647"},
            {"position":12,"station":"hr3","station_id":"s57109"},
            {"position":13,"station":"harmony.fm","station_id":"s140555"},
            {"position":14,"station":"SWR3","station_id":"s24896"},
            {"position":15,"station":"Deluxe Lounge Radio","station_id":"s125250"},
            {"position":16,"station":"Lounge-Radio.com","station_id":"s17364"},
            {"position":17,"station":"Bayern 3","station_id":"s255334"},
            {"position":18,"station":"planet radio","station_id":"s2726"},
            {"position":19,"station":"YOU FM","station_id":"s24878"},
            {"position":20,"station":"1LIVE diggi","station_id":"s45087"},
            {"position":21,"station":"Fritz vom rbb","station_id":"s25005"},
            {"position":22,"station":"Hitradio \u00d63","station_id":"s8007"},
            {"position":23,"station":"radio ffn","station_id":"s8954"},
            {"position":24,"station":"N-JOY","station_id":"s25531"},
            {"position":25,"station":"bigFM","station_id":"s84203"},
            {"position":26,"station":"Deutschlandfunk","station_id":"s42828"},
            {"position":27,"station":"NDR 2","station_id":"s17492"},
            {"position":28,"station":"DASDING","station_id":"s20295"},
            {"position":29,"station":"sunshine live","station_id":"s10637"},
            {"position":30,"station":"MDR JUMP","station_id":"s6634"},
            {"position":31,"station":"Costa Del Mar","station_id":"s187256"},
            {"position":32,"station":"Antenne Bayern","station_id":"s139505"},
            {"position":33,"station":"1 Live","station_id":"s25260"}]'
        );

        $this->RegisterPropertyString('FavoritesList', '[
            {"searchPhrase":"Deutschlandfunk", "musicProvider":"TUNEIN"},
            {"searchPhrase":"Mein Discovery Mix", "musicProvider":"CLOUDPLAYER"},
            {"searchPhrase":"Pop", "musicProvider":"AMAZON_MUSIC"},
            {"searchPhrase":"Rock", "musicProvider":"DEFAULT"}
        ]');

        //        $this->RegisterPropertyString('TuneInStations', '');
        $this->RegisterPropertyInteger('updateinterval', 60);
        $this->RegisterPropertyBoolean('PlayerControl', true);
        $this->RegisterPropertyBoolean('ExtendedInfo', true);
        $this->RegisterPropertyBoolean('DND', false);
        $this->RegisterPropertyBoolean('AlarmInfo', false);
        $this->RegisterPropertyBoolean('ShoppingList', false);
        $this->RegisterPropertyBoolean('TaskList', false);
        $this->RegisterPropertyBoolean('Mute', true);
        $this->RegisterPropertyBoolean('Title', false);
        $this->RegisterPropertyBoolean('Cover', false);
        $this->RegisterPropertyBoolean('Subtitle1', false);
        $this->RegisterPropertyBoolean('Subtitle2', false);
        $this->RegisterPropertyInteger('TitleColor', 0);
        $this->RegisterPropertyInteger('TitleSize', 0);
        $this->RegisterPropertyInteger('Subtitle1Color', 0);
        $this->RegisterPropertyInteger('Subtitle1Size', 0);
        $this->RegisterPropertyInteger('Subtitle2Color', 0);
        $this->RegisterPropertyInteger('Subtitle2Size', 0);
        $this->RegisterPropertyBoolean('OnlineStatus', false);
        $this->RegisterPropertyBoolean('EchoFavorites', true);
        $this->RegisterPropertyBoolean('EchoTuneInRemote', true);
        $this->RegisterPropertyBoolean('LastAction', true);
        $this->RegisterPropertyBoolean('EchoTTS', true);
        $this->RegisterPropertyBoolean('EchoActions', true);
        

        $this->SetBuffer('CoverURL', '');
        $this->SetBuffer('Volume', '');
        $this->RegisterTimer('EchoUpdate', 0, 'EchoRemote_UpdateStatus(' . $this->InstanceID . ');');
        $this->RegisterTimer('UpdatePlayerStatus', 0, 'EchoRemote_UpdatePlayerStatus(' . $this->InstanceID . ', 0);');
        $this->RegisterTimer('EchoAlarm', 0, 'EchoRemote_RaiseAlarm(' . $this->InstanceID . ');');
        $this->RegisterAttributeFloat('LastDeviceTimestamp', 0);
        $this->RegisterAttributeString('LastActivityID', '' ); 
        $this->RegisterAttributeString('routines', '[]');
        $this->RegisterPropertyBoolean('routines_wf', false);
        $this->RegisterAttributeString('DeviceInfo', '');
        $this->RegisterAttributeString('MusicProviders', '');

        $this->ConnectParent('{C7F853A4-60D2-99CD-A198-2C9025E2E312}');

        //we will wait until the kernel is ready
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        if (!$this->ValidateConfiguration()) {
            return;
        }

        $this->RegisterParent();

        $this->RegisterVariables();
        //Apply filter
        $devicenumber = $this->ReadPropertyString('Devicenumber');
        $this->SetReceiveDataFilter('.*' . $devicenumber . '.*');
    }


    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case IM_CHANGESTATUS:
                if ($Data[0] === IS_ACTIVE) {
                    $this->ApplyChanges();
                }
                break;

            case IPS_KERNELMESSAGE:
                if ($Data[0] === KR_READY) {
                    $this->ApplyChanges();
                }
                break;

            default:
                break;
        }
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        $this->SendDebug('Receive Data', $JSONString, 0);

        if ( $data->DeviceSerial != $this->ReadPropertyString('Devicenumber') )
            return;

        switch ($data->Type)
        {
            case 'Volume':
                if(@$this->GetIDForIdent('EchoVolume') > 0)
                {
                    $this->SetValue('EchoVolume', $data->Payload->EchoVolume);
                    // Save current volume for mute function
                    if ($data->Payload->EchoVolume > 0)
                        $this->SetBuffer('Volume', $data->Payload->EchoVolume);
                }
                if(@$this->GetIDForIdent('Mute') > 0)
                {
                    $this->SetValue('Mute', $data->Payload->Mute);
                }
                break;
            
            case 'LastAction':
                $payload = $data->Payload;

                if ($payload->id != $this->ReadAttributeString('LastActivityID')) 
                {
                    $this->WriteAttributeString('LastActivityID', $payload->id);

                    if(@$this->GetIDForIdent('last_action') > 0)
                    {
                        $this->SetValue('last_action', intval($payload->creationTimestamp) );
                    }
                    if(@$this->GetIDForIdent('summary') > 0)
                    {
                        $this->SetValue('summary', $payload->utterance );
                    }
                    $this->UpdatePlayerStatus(10);
                }
                break;
            case 'DeviceInfo':
                $this->WriteAttributeString('DeviceInfo', json_encode( $data->Payload) );
                if(@$this->GetIDForIdent('OnlineStatus') > 0)
                {
                    $this->SetValue('OnlineStatus', $data->Payload->online);
                }                
                break;
        }

    }

    public function RequestAction($Ident, $Value)
    {
        $devicenumber = $this->ReadPropertyString('Devicenumber');
        $this->SendDebug(__FUNCTION__, 'Ident:' . $Ident . ',Value:' . $Value, 0);
        if ($Ident === 'EchoRemote') {
            switch ($Value) {
                case self::PREVIOUS: // Previous
                    $this->Previous();
                    break;
                case self::STOP:
                case self::PAUSE: // Pause / Stop
                    $this->Pause();
                    break;
                case self::PLAY: // Play
                    $this->Play();
                    break;
                case self::NEXT: // Next
                    $this->Next();
                    break;
            }
        }
        if ($Ident === 'EchoShuffle') {
            $this->Shuffle($Value);
        }
        if ($Ident === 'EchoRepeat') {
            $this->Repeat($Value);
        }
        if ($Ident === 'EchoVolume') {
            $this->SetValue('EchoVolume', $Value); // To avoid flickering of the slider set volume variable now.
            $this->SetVolume($Value);
        }
        if ($Ident === 'EchoTuneInRemote_' . $devicenumber) {
            $this->SetValue($Ident, $Value);
            //$station = GetValueFormatted( $this->GetIDForIdent($Ident));
            //$this->PlayMusic( $station, 'TUNEIN');
            $stationid = $this->GetTuneInStationID($Value);
            $this->TuneIn($stationid);
        }
        if ($Ident === 'EchoFavorites') {
            $this->SetValue('EchoFavorites', $Value);
            $Value = json_decode($Value, true);
            if ($Value !== false)
            {
                $this->PlayMusic($Value['searchPhrase'], $Value['musicProvider']);
            }          
        }
        if ($Ident === 'EchoFavoritesPlaylist') {
            $this->SetValue('EchoFavoritesPlaylist', $Value);
            $Value = json_decode($Value, true);
            if ($Value !== false){
                $current = $Value['current'];
                $Value = $Value['entries'][$current ];
                $this->PlayMusic($Value['searchPhrase'], $Value['musicProvider']);
            }
                
        }
        if ($Ident === 'EchoActions') {
            switch ($Value) {
                case 0: // Weather
                    $this->SetValue('EchoActions', $Value);
                    $this->Weather();
                    break;
                case 1: // Traffic
                    $this->SetValue('EchoActions', $Value);
                    $this->Traffic();
                    break;
                case 2: // Flashbriefing
                    $this->SetValue('EchoActions', $Value);
                    $this->FlashBriefing();
                    break;
                case 3: // Good Morning
                    $this->SetValue('EchoActions', $Value);
                    $this->GoodMorning();
                    break;
                case 4: // Sing a song
                    $this->SetValue('EchoActions', $Value);
                    $this->SingASong();
                    break;
                case 5: // tell a story
                    $this->SetValue('EchoActions', $Value);
                    $this->TellStory();
                    break;
                case 6: // tell a joke
                    $this->SetValue('EchoActions', $Value);
                    $this->TellJoke();
                    break;
                case 7: // tell a funfact
                    $this->SetValue('EchoActions', $Value);
                    $this->TellFunFact();
                    break;
            }
        }
        if ($Ident === 'EchoTTS') {
            $this->TextToSpeech($Value);
        }
        if ($Ident === 'Mute') {
            $this->Mute($Value);
        }
        if ($Ident === 'DND') {
            if ($Value) {
                $this->DoNotDisturb(true);
            } else {
                $this->DoNotDisturb(false);
            }
        }
        if ($Ident === 'Automation') {
            $this->StartAlexaRoutineByKey($Value);
        }

    }

    /**
     * Die folgenden Funktionen stehen automatisch zur Verfügung, wenn das Modul über die "Module Control" eingefügt wurden.
     * Die Funktionen werden, mit dem selbst eingerichteten Prefix, in PHP und JSON-RPC wiefolgt zur Verfügung gestellt:.
     */
    public function RaiseAlarm(): void
    {
        //Alarmzeit setzen
        $oldAlarmTime = $this->GetValue('nextAlarmTime');
        $this->SetValue('lastAlarmTime', $oldAlarmTime);
        $this->SendDebug(__FUNCTION__, 'lastAlarmTime set to ' . $oldAlarmTime . ' (' . date(DATE_RSS, $oldAlarmTime) . ')', 0);

        //Nächsten Timer abrufen
        $this->UpdateAlarm();

        //alte Zeit wird nicht gelöscht, da Alexa den Wecker erst deaktiviert, wenn er abgelaufen ist
    }

    /** Rewind 30s
     *
     * @return array|string
     */
    public function Rewind30s()
    {
        $result = $this->NpCommand('RewindCommand');
        if ($result['http_code'] === 200) {
            return true;
        }
        return false;
    }

    /** Forward 30s
     *
     * @return array|string
     */
    public function Forward30s()
    {
        $result = $this->NpCommand('ForwardCommand');
        if ($result['http_code'] === 200) {
            return true;
        }
        return false;
    }

    /** Previous
     *
     * @return array|string
     */
    public function Previous()
    {
        $result = $this->NpCommand('PreviousCommand');
        if ($result['http_code'] === 200) {
            $this->UpdatePlayerStatus(5);
            //$this->SetValue('EchoRemote', self::PREVIOUS);
            return true;
        }
        return false;
    }

    /** Pause
     *
     * @return array|string
     */
    public function Pause()
    {
        $result = $this->NpCommand('PauseCommand');
        if ($result['http_code'] === 200) {
            $this->SetValue('EchoRemote', self::PAUSE);
            $this->UpdatePlayerStatus(5);
            return true;
        }
        return false;
    }

    /** Play
     *
     * @return array|string
     */
    public function Play()
    {
        $result = $this->NpCommand('PlayCommand');
        if ($result['http_code'] === 200) {
            $this->SetValue('EchoRemote', self::PLAY);
            $this->UpdatePlayerStatus(5);
            return true;
        }
        return false;
    }

    /** Next
     *
     * @return array|string
     */
    public function Next()
    {
        $result = $this->NpCommand('NextCommand');
        if ($result['http_code'] === 200) {
            $this->UpdatePlayerStatus(5);
            //$this->SetValue('EchoRemote', self::NEXT);
            return true;
        }
        return false;
    }

    /**
     * Stop music
     */
    public function Stop(): bool
    {

        $operationPayload = [
            'customerId'    => $this->GetCustomerID(),
            'devices' => [
                [
                'deviceSerialNumber'  => $this->GetDevicenumber(),
                'deviceType'          => $this->GetDevicetype()
                ]
            ],
            'isAssociatedDevice' => false         
        ];

        $payload  = [
            'type'              => 'Alexa.DeviceControls.Stop',
            'skillId'           => 'amzn1.ask.1p.alexadevicecontrols',
            'operationPayload'  => $operationPayload
        ];

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        return $result['http_code'] === 200;
    }

    /** Stop music on all devices
     *
     * @return bool
     */
    public function StopAll(): bool
    {

        $operationPayload = [
            'customerId'    => $this->GetCustomerID(),
            'devices' => [
                [
                'deviceSerialNumber'  => 'ALEXA_ALL_DSN',
                'deviceType'          => 'ALEXA_ALL_DEVICE_TYPE'
                ]
            ],
            'isAssociatedDevice' => false         
        ];

        $payload  = [
            'type'              => 'Alexa.DeviceControls.Stop',
            'skillId'           => 'amzn1.ask.1p.alexadevicecontrols',
            'operationPayload'  => $operationPayload
        ];

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        return $result['http_code'] === 200;
    }

    /** VolumeUp
     *
     * @return array|string
     */
    public function VolumeUp()
    {
        return $this->SetVolume((int) $this->GetValue('EchoVolume') + 1);
    }

    /** VolumeDown
     *
     * @return array|string
     */
    public function VolumeDown()
    {
        return $this->SetVolume((int) $this->GetValue('EchoVolume') - 1);
    }

    /** IncreaseVolume
     *
     * @param int $increment
     *
     * @return array|string
     */
    public function IncreaseVolume(int $increment)
    {
        return $this->SetVolume((int) $this->GetValue('EchoVolume') + $increment);
    }

    /** DecreaseVolume
     *
     * @param int $increment
     *
     * @return array|string
     */
    public function DecreaseVolume(int $increment)
    {
        return $this->SetVolume($this->GetValue('EchoVolume') - $increment);
    }

    /** SetVolume
     *
     * @param int $volume
     *
     * @return array|string
     */
    public function SetVolume(int $volume) // integer 0 bis 100
    {
        if ($volume > 100) {
            $volume = 100;
        }
        if ($volume < 0) {
            $volume = 0;
        }

        $result = $this->NpCommand('VolumeLevelCommand', [ 'volumeLevel' => $volume]);

        
        if ($result['http_code'] === 200) 
        {
            $result = true;
        }
        elseif( $result['http_code'] == 404 ||  $result['http_code'] == 400)
        {
            // Try SequenceComand in case of unsuccessful request
            $result = $this->SetVolumeSequenceCmd( $volume );
        }

        $this->UpdatePlayerStatus(5);

        if ($result) {
            $this->SetValue('EchoVolume', $volume);
            if ($volume > 0)
            {
                // save current volume for mute function
                $this->SetBuffer('Volume', $volume);

                if (@$this->GetValue('Mute'))
                    $this->SetValue('Mute', false);
            }
            return true;
        }

        return false;
    }

    private function SetVolumeSequenceCmd( int $volume )
    {
        $operationPayload = [
            'customerId'            => $this->GetCustomerID(),
            'deviceSerialNumber'    => $this->GetDevicenumber(),
            'deviceType'            => $this->GetDevicetype(),
            'value'                 => $volume,
            'locale'                => 'ALEXA_CURRENT_LOCALE'
        ];

        $payload  = [
            'type'              => 'Alexa.DeviceControls.Volume',
            'skillId'           => 'amzn1.ask.1p.alexadevicecontrols',
            'operationPayload'  => $operationPayload
        ];

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        return $result['http_code'] === 200;

    }

    /** Mute / unmute
     *
     * @param bool $mute
     *
     * @return array|string
     */
    public function Mute(bool $mute): bool
    {
        $volume = 0;

        $this->SetValue('Mute', $mute);

        $last_volume = $this->GetBuffer('Volume');
        // if volume buffer is empty, try to get volume from variable
        if ($last_volume === '') {
            $last_volume = $this->GetValue('EchoVolume');
            if ($last_volume > 0)
                $this->SetBuffer('Volume', $last_volume);
            else
                $this->SetBuffer('Volume', '30');
        }

        if (!$mute) 
        {
            $volume = (int) $last_volume;
        }

        return $this->SetVolume($volume);
    }

    /** Get Player Status Information
     *
     * @return array|string
     */
    public function GetPlayerInformation()
    {
        $device = [
            'deviceSerialNumber' => $this->GetDevicenumber(),
            'deviceType'         => $this->GetDevicetype(),
            'screenWidth'        => 1680 //to get url of big picture
        ];

        $payload['url'] = '/api/np/player?' . http_build_query($device);

        $result = $this->SendDataPacket('SendEcho', $payload);
        if(!empty($result))
        {
            if ($result['http_code'] === 200) {
                //$this->SetValue("EchoVolume", $volume);
                return json_decode($result['body'], true);
            }
        }
        return false;
    }

    /** Get Player Status Information
     *
     * @return array|string
     */
    public function GetQueueInformation()
    {
        $device = [
            'deviceSerialNumber' => $this->GetDevicenumber(),
            'deviceType'         => $this->GetDevicetype()
        ];

        $payload['url'] = '/api/np/queue?' . http_build_query($device);

        $result = $this->SendDataPacket('SendEcho', $payload);
        if ($result['http_code'] === 200) {
            //$this->SetValue("EchoVolume", $volume);
            return json_decode($result['body'], true);
        }
        return false;
    }

    public function SetDeviceSettings( string $settingName, string $value)
    {
        $deviceAccountId = json_decode( $this->ReadAttributeString('DeviceInfo'), true)['deviceAccountId'];

        $payload['url']     = '/api/v1/devices/' . $deviceAccountId .'/settings/'. $settingName;
        $payload['method']  = 'PUT'; 
        $payload['postfields'] = [
            'value' => $value
        ];

        return $this->SendDataPacket( 'SendEcho', $payload);
    }

    public function GetDeviceSettings( string $settingName)
    {
        $deviceAccountId = json_decode( $this->ReadAttributeString('DeviceInfo'), true)['deviceAccountId'];

        $payload['url']     = '/api/v1/devices/' . $deviceAccountId .'/settings/'. $settingName;
        $payload['method']  = 'GET'; 

        return $this->SendDataPacket( 'SendEcho', $payload);
    }

    /** Shuffle
     *
     * @param bool $value
     *
     * @return array|string
     */
    public function Shuffle(bool $value)
    {

        $command= [
            'shuffle' => $value ? 'true' : 'false'
        ];

        $result = $this->NpCommand('ShuffleCommand', $command);
        if ($result['http_code'] === 200) {
            $this->SetValue('EchoShuffle', $value);
            return true;
        }
        return false;
    }

    /** Repeat
     *
     * @param bool $value
     *
     * @return array|string
     */
    public function Repeat(bool $value)
    {

        $command= [
            'repeat' => $value ? 'true' : 'false'
        ];

        $result = $this->NpCommand('RepeatCommand', $command);
        if ($result['http_code'] === 200) {
            $this->SetValue('EchoRepeat', $value);
            return true;
        }
        return false;
    }

    public function SeekPosition(int $position)
    {
        $command= [
            'mediaPosition' => $position
        ];

        $result = $this->NpCommand('SeekCommand', $command);
        if ($result['http_code'] === 200) {
            return true;
        }
        return false;
    }

    /** play TuneIn radio station
     *
     * @param string $guideId
     *
     * @return bool
     */
    public function TuneIn(string $guideId)
    {
        $getfields = [
            'deviceSerialNumber' => $this->GetDevicenumber(),
            'deviceType'         => $this->GetDevicetype()
        ];

        $postfields = [
            'contentToken'          => 'music:' . base64_encode(base64_encode('["music/tuneIn/stationId","' . $guideId .'"]|{"previousPageId":"TuneIn_SEARCH"}'))
        ];

        $payload['url'] = '/api/entertainment/v1/player/queue?' . http_build_query($getfields);
        $payload['postfields'] = $postfields;
        $payload['method'] = 'PUT';

        $result = $this->SendDataPacket('SendEcho', $payload);

        $presetPosition = $this->GetTuneInStationPresetPosition($guideId);
        if ($presetPosition) {
            $this->SetValue('EchoTuneInRemote_' . $this->ReadPropertyString('Devicenumber'), $presetPosition);
        }
        if ($result['http_code'] === 200) {
            $this->UpdatePlayerStatus(10);
            return true;
        }
        return false;
    }

    /** GetMediaState
     *
     * @return mixed
     */
    public function GetMediaState()
    {
        $device = [
            'deviceSerialNumber' => $this->GetDevicenumber(),
            'deviceType'         => $this->GetDevicetype()
        ];

        $payload['url'] = '/api/media/state?' . http_build_query($device);

        $result = $this->SendDataPacket('SendEcho', $payload);

        //$url = 'https://{AlexaURL}/api/media/state?deviceSerialNumber=' . $this->GetDevicenumber() . '&deviceType=' . $this->GetDevicetype()
        //       . '&queueId=0e7d86f5-d5a4-4a3a-933e-5910c15d9d4f&shuffling=false&firstIndex=1&lastIndex=1&screenWidth=1920&_=1495289082979';

        if (isset($result['http_code']) && ($result['http_code'] === 200)) {
            return json_decode($result['body'], true);
        }

        return false;
    }

    /** GetNoticications
     *
     * @return mixed
     */
    public function GetNotifications(): ?array
    {
        $payload['url'] = '/api/notifications?';

        $result = $this->SendDataPacket('SendEcho', $payload);

        if (isset($result['http_code']) && ($result['http_code'] === 200)) {
            return json_decode($result['body'], true)['notifications'];
        }

        return null;
    }

    /** GetToDos
     *
     * @param string $type      : one of 'SHOPPING_ITEM' or 'TASK'
     * @param bool   $completed true: completed todos are returned
     *                          false: not completed todos are returned
     *                          null: all todos are returned
     *
     * @return array|null
     */
    public function GetToDos(string $type, bool $completed = null): ?array
    {
        $getfields = [
            'type' => $type, //SHOPPING_ITEM or TASK,
            'size' => 500
        ];

        if ($completed !== null) {
            $getfields['completed'] = $completed ? 'true' : 'false';
        }

        $payload['url'] =  '/api/todos?' . http_build_query($getfields);

        $result = $this->SendDataPacket('SendEcho', $payload);

        if (isset($result['http_code']) && ($result['http_code'] === 200)) {
            return json_decode($result['body'], true)['values'];
        }

        return null;
    }

    /** Play TuneIn station by present
     *
     * @param int $preset
     *
     * @return bool
     */
    public function TuneInPreset(int $preset): ?bool
    {
        $station = $this->GetTuneInStationID($preset);
        if ($station !== '') {
            return $this->TuneIn($station);
        }

        trigger_error('unknown preset: ' . $preset);
        return false;
    }

    /** TextToSpeech
     *
     * @param string $tts
     *
     * @return array|string
     */
    public function TextToSpeech(string $tts): bool
    {
        return $this->TextToSpeechEx( $tts, [ $this->InstanceID ], [] );
    }

    /** TextToSpeechVolume
     *
     * @param string $tts
     * @param int $volume
     *
     * @return array|string
     */
    public function TextToSpeechVolume(string $tts, int $volume): bool
    {
        return $this->TextToSpeechEx( $tts, [ $this->InstanceID ], ['volume' => $volume] );
    }

    /** TextToSpeech to all devices
     *
     * @param string $tts
     *
     * @return array|string
     */
    public function TextToSpeechToAll(string $tts): bool
    {
        return $this->TextToSpeechEx( $tts, ['ALL_DEVICES'], [] );
    }

    /** TextToSpeechEx
     *
     * @param string $tts
     * @param string $instanceIDList
     * @return array|string
     */
    public function TextToSpeechEx(string $tts, array $instanceIDList = [], array $options = [] ): bool
    {
        if ( $instanceIDList == array())
        {
            return false;
        }
        elseif ( in_array('ALL_DEVICES', $instanceIDList) )
        {
            $targetDevices = 'ALL_DEVICES';
        } 
        else
        {
            // Remove duplicates
            $instanceIDList = array_unique($instanceIDList);

            foreach($instanceIDList as $instanceID )
            {
                if (IPS_GetInstance($instanceID)['ConnectionID'] === IPS_GetInstance($this->InstanceID)['ConnectionID']) 
                {
                    $device = array();

                    $device = [
                        'deviceSerialNumber'    => IPS_GetProperty( $instanceID, 'Devicenumber'),
                        'deviceType'          => IPS_GetProperty( $instanceID, 'Devicetype')                 
                    ];               

                    $targetDevices[] = $device;
                }
            }
        }

        $operationPayload = [
            'customerId'    => $this->GetCustomerID(),
            'locale'        => 'ALEXA_CURRENT_LOCALE',         
            'textToSpeak'   => $tts
        ];

        $payload  = [
            'type'              => 'Alexa.Speak',
            'skillId'           => 'amzn1.ask.1p.saysomething',
            'operationPayload'  => $operationPayload,
            'devices'           => $targetDevices,
        ];

        if (isset($options['volume']) )
        {
            $payload['volume'] = $options['volume'];
        }

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        return $result['http_code'] === 200;
    }

    /** Send a text Command to an echo device
     * @param string $command
     * @return bool
     */
    public function TextCommand(string $command): bool
    {
        $operationPayload = [
            'customerId' => $this->GetCustomerID(),
            'deviceSerialNumber' => $this->GetDevicenumber(),
            'deviceType' => $this->GetDevicetype(),
            'locale'        => 'ALEXA_CURRENT_LOCALE', 
            'text' => $command
        ];

        $payload  = [
            'type'              => 'Alexa.TextCommand',
            'skillId'           => 'amzn1.ask.1p.tellalexa',
            'operationPayload'  => $operationPayload
        ];

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        return $result['http_code'] === 200;
    }

    /** Announcement
     *
     * @param string $tts
     *
     * @return array|string
     */
    public function Announcement(string $tts): bool
    {
        return $this->AnnouncementEx( $tts, [ $this->InstanceID ] , [] );
    }

    /** AnnouncementToAll
     *
     * @param string $tts
     *
     * @return array|string
     */
    public function AnnouncementToAll(string $tts): bool
    {
        return $this->AnnouncementEx( $tts, ['ALL_DEVICES'] , [] );
    }

    /** AnnouncementEx
     *
     * @param string $tts
     * @param string $instanceIDList
     * @param string $options
     * @return array|string
     */
    public function AnnouncementEx(string $tts, array $instanceIDList = [] , array $options = [] ): bool
    {

        $customerID = $this->GetCustomerID();

        $tts = '<speak>'.$tts.'</speak>';

        if ( $instanceIDList == array())
        {
            return false;
        }
        elseif ( in_array('ALL_DEVICES', $instanceIDList) )
        {
            $targetDevices = [];
        }
        else
        {
            // Remove duplicates
            $instanceIDList = array_unique($instanceIDList);         
               
            foreach($instanceIDList as $instanceID )
            {
                if (IPS_GetInstance($instanceID)['ConnectionID'] === IPS_GetInstance($this->InstanceID)['ConnectionID']) 
                {
                    $targetDevices[] = [
                        'deviceSerialNumber'    => IPS_GetProperty( $instanceID, 'Devicenumber'),
                        'deviceTypeId'          => IPS_GetProperty( $instanceID, 'Devicetype')                 
                    ];
                }
            }
        }


        $operationPayload = [
            'expireAfter' => 'PT5S',
            'content' => [
                0 => [
                    'display' => [
                        'title' => $this->Translate('Message from Symcon'),
                        'body' => $tts
                    ],
                    'speak' => [
                        'type' => 'ssml',
                        'value' => $tts
                    ],
                    'locale' => 'ALEXA_CURRENT_LOCALE'
                ]
            ],
            'skillId' => 'amzn1.ask.1p.routines.messaging',
            'locale' => 'ALEXA_CURRENT_LOCALE',
            'customerId' => $customerID,
            'locale'        => 'ALEXA_CURRENT_LOCALE', 
            'target' => [
                'customerId' => $customerID,
                'devices' => $targetDevices,
                'locale' =>  'ALEXA_CURRENT_LOCALE'
            ]
        ];

        // Announcements to all devices do not have the devices attribute
        if ($targetDevices == [])
        {
            unset($operationPayload['target']['devices']);
        }

        $payload  = [
            'type'              => 'AlexaAnnouncement',
            'skillId'           => 'amzn1.ask.1p.routines.messaging',
            'operationPayload'  => $operationPayload
        ];

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        return $result['http_code'] === 200;        
    }
    

    /** Send a Mobile Push Notification to Alexa App
     * @param string $title
     * @param string $message
     * @return bool
     */
    public function SendMobilePush(string $title , string $message ): bool
    {
        if ($title == "") $title = "Symcon";

        $operationPayload = [
            'title'                 => $title,
            'notificationMessage'   => $message,
            'alexaUrl'              => '#v2/behaviors',
            'customerId'            => $this->GetCustomerID()
        ];

        $payload  = [
            'type'              => 'Alexa.Notifications.SendMobilePush',
            'skillId'           => 'amzn1.ask.1p.routines.messaging',
            'operationPayload'  => $operationPayload
        ];

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        return $result['http_code'] === 200;    
    }

    /** PlayMusic
     * @param string $searchPhrase
     * @param string $musicProviderId ( DEFAULT, TUNEIN, AMAZON_MUSIC, CLOUDPLAYER, SPOTIFY, APPLE_MUSIC, DEEZER, I_HEART_RADIO )
     */
    public function PlayMusic(string $searchPhrase, string $musicProviderId = ""): bool
    {

        if ($musicProviderId == "") 
        {
            $musicProviderId = 'DEFAULT';
        }

        $operationPayload = [
            'deviceSerialNumber'    => $this->GetDevicenumber(),
            'deviceType'            => $this->GetDevicetype(),   
            'customerId'            => $this->GetCustomerID(),
            'locale'                => 'ALEXA_CURRENT_LOCALE',            
            'searchPhrase'          => $searchPhrase,
            'sanitizedSearchPhrase' => $this->sanitizeSearchPhrase( $searchPhrase ),
            'musicProviderId'       => $musicProviderId
        ];

        $payload  = [
            'type'              => 'Alexa.Music.PlaySearchPhrase',
            'skillId'           => 'amzn1.ask.1p.music',
            'operationPayload'  => $operationPayload
        ];

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        $this->UpdatePlayerStatus(5);

        return $result['http_code'] === 200; 
    }

    private function sanitizeSearchPhrase(  $searchPhrase )
    {

        $operationPayload = [
            'searchPhrase' => $searchPhrase
        ];

        $postfields = [
            'type' => 'Alexa.Music.PlaySearchPhrase',
            'operationPayload' => json_encode($operationPayload )
        ];

        $payload['url'] = '/api/behaviors/operation/validate';
        $payload['postfields'] = $postfields;


        $result = (array) $this->SendDataPacket('SendEcho', $payload);

        if ( $result['http_code'] === 200)
        {
            $body = json_decode($result['body'], true);

            if (isset($body['result']) && $body['result'] == 'VALID')
            {
                if ( isset($body['operationPayload']['sanitizedSearchPhrase'] ))
                {
                    return $body['operationPayload']['sanitizedSearchPhrase'];
                }
            }
        }

        return $searchPhrase;

    }

    /**
     * Weather Forcast.
     */
    public function Weather(): bool
    {
        $operationPayload = [
            'customerId'         => $this->GetCustomerID(),
            'deviceSerialNumber' => $this->GetDevicenumber(),
            'deviceType'         => $this->GetDevicetype(),
            'locale'             => 'ALEXA_CURRENT_LOCALE'
        ];

        $payload  = [
            'type'              => 'Alexa.Weather.Play',
            'skillId'           => 'amzn1.ask.1p.weather',
            'operationPayload'  => $operationPayload
        ];

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        return $result['http_code'] === 200;
    }

    /**
     * Traffic.
     */
    public function Traffic(): bool
    {
        $operationPayload = [
            'customerId'         => $this->GetCustomerID(),
            'deviceSerialNumber' => $this->GetDevicenumber(),
            'deviceType'         => $this->GetDevicetype(),
            'locale'             => 'ALEXA_CURRENT_LOCALE'
        ];

        $payload  = [
            'type'              => 'Alexa.Traffic.Play',
            'skillId'           => 'amzn1.ask.1p.traffic',
            'operationPayload'  => $operationPayload
        ];

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        return $result['http_code'] === 200;
    }

    /**
     * Flash briefing.
     */
    public function FlashBriefing(): bool
    {
        $operationPayload = [
            'customerId'         => $this->GetCustomerID(),
            'deviceSerialNumber' => $this->GetDevicenumber(),
            'deviceType'         => $this->GetDevicetype(),
            'locale'             => 'ALEXA_CURRENT_LOCALE'
        ];

        $payload  = [
            'type'              => 'Alexa.FlashBriefing.Play',
            'skillId'           => 'amzn1.ask.1p.flashbriefing',
            'operationPayload'  => $operationPayload
        ];

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        return $result['http_code'] === 200;
    }

    /**
     * Goodmorning.
     */
    public function GoodMorning(): bool
    {
        $operationPayload = [
            'customerId'         => $this->GetCustomerID(),
            'deviceSerialNumber' => $this->GetDevicenumber(),
            'deviceType'         => $this->GetDevicetype(),
            'locale'             => 'ALEXA_CURRENT_LOCALE'
        ];

        $payload  = [
            'type'              => 'Alexa.GoodMorning.Play',
            'skillId'           => 'amzn1.ask.1p.saysomething',
            'operationPayload'  => $operationPayload
        ];

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        return $result['http_code'] === 200;
    }

    /**
     * Sing a song.
     */
    public function SingASong(): bool
    {
        $operationPayload = [
            'customerId'         => $this->GetCustomerID(),
            'deviceSerialNumber' => $this->GetDevicenumber(),
            'deviceType'         => $this->GetDevicetype(),
            'locale'             => 'ALEXA_CURRENT_LOCALE'
        ];

        $payload  = [
            'type'              => 'Alexa.SingASong.Play',
            'skillId'           => 'amzn1.ask.1p.saysomething',
            'operationPayload'  => $operationPayload
        ];

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        return $result['http_code'] === 200;
    }

    /**
     * Tell a story.
     */
    public function TellStory(): bool
    {
        $operationPayload = [
            'customerId'         => $this->GetCustomerID(),
            'deviceSerialNumber' => $this->GetDevicenumber(),
            'deviceType'         => $this->GetDevicetype(),
            'locale'             => 'ALEXA_CURRENT_LOCALE'
        ];

        $payload  = [
            'type'              => 'Alexa.TellStory.Play',
            'skillId'           => 'amzn1.ask.1p.saysomething',
            'operationPayload'  => $operationPayload
        ];

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        return $result['http_code'] === 200;
    }

    /**
     * Tell a funfact.
     */
    public function TellFunFact(): bool
    {
        $operationPayload = [
            'customerId'         => $this->GetCustomerID(),
            'deviceSerialNumber' => $this->GetDevicenumber(),
            'deviceType'         => $this->GetDevicetype(),
            'locale'             => 'ALEXA_CURRENT_LOCALE'
        ];

        $payload  = [
            'type'              => 'Alexa.FunFact.Play',
            'skillId'           => 'amzn1.ask.1p.saysomething',
            'operationPayload'  => $operationPayload
        ];

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        return $result['http_code'] === 200;
    }

    /**
     * Tell a joke.
     */
    public function TellJoke(): bool
    {
        $operationPayload = [
            'customerId'         => $this->GetCustomerID(),
            'deviceSerialNumber' => $this->GetDevicenumber(),
            'deviceType'         => $this->GetDevicetype(),
            'locale'             => 'ALEXA_CURRENT_LOCALE'
        ];

        $payload  = [
            'type'              => 'Alexa.Joke.Play',
            'skillId'           => 'amzn1.ask.1p.saysomething',
            'operationPayload'  => $operationPayload
        ];

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        return $result['http_code'] === 200;
    }


    /**
     * Clean Up need Amazon Music Unlimited.
     */
    public function CleanUp(): bool
    {
        $operationPayload = [
            'customerId'         => $this->GetCustomerID(),
            'deviceSerialNumber' => $this->GetDevicenumber(),
            'deviceType'         => $this->GetDevicetype(),
            'locale'             => 'ALEXA_CURRENT_LOCALE'
        ];

        $payload  = [
            'type'              => 'Alexa.CleanUp.Play',
            'operationPayload'  => $operationPayload
        ];

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        return $result['http_code'] === 200;
    }

    /**
     * Calendar Today.
     */
    public function CalendarToday(): bool
    {
        $operationPayload = [
            'customerId'         => $this->GetCustomerID(),
            'deviceSerialNumber' => $this->GetDevicenumber(),
            'deviceType'         => $this->GetDevicetype(),
            'locale'             => 'ALEXA_CURRENT_LOCALE'
        ];

        $payload  = [
            'type'              => 'Alexa.Calendar.PlayToday',
            'skillId'           => 'amzn1.ask.1p.calendar',
            'operationPayload'  => $operationPayload
        ];

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        return $result['http_code'] === 200;
    }

    /**
     * Calendar Tomorrow.
     */
    public function CalendarTomorrow(): bool
    {
        $operationPayload = [
            'customerId'         => $this->GetCustomerID(),
            'deviceSerialNumber' => $this->GetDevicenumber(),
            'deviceType'         => $this->GetDevicetype(),
            'locale'             => 'ALEXA_CURRENT_LOCALE'
        ];

        $payload  = [
            'type'              => 'Alexa.Calendar.PlayTomorrow',
            'skillId'           => 'amzn1.ask.1p.calendar',
            'operationPayload'  => $operationPayload
        ];

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        return $result['http_code'] === 200;
    }

    /**
     * Calendar Next.
     */
    public function CalendarNext(): bool
    {
        $operationPayload = [
            'customerId'         => $this->GetCustomerID(),
            'deviceSerialNumber' => $this->GetDevicenumber(),
            'deviceType'         => $this->GetDevicetype(),
            'locale'             => 'ALEXA_CURRENT_LOCALE'
        ];

        $payload  = [
            'type'              => 'Alexa.Calendar.PlayNext',
            'skillId'           => 'amzn1.ask.1p.calendar',
            'operationPayload'  => $operationPayload
        ];

        $result =  $this->SendDataPacket( 'BehaviorsPreview', $payload );

        return $result['http_code'] === 200;
    }

    /** Get state do not disturb
     * @return array|mixed|null
     */
    public function GetDoNotDisturbState()
    {

        $result = $this->SendDataPacket('GetDNDState');
        $deviceSerialNumber = $this->ReadPropertyString('Devicenumber');
        if ($result['http_code'] == 200) {
            $doNotDisturbDeviceStatusList = json_decode($result['body'], true);
            $dnd_devices = $doNotDisturbDeviceStatusList['doNotDisturbDeviceStatusList'];
            foreach($dnd_devices as $dnd_device)
            {
                if($deviceSerialNumber == $dnd_device['deviceSerialNumber']){
                    $dnd = $dnd_device['enabled'];

                    if(@$this->GetIDForIdent('DND') > 0)
                    {
                        if ($this->GetValue('DND') != $dnd )
                        {
                            $this->SetValue('DND', $dnd);
                        }
                    }
                }
            }
            return $result['body'];
        }
        return $result;
    }

    public function GetMusicProviders()
    {
        $payload['url'] = '/api/behaviors/entities?skillId=amzn1.ask.1p.music';

        $result = $this->SendDataPacket('SendEcho', $payload);

        $list = array( 'DEFAULT' => 'Default');

        if ($result['http_code'] === 200) {
            $providers = json_decode($result['body'], true);
            foreach($providers as $provider)
            {
                if ($provider['id'] != 'DEFAULT')
                    $list[ $provider['id'] ] = $provider['displayName'];
            }
            $this->WriteAttributeString('MusicProviders', json_encode($list) );
        }

        return $list;
    }

    private function GetMusicProviersFormField()
    {
        $providers =  $this->ReadAttributeString('MusicProviders');

        if ($providers == '')
        {
            $providers = $this->GetMusicProviders();
        }
        else
        {
            $providers = json_decode($providers, true);
        }

        $formField = array();
        foreach( $providers as $id => $name)
        {
            $formField[] = [
                'caption' => $name,
                'value' => $id
            ];
        }
        return $formField;
    }

    private function GetMusicProviderName( $id )
    {
        $providers =  json_decode( $this->ReadAttributeString('MusicProviders'), true);
        if (isset( $providers[$id] ))
        {
            return $providers[$id];
        }
        return $id;
    }

    /** Get all automations
     *
     * @return array
     */
    public function GetAllAutomations()
    {
        $payload['url'] = '/api/behaviors/v2/automations';

        $result = (array) $this->SendDataPacket('SendEcho', $payload);

        if ($result['http_code'] !== 200) {
            return [];
        }
        return json_decode($result['body'], true);
    }

    private function GetAutomationsList()
    {
        $automations = $this->GetAllAutomations();
        $list = [];
        if(!empty($automations))
        {
            foreach ($automations as $key => $automation) {

                $routine_id = $key;
                $automationId = $automation['automationId'];
                $routine_name = $automation['name'];
                $routine_utterance = '';
                if(isset($automation['triggers'][0]['payload']['utterance']))
                {
                    $routine_utterance = $automation['triggers'][0]['payload']['utterance'];
                }
                if(is_null($routine_name))
                {
                    $routine_name = '';
                }

                $list[] = [
                    'routine_id'        => $routine_id,
                    'automationId'      => $automationId,
                    'routine_name'      => $routine_name,
                    'routine_utterance' => $routine_utterance,
                ];
            }
        }
        return $list;
    }

    /** Echo Show Display off
     *
     * @return bool
     */
    public function DisplayOff(): bool
    {      
        return $this->TextCommand( $this->Translate('display off') );
    }

    /** Echo Show Display on
     *
     * @return bool
     */
    public function DisplayOn(): bool
    {
        return $this->TextCommand( $this->Translate('display on') );
    }

    /** Show Alarm Clock
     *
     * @return bool
     */
    public function ShowAlarmClock(): bool
    {
        return $this->TextCommand( $this->Translate('show alarm clock') );
    }

    /** Set do not disturb
     * @param bool $state
     * @return bool
     */
    public function DoNotDisturb(bool $state): bool
    {
        $postfields = [
            'deviceSerialNumber' => $this->GetDevicenumber(),
            'deviceType'         => $this->GetDevicetype(),
            'enabled'               => $state
        ];

        $payload['url'] = '/api/dnd/status';
        $payload['postfields'] = $postfields;
        $payload['method'] = 'PUT';

        $result = (array) $this->SendDataPacket('SendEcho', $payload);
        IPS_Sleep(200);
        $this->GetDoNotDisturbState();

        return $result['http_code'] === 200;
    }

    /** Start Alexa Routine by utterance
     * @param string $utterance
     *
     * @return bool
     */
    public function StartAlexaRoutine(string $utterance): bool
    {
        $automations = $this->GetAllAutomations();
        if(!empty($automations))
        {
            //search Automation of utterance
            $automation = $this->GetAutomation($utterance, $automations);
            if ($automation) {
                //play automation
                $payload = [
                    'device'             => [
                        'deviceSerialNumber' => $this->GetDevicenumber(),
                        'deviceType'         => $this->GetDevicetype(),
                    ],
                    'automation'         => $automation
                ];

                $result = (array) $this->SendDataPacket('BehaviorsPreviewAutomation', $payload);
                return $result['http_code'] === 200;
            }
        }
        return false;
    }

    /** Start Alexa routine by routine name
     * @param int $routine_key
     *
     * @return bool
     */
    private function StartAlexaRoutineByKey(int $routine_key): bool
    {
        $automations = $this->GetAllAutomations();
        if(!empty($automations))
        {
            //search Automation of utterance
            $automation = $this->GetAutomationByKey($routine_key, $automations);
            if ($automation) {
                //play automation
                $payload = [
                    'device'             => [
                        'deviceSerialNumber' => $this->GetDevicenumber(),
                        'deviceType'         => $this->GetDevicetype(),
                    ],
                    'automation'         => $automation
                ];

                $result = (array) $this->SendDataPacket('BehaviorsPreviewAutomation', $payload);
                return $result['http_code'] === 200;
            }
        }
        return false;
    }

    /** Start Alexa routine by routine name
     * @param string $routine_name
     *
     * @return bool
     */
    public function StartAlexaRoutineByName(string $routine_name): bool
    {
        $automations = $this->GetAllAutomations();
        if(!empty($automations))
        {
            //search Automation of utterance
            $automation = $this->GetAutomationByName($routine_name, $automations);
            if ($automation) {
                //play automation
                $payload = [
                    'device'             => [
                        'deviceSerialNumber' => $this->GetDevicenumber(),
                        'deviceType'         => $this->GetDevicetype(),
                    ],
                    'automation'         => $automation
                ];

                $result = (array) $this->SendDataPacket('BehaviorsPreviewAutomation', $payload);
                return $result['http_code'] === 200;
            }
        }
        return false;
    }

    /** List paired bluetooth devices
     *
     * @return array|null
     */
    public function ListPairedBluetoothDevices(): ?array
    {
        $devicenumber = $this->ReadPropertyString('Devicenumber');
        $devices = $this->GetBluetoothDevices();
        if ($devices) {
            foreach ($devices as $key => $device) {
                if ($devicenumber === $device['deviceSerialNumber']) {
                    return $device['pairedDeviceList'];
                }
            }
        }

        return null;
    }

    /** List all echo devices with connected Bluetooth devices
     *
     * @return mixed
     */
    private function GetBluetoothDevices()
    {
        $payload['url'] = '/api/bluetooth?cached=false';

        $result = (array) $this->SendDataPacket('SendEcho', $payload);

        if ($result['http_code'] === 200) {
            $data = json_decode($result['body'], true);
            return $data['bluetoothStates'];
        }

        return false;
    }

    public function ConnectBluetooth(string $bluetooth_address): bool
    {

        $postfields = [
            'bluetoothDeviceAddress' => $bluetooth_address
        ];
        
        $payload['url'] ='/api/bluetooth/pair-sink/' . $this->GetDevicetype() . '/' . $this->GetDevicenumber();
        $payload['postfields'] = $postfields;

        $result = (array) $this->SendDataPacket('EchoSend', $payload);
        return $result['http_code'] === 200;
    }

    public function DisconnectBluetooth(): bool
    {
        $payload['url'] = '/api/bluetooth/disconnect-sink/' . $this->GetDevicetype(). '/' . $this->GetDevicenumber();

        $result = (array) $this->SendDataPacket('EchoSend', $payload);

        return $result['http_code'] === 200;
    }

    public function GetLastActivities(int $count)
    {
        $getfields = [
            'size'      => $count,
            'startTime' => '',
            'offset'    => 1
        ];

        $payload['url'] = '/api/activities?' . http_build_query($getfields);

        $result = (array) $this->SendDataPacket('SendEcho', $payload);

        if ($result['http_code'] === 200) {
            return json_decode($result['body'], true);
        }

        return false;
    }

    /** Get State Tune In
     *
     * @return bool
     */
    public function UpdateStatus(): bool
    {    

        if ( !IPS_SemaphoreEnter ( 'UpdateStatus.'.$this->InstanceID , 1000) )
        {
            return false;
        } 

        $this->UpdatePlayerStatus();

        // Update do-not-disturb-state
        if ($this->ReadPropertyBoolean('DND')) {
            $this->GetDoNotDisturbState();
        }

        //update Alarm
        if ($this->ReadPropertyBoolean('AlarmInfo')) {
            $this->UpdateAlarm();
        }

        //update ShoppingList
        if ($this->ReadPropertyBoolean('ShoppingList')) {
            $shoppingList = (array) $this->GetToDos('SHOPPING_ITEM', false);
            if ($shoppingList === false) {
                return false;
            }

            $html = $this->GetListPage($shoppingList);
            //neuen Wert setzen.
            if ($html !== $this->GetValue('ShoppingList')) {
                $this->SetValue('ShoppingList', $html);
            }
        }

        //update TaskList
        if ($this->ReadPropertyBoolean('TaskList')) {
            $taskList = (array) $this->GetToDos('TASK', false);
            if ($taskList === false) {
                return false;
            }

            $html = $this->GetListPage($taskList);
            //neuen Wert setzen.
            if ($html !== $this->GetValue('TaskList')) {
                $this->SetValue('TaskList', $html);
            }
        }

        IPS_SemaphoreLeave ( 'UpdateStatus.'.$this->InstanceID );

        return true;
    }

    public function UpdatePlayerStatus(int $waitSeconds = 0)
    {
        if ($waitSeconds > 0)
        {
            $this->SetTimerInterval('UpdatePlayerStatus', $waitSeconds * 1000);
            return;
        } 
        else
        {
            $this->SetTimerInterval('UpdatePlayerStatus', 0);
        }

        // Update player information
        $result = $this->GetPlayerInformation();

        if ($result !== false) {
            
            $playerInfo = $result['playerInfo'];
            $this->SendDebug('Playerinfo', json_encode($playerInfo), 0);

            // Set timer based on media length and current progress
            if ( isset($playerInfo['progress']['mediaLength']) && isset($playerInfo['progress']['mediaProgress']) )
            {
                $remaining = $playerInfo['progress']['mediaLength'] - $playerInfo['progress']['mediaProgress'];
                if ( $remaining > 0)
                {
                    $this->SetTimerInterval('UpdatePlayerStatus', ($remaining + 5) * 1000);
                }
            }

            if ($this->CheckExistence('EchoRemote') )
            {
                switch ($playerInfo['state']) {
                    case 'PLAYING':
                        $this->SetValue('EchoRemote', self::PLAY);
                        break;
        
                    case null:
                    case 'PAUSED':
                    case 'IDLE':
                        $this->SetValue('EchoRemote', self::PAUSE);
                        break;
        
                    default:
                        trigger_error('Instanz #' . $this->InstanceID . ' - Unexpected state: ' . $playerInfo['state']);
                }
            }
    
            $imageurl = $playerInfo['mainArt']['url'] ?? null;
            $infotext = $playerInfo['infoText'];
            if (is_null($infotext)) {
                $this->SetStatePage('', '', '', '');
            } else {
                $this->SetStatePage(
                    $imageurl, $playerInfo['infoText']['title'], $playerInfo['infoText']['subText1'], $playerInfo['infoText']['subText2']
                );
            }
    
            if ($this->CheckExistence('EchoRepeat') && isset($playerInfo['transport']['repeat'])) {
                switch ($playerInfo['transport']['repeat']) {
                    case null:
                        break;
                    case 'HIDDEN':
                    case 'ENABLED':
                    case 'DISABLED':
                        $this->SetValue('EchoRepeat', false);
                        break;
    
                    case 'SELECTED':
                        $this->SetValue('EchoRepeat', true);
                        break;
    
                    default:
                        trigger_error('Instanz #' . $this->InstanceID . ' - Unexpected repeat value: ' . $playerInfo['transport']['repeat']);
                }
            }
    
            if ($this->CheckExistence('EchoShuffle') && isset($playerInfo['transport']['shuffle'])) {
                switch ($playerInfo['transport']['shuffle']) {
                    case null:
                        break;
                    case 'HIDDEN':
                    case 'ENABLED':
                    case 'DISABLED':
                        $this->SetValue('EchoShuffle', false);
                        break;
    
                    case 'SELECTED':
                        $this->SetValue('EchoShuffle', true);
                        break;
    
                    default:
                        trigger_error('Instanz #' . $this->InstanceID . ' - Unexpected shuffle value: ' . $playerInfo['transport']['shuffle']);
                }
            }

            if ($this->CheckExistence('EchoVolume') && isset($playerInfo['volume']['volume']) && isset($playerInfo['volume']['muted']) ) {

                if ( $playerInfo['volume']['volume'] > 0 && $playerInfo['volume']['muted'] == false ) // Grouped speakers send always volume=0 and muted=false, therefore do not overwrite volume
                {
                    $this->SetValue('EchoVolume', $playerInfo['volume']['volume']);
                }
                elseif ( $playerInfo['volume']['muted'] == true )
                {
                    $this->SetValue('EchoVolume', 0);
                }                
            }
        }
    }


    public function CustomCommand(string $url, array $postfields = [], string $method = '')
    {
        $search = [
            '{DeviceSerialNumber}',
            '{DeviceType}',
            '{MediaOwnerCustomerID}',
            urlencode('{DeviceSerialNumber}'),
            urlencode('{DeviceSerialNumber}'),
            urlencode('{MediaOwnerCustomerID}')];

        $replace = [
            $this->GetDevicenumber(),
            $this->GetDevicetype(),
            $this->GetCustomerID(),
            $this->GetDevicenumber(),
            $this->GetDevicetype(),
            $this->GetCustomerID()];

        $url = str_replace($search, $replace, $url);

        if ($postfields != [] ) {
            $postJson = json_encode($postfields, JSON_UNESCAPED_SLASHES);
            $postJson = str_replace($search, $replace, $postJson);
            $postfields = json_decode($postJson, true);
        }
        $payload['url'] = $url;
        $payload['postfields'] = $postfields;
        $payload['method'] = $method;

        return $this->SendDataPacket('CustomCommand', $payload);
    }


    public function GetConfigurationForm(): string
    {
        return json_encode(
            [
                'elements' => $this->FormElements(),
                'actions'  => $this->FormActions(),
                'status'   => $this->FormStatus()]
        );
    }


    /**
     * Ermittelt den Parent und verwaltet die Einträge des Parent im MessageSink
     * Ermöglicht es das Statusänderungen des Parent empfangen werden können.
     *
     * @return int ID des Parent.
     */
    protected function RegisterParent(): int
    {
        $OldParentId = $this->ParentID;
        $ParentId = @IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($ParentId !== $OldParentId) {
            if ($OldParentId > 0) {
                $this->UnregisterMessage($OldParentId, IM_CHANGESTATUS);
            }
            if ($ParentId > 0) {
                $this->RegisterMessage($ParentId, IM_CHANGESTATUS);
            } else {
                $ParentId = 0;
            }
            $this->ParentID = $ParentId;
        }
        return $ParentId;
    }

    private function ValidateConfiguration(): bool
    {
        $this->SetTimerInterval('EchoAlarm', 0);
        if ($this->ReadPropertyString('Devicetype') === '') {
            $this->SetStatus(self::STATUS_INST_DEVICETYPE_IS_EMPTY);
        } elseif ($this->ReadPropertyString('Devicenumber') === '') {
            $this->SetStatus(self::STATUS_INST_DEVICENUMBER_IS_EMPTY);
        } else {
            $this->SetStatus(IS_ACTIVE);
            $this->SetEchoInterval();

            return true;
        }

        return false;
    }

    /**
     * return incremented position
     * @return int
     */
    private function _getPosition()
    {
        $this->position++;
        return $this->position;
    }

    private function RegisterVariables(): void
    {
        if (!$this->HasActiveParent()) {
            return;
        }

        $device_info = $this->GetDeviceInfo();

        if (!$device_info) {
            return;
        }

        $caps = $device_info['capabilities'];

        //Remote Variable
        $playerControl =  $this->ReadPropertyBoolean('PlayerControl');

        $keep = $playerControl && in_array('AMAZON_MUSIC', $caps, true);
        $this->RegisterProfileAssociation(
            'Echo.Remote', 'Remote', '', '', 0, 5, 0, 0, VARIABLETYPE_INTEGER, [
                [self::PREVIOUS, $this->Translate('Previous'), 'HollowLargeArrowLeft', -1],
                [self::PAUSE, $this->Translate('Pause/Stop'), 'Sleep', -1],
                [self::PLAY, $this->Translate('Play'), 'Script', -1],
                [self::NEXT, $this->Translate('Next'), 'HollowLargeArrowRight', -1]
            ]
        );
        
        if ( IPS_VariableProfileExists('~PlaybackPreviousNext') )
        {
            $profile = '~PlaybackPreviousNext';
        }
        else
        {
            $profile = 'Echo.Remote';
        }

        $this->MaintainVariable('EchoRemote', $this->Translate('Remote'), 1, $profile, $this->_getPosition(), $keep );
        @$this->MaintainAction('EchoRemote', $keep);

        //Shuffle Variable
        $keep = $playerControl && in_array('AMAZON_MUSIC', $caps, true);
        if ( IPS_VariableProfileExists('~Shuffle') )
        {
            $profile = '~Shuffle';
        }
        else
        {
            $profile = '~Switch';
        }

        $this->MaintainVariable('EchoShuffle', $this->Translate('Shuffle'), 0, $profile, $this->_getPosition(), $keep);
        if ($keep) {
            $exist_shuffle = $this->CheckExistence('EchoShuffle');
            $this->SetIcon('EchoShuffle', 'Shuffle', $exist_shuffle);
            $this->EnableAction('EchoShuffle');
        } 

        //Repeat Variable
        $keep = $playerControl && in_array('AMAZON_MUSIC', $caps, true);
        $this->MaintainVariable('EchoRepeat', $this->Translate('Repeat'), 0, '~Switch', $this->_getPosition(), $keep);
        if ($keep) {
            $exist_repeat = $this->CheckExistence('EchoRepeat');
            $this->SetIcon('EchoRepeat', 'Repeat', $exist_repeat);
            $this->EnableAction('EchoRepeat');
        }

        //Volume Variable
        $keep = $playerControl && in_array('AMAZON_MUSIC', $caps, true);
        if ( IPS_VariableProfileExists('~Volume') )
        {
            $profile = '~Volume';
        }
        else
        {
            $profile = '~Intensity.100';
        }

        $this->MaintainVariable('EchoVolume', $this->Translate('Volume'), 1, $profile, $this->_getPosition(), $keep);
        if ($keep) {
            $this->EnableAction('EchoVolume');
        }

        //Mute
        $keep = $this->ReadPropertyBoolean('Mute') && in_array('AMAZON_MUSIC', $caps, true);

        $this->RegisterProfileAssociation(
            'Echo.Mute', 'Speaker', '', '', 0, 1, 0, 0, VARIABLETYPE_BOOLEAN, [
                [false, $this->Translate('Off'), '', -1],
                [true, $this->Translate('On'), '', 0x00ff00]]
        ); 

        if ( IPS_VariableProfileExists('~Mute') )
        {
            $profile = '~Mute';
        }
        else
        {
            $profile = 'Echo.Mute';
        }

        $this->MaintainVariable('Mute', $this->Translate('Mute'), 0, $profile, $this->_getPosition(), $keep);
        @$this->EnableAction('Mute');

        //Info Variable
        $keep = $playerControl && in_array('AMAZON_MUSIC', $caps, true);
        $this->MaintainVariable('EchoInfo', $this->Translate('Info'), 3, '~HTMLBox', $this->_getPosition(), $keep);

        // Favorites
        $keep = $this->ReadPropertyBoolean('EchoFavorites');
        $profileName = '';
        if ($keep)
        {
            $associations = [];

            foreach (json_decode($this->ReadPropertyString('FavoritesList'), true) as $favorite) {
                $value = [
                    'musicProvider' => $favorite['musicProvider'],
                    'searchPhrase' => $favorite['searchPhrase'],
                ];
                $name = $favorite['searchPhrase'] . ' ('. $this->GetMusicProviderName( $favorite['musicProvider'] ) .')';
                $associations[] = [json_encode($value), $name, '', -1];
            }
            $profileName = 'Echo.Favorites.' . $this->InstanceID;
            $this->RegisterProfileAssociation($profileName, 'Database', '', '', 0, 0, 0, 0, VARIABLETYPE_STRING, $associations);
        }
        $this->MaintainVariable('EchoFavorites', $this->Translate('Favorites'), 3, $profileName, $this->_getPosition(), $keep );
        @$this->MaintainAction('EchoFavorites', $keep);

        // Setup Favorites as Playlist for new Visualization
        if ( IPS_VariableProfileExists('~Playlist') )
        {
            $this->MaintainVariable('EchoFavoritesPlaylist', $this->Translate('Favorites (Playlist)'), 3, '~Playlist', $this->_getPosition() -1 , $keep );
            @$this->MaintainAction('EchoFavoritesPlaylist', $keep);

            if ($keep)
            {
                $playlistEntries = [];

                foreach (json_decode($this->ReadPropertyString('FavoritesList'), true) as $favorite) {
                    $playlistEntries[] = [
                        'artist' => $this->GetMusicProviderName( $favorite['musicProvider'] ) ,
                        'song' => $favorite['searchPhrase'],
                        'musicProvider' => $favorite['musicProvider'],
                        'searchPhrase' => $favorite['searchPhrase'],
                    ];
                } 
                $playlist = [
                    'current' => -1,
                    'entries' => $playlistEntries
                ];
                $this->SetValue('EchoFavoritesPlaylist', json_encode($playlist));
            }
        } 

        //TuneIn Variable
        $keep = $this->ReadPropertyBoolean('EchoTuneInRemote') && in_array('TUNE_IN', $caps, true);
        $profileName = '';
        $devicenumber = $this->ReadPropertyString('Devicenumber');
        if ($keep) {
            if ($devicenumber !== '') {
                $associations = [];
                foreach (json_decode($this->ReadPropertyString('TuneInStations'), true) as $tuneInStation) {
                    $associations[] = [$tuneInStation['position'], $tuneInStation['station'], '', -1];
                }
                $profileName = 'Echo.TuneInStation.' . $devicenumber;
                $this->RegisterProfileAssociation($profileName, 'Music', '', '', 0, 0, 0, 0, VARIABLETYPE_INTEGER, $associations);
            } 
        }
        $this->MaintainVariable('EchoTuneInRemote_' . $devicenumber, 'TuneIn Radio', 1, $profileName, $this->_getPosition(), $keep );
        @$this->MaintainAction('EchoTuneInRemote_' . $devicenumber, $keep);
        

        //Extended Info
        $keep = $this->ReadPropertyBoolean('ExtendedInfo') && in_array('AMAZON_MUSIC', $caps, true);
        if ( IPS_VariableProfileExists('~Song') ) $profile = '~Song'; else $profile = '';
        $this->MaintainVariable('Title', $this->Translate('Title'), 3, $profile, $this->_getPosition(),$keep );

        if ( IPS_VariableProfileExists('~Artist') ) $profile = '~Artist'; else $profile = '';
        $this->MaintainVariable('Subtitle_1', $this->Translate('Artist (Subtitle 1)'), 3, $profile, $this->_getPosition(), $keep);

        $this->MaintainVariable('Subtitle_2', $this->Translate('Album (Subtitle 2)'), 3, '', $this->_getPosition(), $keep);
        if ($keep) {
            $this->CreateMediaImage('MediaImageCover', 'Cover',  $this->_getPosition());
            $this->RefreshCover( $this->GetBuffer('CoverURL') );
        } else {
            $this->_getPosition();
            $this->DeleteMediaImage('MediaImageCover');
        }

        //Actions
        $this->RegisterProfileAssociation(
            'Echo.Actions', 'Move', '', '', 0, 5, 0, 0, VARIABLETYPE_INTEGER, [
                [0, $this->Translate('Weather'), '', -1],
                [1, $this->Translate('Traffic'), '', -1],
                [2, $this->Translate('Flash Briefing'), '', -1],
                [3, $this->Translate('Good morning'), '', -1],
                [4, $this->Translate('Sing a song'), '', -1],
                [5, $this->Translate('Tell a story'), '', -1],
                [6, $this->Translate('Tell a joke'), '', -1],
                [7, $this->Translate('Tell a funfact'), '', -1]
            ]
        );

        $keep = in_array('FLASH_BRIEFING', $caps, true) && $this->ReadPropertyBoolean('EchoActions');
        $this->MaintainVariable('EchoActions', $this->Translate('Actions'), 1, 'Echo.Actions', $this->_getPosition(), $keep);
        @$this->EnableAction('EchoActions');

        // Text to Speech
        $keep = in_array('FLASH_BRIEFING', $caps, true) && $this->ReadPropertyBoolean('EchoTTS');
        $this->MaintainVariable('EchoTTS', $this->Translate('Text to Speech'), 3, '', $this->_getPosition(), $keep);
        @$this->EnableAction('EchoTTS');

        // Do not disturb
        $keep = $this->ReadPropertyBoolean('DND');
        $this->RegisterProfileAssociation(
            'Echo.Remote.DND', 'Speaker', '', '', 0, 1, 0, 0, VARIABLETYPE_BOOLEAN, [
                [false, $this->Translate('Do not disturb off'), 'Speaker', 0x00ff55],
                [true, $this->Translate('Do not disturb'), 'Speaker', 0xff3300]]
        );
        $this->MaintainVariable('DND', $this->Translate('Do not disturb'), 0, 'Echo.Remote.DND', $this->_getPosition(), $keep );
        @$this->MaintainAction('DND', $keep);

        //support of alarm
        $this->MaintainVariable('nextAlarmTime', $this->Translate('next Alarm'), 1, '~UnixTimestamp', $this->_getPosition(), $this->ReadPropertyBoolean('AlarmInfo'));
        $this->MaintainVariable('lastAlarmTime', $this->Translate('last Alarm'), 1, '~UnixTimestamp', $this->_getPosition(), $this->ReadPropertyBoolean('AlarmInfo'));

        //support of ShoppingList
        $this->MaintainVariable('ShoppingList', $this->Translate('ShoppingList'), 3, '~HTMLBox', $this->_getPosition(), $this->ReadPropertyBoolean('ShoppingList'));

        //support of TaskList
        $this->MaintainVariable('TaskList', $this->Translate('TaskList'), 3, '~HTMLBox', $this->_getPosition(), $this->ReadPropertyBoolean('TaskList'));

        // Cover as HTML image
        $this->MaintainVariable('Cover_HTML', $this->Translate('Cover'), 3, '~HTMLBox', $this->_getPosition(), $this->ReadPropertyBoolean('Cover'));

        // Title as HTML
        $this->MaintainVariable('Title_HTML', $this->Translate('Title'), 3, '~HTMLBox', $this->_getPosition(), $this->ReadPropertyBoolean('Title'));

        // Subtitle 1 as HTML
        $this->MaintainVariable('Subtitle_1_HTML', $this->Translate('Subtitle 1'), 3, '~HTMLBox', $this->_getPosition(), $this->ReadPropertyBoolean('Subtitle1'));

        // Subtitle 2 as HTML
        $this->MaintainVariable('Subtitle_2_HTML', $this->Translate('Subtitle 2'), 3, '~HTMLBox', $this->_getPosition(), $this->ReadPropertyBoolean('Subtitle2'));

        if ($this->ReadPropertyBoolean('routines_wf')) {
            $automations = $this->GetAllAutomations();
            // automation variable
            $associations = [];
            $max = count($automations);
            foreach ($automations as $key => $automation) {
                $routine_id = $key;
                // $automationId = $automation['automationId'];
                $routine_name = $automation['name'];
                $routine_utterance = '';
                if(isset($automation['triggers'][0]['payload']['utterance']))
                {
                    $routine_utterance = $automation['triggers'][0]['payload']['utterance'];
                }
                else{
                    $routine_utterance = 'no utterance';
                }
                if(is_null($routine_name))
                {
                    $routine_name = '';
                }
                $association_name = $routine_name;
                if($routine_name == '')
                {
                    $association_name = $routine_utterance;
                }
                $associations[] = [$routine_id, $association_name, '', -1];
            }
            $this->RegisterProfileAssociation('Echo.Remote.Automation', 'Execute', '', '', 0, $max, 0, 0, VARIABLETYPE_INTEGER, $associations);
            $this->RegisterVariableInteger('Automation', 'Automation', 'Echo.Remote.Automation', $this->_getPosition());
            $this->EnableAction('Automation');
        }

        $keep = in_array('FLASH_BRIEFING', $caps, true) && $this->ReadPropertyBoolean('LastAction');
        $this->MaintainVariable('last_action', $this->Translate('Last Action'), 1, '~UnixTimestamp', $this->_getPosition(), $keep);
        $this->MaintainVariable('summary', $this->Translate('Last Command'), 3, '', $this->_getPosition(), $keep);

        $this->MaintainVariable('OnlineStatus', $this->Translate('Online status'), 0, '~Switch', $this->_getPosition(), $this->ReadPropertyBoolean('OnlineStatus'));

    }

    private function CheckExistence($ident)
    {
        $objectid = @$this->GetIDForIdent($ident);
        if ($objectid == false) {
            $exist = false;
        } else {
            $exist = true;
        }
        return $exist;
    }

    private function SetIcon($ident, $icon, $exist)
    {
        $icon_exist = false;
        if ($exist == false) {
            $icon_exist = IPS_SetIcon($this->GetIDForIdent($ident), $icon);
        }

        return $icon_exist;
    }

    private function GetDeviceInfo()
    {
        
        $deviceInfo = $this->ReadAttributeString('DeviceInfo');

        if ($deviceInfo == '')
        {
            $deviceInfo = $this->RequestDeviceInfo();
            if ($deviceInfo !== false)
            {
                $this->WriteAttributeString('DeviceInfo', json_encode($deviceInfo));
                return $deviceInfo;
            }
        }

        return json_decode($deviceInfo, true);

    }

    private function RequestDeviceInfo()
    {

        //fetch all devices
        $result = $this->SendDataPacket('GetDevices');

        if ($result['http_code'] !== 200) {
            return false;
        }

        $devices_arr = json_decode($result['body'], true);

        //search device with my type and serial number
        foreach ($devices_arr as $key => $device) {
            if (($device['deviceType'] === $this->GetDevicetype()) && ($device['serialNumber'] === $this->GetDevicenumber())) {
                return $device;
                break;
            }
        }

        return false;
    }

    private function SendDataPacket( string $type, array $payload = [])
    {
        $Data['DataID']     = '{8E187D67-F330-2B1D-8C6E-B37896D7AE3E}';
        $Data['Type']       = $type;
        $Data['Payload']    = $payload;

        $this->SendDebug( __FUNCTION__, json_encode($Data) , 0);

        if (!$this->HasActiveParent())
        {
            $this->SendDebug(__FUNCTION__, 'No active parent', 0);
            return ['http_code' => 502, 'header' => '', 'body' => 'No active parent'];
        }

        $ResultJSON = $this->SendDataToParent(json_encode($Data));
        if ($ResultJSON) {
            $this->SendDebug(__FUNCTION__.' Result', $ResultJSON, 0);

            $ret = json_decode($ResultJSON, true);
            if ($ret) {
                return $ret; //returns an array of http_code, body and header
            }
        }

        $this->SendDebug( __FUNCTION__.' Result', json_encode($ResultJSON), 0);

        return ['http_code' => 502, 'header' => '', 'body' => ''];        
    }

    /** register profiles
     *
     *
     * @param $Name
     * @param $Icon
     * @param $Prefix
     * @param $Suffix
     * @param $MinValue
     * @param $MaxValue
     * @param $StepSize
     * @param $Digits
     * @param $Vartype
     */
    private function RegisterProfile($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits, $Vartype): void
    {
        if (IPS_VariableProfileExists($Name)) {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] !== $Vartype) {
                $this->SendDebug('Profile', 'Variable profile type does not match for profile ' . $Name, 0);
            }
        } else {
            IPS_CreateVariableProfile($Name, $Vartype); // 0 boolean, 1 int, 2 float, 3 string
            $this->SendDebug('Variablenprofil angelegt', $Name, 0);
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        if ($Vartype == VARIABLETYPE_FLOAT)
            IPS_SetVariableProfileDigits($Name, $Digits); //  Nachkommastellen
        if ($Vartype == VARIABLETYPE_FLOAT || $Vartype == VARIABLETYPE_INTEGER)
            IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize); // string $ProfilName, float $Minimalwert, float $Maximalwert, float $Schrittweite
        $this->SendDebug(
            'Variablenprofil konfiguriert',
            'Name: ' . $Name . ', Icon: ' . $Icon . ', Prefix: ' . $Prefix . ', $Suffix: ' . $Suffix . ', Digits: ' . $Digits . ', MinValue: '
            . $MinValue . ', MaxValue: ' . $MaxValue . ', StepSize: ' . $StepSize, 0
        );
    }

    /** register profile association
     *
     * @param $Name
     * @param $Icon
     * @param $Prefix
     * @param $Suffix
     * @param $MinValue
     * @param $MaxValue
     * @param $Stepsize
     * @param $Digits
     * @param $Vartype
     * @param $Associations
     */
    private function RegisterProfileAssociation($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $Stepsize, $Digits, $Vartype,
                                                $Associations): void
    {
        if (is_array($Associations) && count($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        }
        $this->RegisterProfile($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $Stepsize, $Digits, $Vartype);

        if (is_array($Associations)) {
            //zunächst werden alte Assoziationen gelöscht
            //bool IPS_SetVariableProfileAssociation ( string $ProfilName, float $Wert, string $Name, string $Icon, integer $Farbe )
            if ($Vartype !== 0) { // 0 boolean, 1 int, 2 float, 3 string
                foreach (IPS_GetVariableProfile($Name)['Associations'] as $Association) {
                    IPS_SetVariableProfileAssociation($Name, $Association['Value'], '', '', -1);
                }
            }

            //dann werden die aktuellen eingetragen
            foreach ($Associations as $Association) {
                IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
            }
        } else {
            $Associations = $this->$Associations;
            foreach ($Associations as $code => $association) {
                IPS_SetVariableProfileAssociation($Name, $code, $this->Translate($association), $Icon, -1);
            }
        }
    }

    private function SetEchoInterval(): void
    {
        $echointerval = $this->ReadPropertyInteger('updateinterval');
        $interval = $echointerval * 1000;
        $this->SetTimerInterval('EchoUpdate', $interval);
    }


    private function CreateMediaImage(string $ident, string $name, int $position): void
    {
        $ImageFile = IPS_GetKernelDir() . 'media' . DIRECTORY_SEPARATOR . 'cover.' . $this->InstanceID. '.png';  // Image-Datei

        $MediaID = @$this->GetIDForIdent($ident);
        if ($MediaID === false) {
            $MediaID = IPS_CreateMedia(1);                  // Image im MedienPool anlegen
            IPS_SetParent($MediaID, $this->InstanceID); // Medienobjekt einsortieren unter der Sonos Instanz
            IPS_SetIdent($MediaID, $ident);
            IPS_SetPosition($MediaID, $position);
            IPS_SetName($MediaID, $name); // Medienobjekt benennen
            IPS_SetMediaCached($MediaID, true); // Das Cachen für das Mediaobjekt wird aktiviert (Verarbeitung im Arbeitsspeicher)
            file_put_contents($ImageFile, ''); // leere Media Datei anlegen
            IPS_SetMediaFile($MediaID, $ImageFile, false);    // Image im MedienPool mit Image-Datei verbinden
            IPS_SetMediaContent($MediaID, '');  // Base64 codiertes Bild ablegen
            IPS_SendMediaEvent($MediaID); //aktualisieren
        }
    }

    private function DeleteMediaImage( string $ident )
    {
        $MediaID = @$this->GetIDForIdent($ident);

        if ($MediaID !== false)
            IPS_DeleteMedia($MediaID , true);
    }

    private function RefreshCover(string $imageurl): void
    {
        if ($imageurl != '')
        {
            $Content = base64_encode(file_get_contents($imageurl)); // Bild Base64 codieren
        } 
        else 
        {
            $Content = base64_encode(file_get_contents( __DIR__ . '/../imgs/cover-800.png' ));
        }
        $mediaID = $this->GetIDForIdent('MediaImageCover');
        if ($mediaID > 0)
        {
            IPS_SetMediaContent($this->GetIDForIdent('MediaImageCover'), $Content);  // Base64 codiertes Bild ablegen
            IPS_SendMediaEvent($this->GetIDForIdent('MediaImageCover')); //aktualisieren
        }
    }

    /** GetTuneInStationID
     *
     * @param $preset
     *
     * @return string
     */
    private function GetTuneInStationID(int $preset): string
    {
        $list_json = $this->ReadPropertyString('TuneInStations');
        $list = json_decode($list_json, true);
        $stationid = '';
        foreach ($list as $station) {
            if ($preset === $station['position']) {
                $station_name = $station['station'];
                $stationid = $station['station_id'];
            }
        }
        return $stationid;
    }

    /** GetTuneInStationPreset
     *
     * @param $guideId
     *
     * @return bool|int
     */
    private function GetTuneInStationPresetPosition(string $guideId)
    {
        $presetPosition = false;
        $list_json = $this->ReadPropertyString('TuneInStations');
        $list = json_decode($list_json, true);
        foreach ($list as $station) {
            if ($guideId === $station['station_id']) {
                $presetPosition = $station['position'];
                $station_name = $station['station'];
                $stationid = $station['station_id'];
                break;
            }
        }
        return $presetPosition;
    }

    public function CopyTuneInStationsToFavorites()
    {
        $stations = json_decode( $this->ReadPropertyString('TuneInStations'), true) ;
        $favorites = json_decode( $this->ReadPropertyString('FavoritesList'), true) ;

        foreach( $stations as $station)
        {
            if ( in_array($station['station'], array_column( $favorites, 'searchPhrase')) === false )
            {
                $favorites[] = [
                    'searchPhrase' => $station['station'],
                    'musicProvider' => 'TUNEIN'
                ];
            }
        }
        $this->UpdateFormField('FavoritesList', 'values', json_encode($favorites));

        echo $this->Translate('TuneIn stations copied to favorites! Make sure that all station names in the favorits match those on tunein.com and press *apply changes*.');
        
        return true;
    }

    private function NpCommand(string $commandType, array $command = [])
    {
        $device = [
            'deviceSerialNumber' => $this->GetDevicenumber(),
            'deviceType'         => $this->GetDevicetype()
        ];


        $postfields['type'] =  $commandType;

        if ($command !== [])
            $postfields = array_merge($postfields, $command);

        $payload['url'] = '/api/np/command?' . http_build_query($device);
        $payload['postfields'] = $postfields;

        return $this->SendDataPacket('SendEcho', $payload);
    }

    private function GetAutomation($utterance, $automations)
    {
        foreach ($automations as $automation) {
            foreach ($automation['triggers'] as $trigger) {
                if (isset($trigger['payload']['utterance']) && $trigger['payload']['utterance'] === $utterance) {
                    return $automation;
                }
            }
        }

        return false;
    }

    private function GetAutomationByName($routine_name, $automations)
    {
        foreach ($automations as $automation) {
            if($automation['name'] === $routine_name)
            {
                return $automation;
            }
        }
        return false;
    }

    private function GetAutomationByKey($routine_key, $automations)
    {
        foreach ($automations as $key => $automation) {
            if($key === $routine_key)
            {
                return $automation;
            }
        }
        return false;
    }

    private function GetHeader()
    {
        $header = '
<head>
<meta charset="utf-8">
<title>Echo Info</title>
<style type="text/css">
.echo_mediaplayer {
	/*font-family: "Lucida Grande", "Lucida Sans Unicode", "Lucida Sans", "DejaVu Sans", "Verdana", "sans-serif";
	background-color: hsla(0,0%,100%,0.00);
	color: hsla(0,0%,100%,1.00);
	text-shadow: 1px 1px 3px hsla(0,0%,0%,1.00);*/
}
.echo_cover {
	display: block;
	float: left;
	padding: 8px;
}
.echo_mediaplayer .echo_cover #echocover {
	-webkit-box-shadow: 2px 2px 5px hsla(0,0%,0%,1.00);
	box-shadow: 2px 2px 5px hsla(0,0%,0%,1.00);
}
.echo_description {
	vertical-align: bottom;
	float: none;
	padding: 60px 11px 11px;
	margin-top: 0;
}
.echo_title {' . $this->GetTitleCSS() . '}
.echo_subtitle1 {' . $this->GetSubtitle1CSS() . '}
.echo_subtitle2 {' . $this->GetSubtitle2CSS() . '}
.shopping_item {
	font-size: large;
}
</style>
</head>
';
        return $header;
    }

    private function GetTitleCSS()
    {
        $TitleSize = $this->ReadPropertyInteger('TitleSize') . 'em';
        $TitleColor = $this->GetColor('TitleColor');
        if ($TitleSize == '0em') {
            $title_css = 'font-size: xx-large;';
        } else {
            $title_css = 'font-size: ' . $TitleSize . ';
			color: #' . $TitleColor . ';';
        }
        return $title_css;
    }

    private function GetSubtitle1CSS()
    {
        $Subtitle1Size = $this->ReadPropertyInteger('Subtitle1Size') . 'em';
        $Subtitle1Color = $this->GetColor('Subtitle1Color');
        if ($Subtitle1Size == '0em') {
            $subtitle1_css = 'font-size: large;';
        } else {
            $subtitle1_css = 'font-size: ' . $Subtitle1Size . ';
			color: #' . $Subtitle1Color . ';';
        }
        return $subtitle1_css;
    }

    private function GetSubtitle2CSS()
    {
        $Subtitle2Size = $this->ReadPropertyInteger('Subtitle2Size') . 'em';
        $Subtitle2Color = $this->GetColor('Subtitle2Color');
        if ($Subtitle2Size == '0em') {
            $subtitle2_css = 'font-size: large;';
        } else {
            $subtitle2_css = 'font-size: ' . $Subtitle2Size . ';
			color: #' . $Subtitle2Color . ';';
        }
        return $subtitle2_css;
    }

    private function GetColor($property)
    {
        $color = $this->ReadPropertyInteger($property);
        if ($color == 0) {
            $hex_color = 'ffffff'; // white
        } else {
            $hex_color = dechex($color);
        }
        return $hex_color;
    }

    private function SetStatePage(string $imageurl = null, string $title = null, string $subtitle_1 = null, string $subtitle_2 = null): void
    {
        
        if ($imageurl == null){
            $htmlImage = 'data:image/png;base64,' . base64_encode(file_get_contents( __DIR__ . '/../imgs/cover-small.png' ) );
        } else {
            $htmlImage  = $imageurl;
        }

        $html = '<!doctype html>
<html lang="de">' . $this->GetHeader() . '
<body>
<main class="echo_mediaplayer1">
  <section class="echo_cover"><img src="' . $htmlImage . '" alt="cover" width="145" height="145" id="echocover"></section>
  <section class="echo_description">
    <div class="echo_title">' . $title . '</div>
    <div class="echo_subtitle1">' . $subtitle_1 . '</div>
    <div class="echo_subtitle2">' . $subtitle_2 . '</div>
  </section>
</main>
</body>
</html>';
        if ($this->CheckExistence('EchoInfo') )
        {
            $this->SetValue('EchoInfo', $html);
        }     

        if ($this->ReadPropertyBoolean('ExtendedInfo')) {
            $this->SetValue('Title', $title);
            $this->SetValue('Subtitle_1', $subtitle_1);
            $this->SetValue('Subtitle_2', $subtitle_2);
            if ($this->GetBuffer('CoverURL') != $imageurl) {
                $this->SetBuffer('CoverURL', $imageurl);
                $this->RefreshCover($imageurl);
            }
        }

        if ($this->ReadPropertyBoolean('Cover')) {
            $this->SetValue('Cover_HTML', '<img src="' . $imageurl . '" alt="cover" />');
        }

        if ($this->ReadPropertyBoolean('Title')) {
            $this->SetValue('Title_HTML', '<div class="echo_title">' . $title . '</div>');
        }

        if ($this->ReadPropertyBoolean('Subtitle1')) {
            $this->SetValue('Subtitle_1_HTML', '<div class="echo_subtitle1">' . $subtitle_1 . '</div>');
        }

        if ($this->ReadPropertyBoolean('Subtitle2')) {
            $this->SetValue('Subtitle_2_HTML', '<div class="echo_subtitle2">' . $subtitle_2 . '</div>');
        }
    }


    public function UpdateAlarm(): bool
    {

        $notifications = $this->GetNotifications();
        if ($notifications === null) {
            return false;
        }

        $alarmTime = 0;
        $nextAlarm = 0; 
        $now = time();

        foreach ($notifications as $notification) {
            if (($notification['type'] === 'Alarm' || $notification['type'] === 'MusicAlarm')
                && ($notification['status'] === 'ON')
                && ($notification['deviceSerialNumber'] === IPS_GetProperty($this->InstanceID, 'Devicenumber'))) {
                $alarmTime = strtotime($notification['originalDate'] . 'T' . $notification['originalTime']);

                // In case the alarm is just running and not yet switched off we have to skip it
                if ( $alarmTime <= $now){
                    continue;
                }

                if ($nextAlarm === 0) {
                    $nextAlarm = $alarmTime;
                } else {
                    $nextAlarm = min($nextAlarm, $alarmTime);
                }
            }
        }

        if ($nextAlarm === 0) {
            $timerIntervalSec = 0;
        } else {
            $timerIntervalSec = $nextAlarm - $now;
        }

        if ($nextAlarm !== $this->GetValue('nextAlarmTime')) {
            //neuen Wert und Timer setzen.
            $this->SetValue('nextAlarmTime', $nextAlarm);

            $this->SetTimerInterval('EchoAlarm', $timerIntervalSec * 1000);
        }

        // Set the timer in case of a restart of symcon
        if ( $this->GetTimerInterval("EchoAlarm") === 0 && $timerIntervalSec > 0 )
        {
            $this->SetTimerInterval('EchoAlarm', $timerIntervalSec * 1000);         
        }

        return true;
    }

    private function GetListPage(array $Items): string
    {
        $html = '<!doctype html>
<html lang="de">' . $this->GetHeader() . '
<body>
<main class="echo_mediaplayer1">
<table class="shopping_item">';
        foreach ($Items as $Item) {
            $html .= '<tr><td>' . $Item['text'] . '</td></tr>';
        }
        $html .= '
</table>
</main>
</body>
</html>';

        return $html;
    }

    private function PlayCloudplayer(bool $shuffle, array $postfields): bool
    {
        $getfields = [
            'deviceSerialNumber'   => $this->GetDevicenumber(),
            'deviceType'           => $this->GetDevicetype(),
            'mediaOwnerCustomerId' => $this->GetCustomerID(),
            'shuffle'              => $shuffle ? 'true' : 'false'
        ];

        $payload['url'] = '/api/cloudplayer/queue-and-play?' . http_build_query($getfields);
        $payload['postfields'] = $postfields;

        $return = $this->SendEcho('SendEcho', $payload);

        return $return['http_code'] === 200;
    }


    /** GetDevicetype
     *
     * @return string
     */
    private function GetDevicetype(): string
    {
        return $this->ReadPropertyString('Devicetype');
    }

    /** GetDevicenumber
     *
     * @return string
     */
    private function GetDevicenumber(): string
    {
        return $this->ReadPropertyString('Devicenumber');
    }

    /** GetCustomerID
     *
     * @return string
     */
    private function GetCustomerID(): string
    {
        $deviceInfo = json_decode( $this->ReadAttributeString('DeviceInfo'), true);
        return $deviceInfo ['deviceOwnerCustomerId'];
    }

    /** GetLanguage
     *
     * @return string
     */
    private function GetLanguage(): string
    {
        return $this->SendDataPacket('GetLanguage');
    }

    /**
     * return form configurations on configuration step.
     *
     * @return array
     */
    private function FormElements(): array
    {
        return [
            [
                'name'    => 'Devicetype',
                'type'    => 'ValidationTextBox',
                'caption' => 'device type'],
            [
                'name'    => 'Devicenumber',
                'type'    => 'ValidationTextBox',
                'caption' => 'device number'],
            [
                'name'    => 'updateinterval',
                'type'    => 'NumberSpinner',
                'caption' => 'update interval',
                'suffix'  => 'seconds',
                'minimum' => 0],
            [
                'type'    => 'ExpansionPanel',
                'caption' => 'Variables',
                'expanded' => true,
                'items'   => [                
                    [
                        'name'    => 'PlayerControl',
                        'type'    => 'CheckBox',
                        'caption' => 'setup variables for media player control (remote, shuffle, repeat, volume, info)'],
                    [
                        'name'    => 'ExtendedInfo',
                        'type'    => 'CheckBox',
                        'caption' => 'setup variables for extended info (title, artist, album, cover)'],
                    [
                        'name'    => 'Mute',
                        'type'    => 'CheckBox',
                        'caption' => 'setup variable for mute'],
                    [
                        'name'    => 'DND',
                        'type'    => 'CheckBox',
                        'caption' => 'setup variable for Do not disturb'],
                    [
                        'name'    => 'AlarmInfo',
                        'type'    => 'CheckBox',
                        'caption' => 'setup variables for alarm info (nextAlarmTime, lastAlarmTime)'],
                    [
                        'name'    => 'ShoppingList',
                        'type'    => 'CheckBox',
                        'caption' => 'setup variable for a shopping list'],
                    [
                        'name'    => 'TaskList',
                        'type'    => 'CheckBox',
                        'caption' => 'setup variable for a task list'],
                    [
                        'name'    => 'EchoActions',
                        'type'    => 'CheckBox',
                        'caption' => 'setup variable for actions (i.g. flash briefing, traffic, weather,...)'],
                    [
                        'name'    => 'EchoTTS',
                        'type'    => 'CheckBox',
                        'caption' => 'setup variable for text-to-speech'],
                    [
                        'name'    => 'LastAction',
                        'type'    => 'CheckBox',
                        'caption' => 'setup variables for last action (function has to be enabled in EchoIO instance)'],
                    [
                        'name'    => 'OnlineStatus',
                        'type'    => 'CheckBox',
                        'caption' => 'setup variable for online status']
                ]
            ],
            [
                'type'    => 'ExpansionPanel',
                'caption' => 'Favorites',
                'items'   => [
                    [
                        'name'    => 'EchoFavorites',
                        'type'    => 'CheckBox',
                        'caption' => 'setup variable for favorites'
                    ],
                    [
                        'type'     => 'List',
                        'name'     => 'FavoritesList',
                        'caption'  => 'Favorites',
                        'rowCount' => 10,
                        'add'      => true,
                        'delete'   => true,
                        'edit'   => true,
                        'changeOrder' => true,
                        'columns'  => [
                            [
                                'name'    => 'searchPhrase',
                                'caption' => 'Search Phrase',
                                'width'   => 'auto',
                                'save'    => true,
                                'add'     => '',
                                'edit'    => [
                                    'type' => 'ValidationTextBox']],
                            [
                                'name'    => 'musicProvider',
                                'caption' => 'Music Provider',
                                'width'   => '200px',
                                'save'    => true,
                                'add'     => 'DEFAULT',
                                'edit'    => [
                                    'type' => 'Select',
                                    'options' => $this->GetMusicProviersFormField()
                                ],
                                'visible' => true]]]
                ]
            ],
            [
                'type'    => 'ExpansionPanel',
                'caption' => 'Alexa Routines',
                'items'   => [
                    [
                        'name'    => 'routines_wf',
                        'type'    => 'CheckBox',
                        'caption' => 'setup variable for Alexa routines'],
                    [
                        'type'     => 'List',
                        'name'     => 'routines',
                        'caption'  => 'Alexa Routines',
                        'rowCount' => 20,
                        'add'      => false,
                        'delete'   => false,
                        'sort'     => [
                            'column'    => 'routine_name',
                            'direction' => 'ascending'],
                        'columns'  => [
                            [
                                'name'    => 'routine_id',
                                'caption' => 'ID',
                                'width'   => '100px',
                                'save'    => true,
                                'visible' => true],
                            [
                                'name'    => 'automationId',
                                'caption' => 'automationId',
                                'width'   => '100px',
                                'save'    => true,
                                'visible' => false],
                            [
                                'name'    => 'routine_name',
                                'caption' => 'routine name',
                                'width'   => '200px',
                                'save'    => true],
                            [
                                'name'    => 'routine_utterance',
                                'caption' => 'routine utterance',
                                'width'   => 'auto',
                                'save'    => true,
                                'visible' => true]],
                        'values'   => $this->GetAutomationsList()
                   ]]],
            [
                'type'    => 'ExpansionPanel',
                'caption' => 'Layout for extended info',
                'items'   => [
                    [
                        'name'    => 'Cover',
                        'type'    => 'CheckBox',
                        'caption' => 'setup separate variable for the cover as HTML image'],
                    [
                        'name'    => 'Title',
                        'type'    => 'CheckBox',
                        'caption' => 'setup separate variable for the title as HTML'],
                    [
                        'name'    => 'TitleColor',
                        'type'    => 'SelectColor',
                        'caption' => 'title color'],
                    [
                        'type'    => 'Select',
                        'name'    => 'TitleSize',
                        'caption' => 'size title',
                        'options' => $this->SelectionFontSize()],
                    [
                        'name'    => 'Subtitle1',
                        'type'    => 'CheckBox',
                        'caption' => 'setup separate variable for the subtitle 1 as HTML'],
                    [
                        'name'    => 'Subtitle1Color',
                        'type'    => 'SelectColor',
                        'caption' => 'subtitle 1 color'],
                    [
                        'type'    => 'Select',
                        'name'    => 'Subtitle1Size',
                        'caption' => 'size subtitle 1',
                        'options' => $this->SelectionFontSize()],
                    [
                        'name'    => 'Subtitle2',
                        'type'    => 'CheckBox',
                        'caption' => 'setup separate variable for the subtitle 2 as HTML'],
                    [
                        'name'    => 'Subtitle2Color',
                        'type'    => 'SelectColor',
                        'caption' => 'subtitle 2 color'],
                    [
                        'type'    => 'Select',
                        'name'    => 'Subtitle2Size',
                        'caption' => 'size subtitle 2',
                        'options' => $this->SelectionFontSize()]]],
            [
                'type'    => 'ExpansionPanel',
                'caption' => 'TuneIn stations (will be replaced by favorites in future)',
                'items'   => [
                    [
                        "type" => "Label", 
                        "caption"=> "Migration zu Favoriten: Die TuneIn-Sender dieser Liste können mit dem Button 'TuneIn-Sender in Favoritenliste kopieren' (am Ende des Konfigurationsformulars) in die neue Favoritenliste übernommen werden. In den Favoriten werden die Sendernamen (nicht mehr die Stationskennung) verwendet, um den Sender zu starten. Daher müssen die Sendernamen in der Favoriten-Liste exakt mit den Sendernamen auf https://tunein.com/ übereinstimmen.",
                        "link" => true
                    ],
                    [
                        'name'    => 'EchoTuneInRemote',
                        'type'    => 'CheckBox',
                        'caption' => 'setup variable for TuneIn stations'],
                    [
                        'type'     => 'List',
                        'name'     => 'TuneInStations',
                        'caption'  => 'TuneIn stations',
                        'rowCount' => 20,
                        'add'      => true,
                        'delete'   => true,
                        'sort'     => [
                            'column'    => 'position',
                            'direction' => 'ascending'],
                        'columns'  => [
                            [
                                'name'    => 'position',
                                'caption' => 'Station',
                                'width'   => '100px',
                                'save'    => true,
                                'visible' => true,
                                'add'     => 0,
                                'edit'    => [
                                    'type' => 'NumberSpinner']],
                            [
                                'name'    => 'station',
                                'caption' => 'Station Name',
                                'width'   => '200px',
                                'save'    => true,
                                'add'     => '',
                                'edit'    => [
                                    'type' => 'ValidationTextBox']],
                            [
                                'name'    => 'station_id',
                                'caption' => 'Station ID',
                                'width'   => 'auto',
                                'save'    => true,
                                'add'     => '',
                                'edit'    => [
                                    'type' => 'ValidationTextBox'],
                                'visible' => true]]]
                ]]
            ];
    }

    private function SelectionFontSize()
    {
        $selection = [
            [
                'label' => 'Please select a font size',
                'value' => 0],
            [
                'label' => '1em',
                'value' => 1],
            [
                'label' => '2em',
                'value' => 2],
            [
                'label' => '3em',
                'value' => 3],
            [
                'label' => '4em',
                'value' => 4],
            [
                'label' => '5em',
                'value' => 5],
            [
                'label' => '6em',
                'value' => 6],
            [
                'label' => '7em',
                'value' => 7],
            [
                'label' => '8em',
                'value' => 8],
            [
                'label' => '9em',
                'value' => 9],
            [
                'label' => '10em',
                'value' => 10],
            [
                'label' => '11em',
                'value' => 11],
            [
                'label' => '12em',
                'value' => 12],
            [
                'label' => '13em',
                'value' => 13],
            [
                'label' => '14em',
                'value' => 14],
            [
                'label' => '15em',
                'value' => 15],
            [
                'label' => '16em',
                'value' => 16],
            [
                'label' => '17em',
                'value' => 17],
            [
                'label' => '18em',
                'value' => 18],
            [
                'label' => '19em',
                'value' => 19],
            [
                'label' => '20em',
                'value' => 20],
            [
                'label' => '21em',
                'value' => 21],
            [
                'label' => '22em',
                'value' => 22],
            [
                'label' => '23em',
                'value' => 23],
            [
                'label' => '24em',
                'value' => 24],
            [
                'label' => '25em',
                'value' => 25],
            [
                'label' => '26em',
                'value' => 26],
            [
                'label' => '27em',
                'value' => 27],
            [
                'label' => '28em',
                'value' => 28],
            [
                'label' => '29em',
                'value' => 29],
            [
                'label' => '30em',
                'value' => 30],
            [
                'label' => '31em',
                'value' => 31],
            [
                'label' => '32em',
                'value' => 32],
            [
                'label' => '33em',
                'value' => 33],
            [
                'label' => '34em',
                'value' => 34],
            [
                'label' => '35em',
                'value' => 35],
            [
                'label' => '36em',
                'value' => 36],
            [
                'label' => '37em',
                'value' => 37],
            [
                'label' => '38em',
                'value' => 38],
            [
                'label' => '39em',
                'value' => 39],
            [
                'label' => '40em',
                'value' => 40]];
        return $selection;
    }

    /**
     * return form actions by token.
     *
     * @return array
     */
    private function FormActions(): array
    {
        $form = [
            [
                'type'    => 'TestCenter',],
            [
                'type'    => 'Label',
                'caption' => 'Migration:'],
            [
                'type'    => 'Button',
                'caption' => 'Copy TuneIn stations to favorites',
                'onClick' => "EchoRemote_CopyTuneInStationsToFavorites(\$id);"]
            ];

        return $form;
    }

    /**
     * return from status.
     *
     * @return array
     */
    private function FormStatus(): array
    {
        $form = [
            [
                'code'    => 210,
                'icon'    => 'error',
                'caption' => 'devicetype field must not be empty.'],
            [
                'code'    => 211,
                'icon'    => 'error',
                'caption' => 'devicenumber field must not be empty.']];

        return $form;
    }

    //</editor-fold>
}
