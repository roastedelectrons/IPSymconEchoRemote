<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/EchoBufferHelper.php';
require_once __DIR__ . '/../libs/EchoDebugHelper.php';
require_once __DIR__ . '/../libs/VariableProfileHelper.php';
require_once __DIR__ . '/../libs/ColorHelper.php';
require_once __DIR__ . '/../libs/AlexaSmartHome.php';


class AlexaSmartHomeDevice extends IPSModule
{
    use IPSymconEchoRemote\EchoBufferHelper;
    use IPSymconEchoRemote\EchoDebugHelper;
    use IPSymconEchoRemote\VariableProfileHelper;
    use IPSymconEchoRemote\ColorHelper;
    use IPSymconEchoRemote\AlexaSmartHome;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        $this->RegisterPropertyString('EntityID', '');
        $this->RegisterPropertyInteger('UpdateInterval', 0);

        $this->RegisterAttributeString('DeviceInformation', '');

        $this->ConnectParent('{C7F853A4-60D2-99CD-A198-2C9025E2E312}');

        //we will wait until the kernel is ready
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function Destroy() {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        $this->SetReceiveDataFilter('.*(AlexaSmartHomeDevice).*');

        if (trim($this->getEntityID()) == ''){
            $this->SetStatus(201);
            return false;
        }

        
        if (json_decode($this->ReadAttributeString('DeviceInformation')) == false){

            $deviceInfo = $this->getSmartHomeDevice();

            if ($deviceInfo == []){
                // invalid entityID
                $this->SetStatus(201);
                return false;
            } else {
                $this->WriteAttributeString('DeviceInformation', json_encode($deviceInfo));
            }
        }

        if ($this->GetStatus() != 102){
            $this->SetStatus(102);
        }

        //Apply filter
        $this->SetReceiveDataFilter('.*(AlexaSmartHomeDevice|'.$this->getEntityID().').*');

        $this->RegisterVariables();

    }

    private function RegisterVariables()
    {

        $deviceInformation = json_decode($this->ReadAttributeString('DeviceInformation'), true);

        if (isset($deviceInformation['capabilities']) == false)
            return false;

        $capabilities = $deviceInformation['capabilities'];


        foreach ($capabilities as $capability){
            switch ($capability['interfaceName']){
                case 'Alexa.EndpointHealth':
                    $this->MaintainVariable('connectivity', $this->Translate('connectivity'), 0, '~Switch', 1, true );
                    break;

                case 'Alexa.PowerController':
                    $this->MaintainVariable('powerState', $this->Translate('state'), 0, '~Switch', 1, true );
                    $this->EnableAction('powerState');
                    break;

                case 'Alexa.PercentageController':
                    $this->MaintainVariable('percentage', $this->Translate('percentage'), 1, '~Intensity.100', 1, true );
                    $this->EnableAction('percentage');
                    break;

                case 'Alexa.BrightnessController':
                    $this->MaintainVariable('brightness', $this->Translate('brightness'), 1, '~Intensity.100', 1, true );
                    $this->EnableAction('brightness');
                    break;

                case 'Alexa.ColorController':
                    $this->MaintainVariable('color', $this->Translate('color'), 1, '~HexColor', 1, true );
                    $this->EnableAction('color');
                    break;

                case 'Alexa.ColorTemperatureController':
                    $this->MaintainVariable('colorTemperatureInKelvin', $this->Translate('color temperature'), 1, '~TWColor', 1, true );
                    $this->EnableAction('colorTemperatureInKelvin');
                    break;

                case 'Alexa.TemperatureSensor':
                    $this->MaintainVariable('temperature', $this->Translate('temperature'), 2, '~Temperature', 1, true );
                    break;

                case 'Alexa.LightSensor':
                    $this->MaintainVariable('illuminance', $this->Translate('illuminance'), 1, '~Illumination', 1, true );
                    break;

                /*
                case 'Alexa.MotionSensor':
                    $this->MaintainVariable('detectionState', $this->Translate('motion'), 0, '~Motion', 1, true );
                    break;  
                */     
                    
                case 'Alexa.ThermostatController':
                    // Register setpoint variable
                    $this->MaintainVariable('targetSetpoint', $this->Translate('set temperature'), 2, '~Temperature', 1, true );
                    $this->EnableAction('targetSetpoint');

                    // Create Mode Profile
                    if (isset($capability['configuration']['supportedModes']) && count($capability['configuration']['supportedModes']) > 0 ){
                        $associations = array();
                        foreach($capability['configuration']['supportedModes'] as $mode){
                            $associations[] = [$mode['value'], $mode['value'], '', -1];
                        }
                        $profileName = 'Alexa.ThermostatMode.'.$this->InstanceID;

                        $this->RegisterProfileAssociation($profileName, 'temperature-list', '', '', 0, 1, 0, 0, VARIABLETYPE_STRING, $associations);
                    } else {
                        $profileName = '';
                    }

                    // Register mode variable
                    $this->MaintainVariable('thermostatMode', $this->Translate('thermostat mode'), VARIABLETYPE_STRING, $profileName, 1, true );
                    if ($profileName !== ''){
                        $this->EnableAction('thermostatMode');
                    }
                    break;

                case 'Alexa.SceneController': 
                    $this->RegisterProfileAssociation(
                        'Alexa.Scene', '', '', '', 0, 1, 0, 0, VARIABLETYPE_INTEGER, [
                            [0, $this->Translate('Deactivate'), '', -1],
                            [1, $this->Translate('Activate'), '', -1]
                        ]
                    );
                    $this->MaintainVariable('scene', $this->Translate('scene'), 1, 'Alexa.Scene', 0, true );
                    $this->SetValue('scene', -1);
                    $this->EnableAction('scene');
                    break;


                case 'Alexa.ModeController':

                    $instanceName = $capability['instance'];
                    $variableIdent = 'mode_'.str_replace('.', '_', $instanceName);

                    // Create Mode Profile
                    if (isset($capability['configuration']['supportedModes']) && count($capability['configuration']['supportedModes']) > 0 ){
                        $associations = array();
                        foreach($capability['configuration']['supportedModes'] as $mode){
                            $modeName = $mode['value'];

                            if (isset($mode['modeResources']['friendlyNames'])){
                                foreach ($mode['modeResources']['friendlyNames'] as $friendlyName){
                                    if ($friendlyName['@type'] == 'text' && strtolower($friendlyName['value']['locale']) == 'en-us'){
                                        $modeName = $friendlyName['value']['text'];
                                        break;
                                    }
                                }
                            }
                            $associations[] = [$mode['value'], $modeName, '', -1];
                        }
                        $profileName = 'Alexa.ModeController.'.$instanceName.'.'.$this->InstanceID;

                        $this->RegisterProfileAssociation($profileName, '', '', '', 0, 1, 0, 0, VARIABLETYPE_STRING, $associations);
                    } else {
                        $profileName = '';
                    }

                    // Register mode variable
                    $this->MaintainVariable($variableIdent, $instanceName, VARIABLETYPE_STRING, $profileName, 1, true );
                    if ($profileName !== ''){
                        $this->EnableAction($variableIdent);
                    }
                    break;

                case 'Alexa.ToggleController':
                    $instanceName = $capability['instance'];
                    $variableIdent = 'toggleState_'.str_replace('.', '_', $instanceName);

                    $this->MaintainVariable($variableIdent, $instanceName, VARIABLETYPE_BOOLEAN, '~Switch', 1, true );
                    $this->EnableAction($variableIdent);
                    break;

                case 'Alexa.LockController':
                    $this->MaintainVariable('lockState', $this->Translate('lock state'), VARIABLETYPE_BOOLEAN, '~Lock', 1, true );
                    $this->EnableAction('lockState');
                    break;

            }
        }
    }
    

    public function RequestAction($ident, $value)
    {
        $name = explode('_', $ident)[0];

        switch ($name) {
            // Variable actions
            case 'powerState':
                $this->Switch($value);
                $this->SetValue($ident, $value);
                break;

            case 'percentage':
                $this->SetPercentage($value);
                $this->SetValue($ident, $value);
                break;

            case 'brightness':
                $this->SetBrightness($value);
                $this->SetValue($ident, $value);
                break;

            case 'colorTemperatureInKelvin':
                $this->SetColorTemperature ($value);
                $this->SetValue($ident, $value);
                break;
            
            case 'color':
                $this->SetColor($value);
                break;

            case 'targetSetpoint':
                $this->SetTemperature ($value);
                $this->SetValue($ident, $value);
                break;

            case 'thermostatMode':
                $this->SetThermostatMode ($value);
                $this->SetValue($ident, $value);
                break;

            case 'scene':
                $this->SetValue($ident, $value);
                $this->Scene( boolval($value));
                $this->SetValue($ident, -1);
                break;

            case 'mode':
                $instance = substr($ident, 5);
                $instance = str_replace('_', '.', $instance);
                $this->SetMode($instance, $value);
                $this->SetValue($ident, $value);
                break;

            case 'toggleState':
                $instance = substr($ident, 12);
                $instance = str_replace('_', '.', $instance);
                $this->ToggleState( $instance, $value);
                $this->SetValue($ident, $value);
                break;

            case 'lockState':
                $this->Lock($value);
                $this->SetValue($ident, $value);
                break;      

            // Form actions
            case 'UpdateDeviceInformation':
                $this->WriteAttributeString('DeviceInformation', json_encode($this->getSmartHomeDevice()));
                $this->UpdateFormField('DeviceInformation', 'caption', $this->FormGetDeviceInformation() ) ;
                $this->RegisterVariables();
                break;

            case 'Update':
                $this->UpdateState() ;
                break;
                
        }
    }


    private function processStates( $states )
    {
        if (!isset($states['deviceStates']))
            return;

        foreach ($states['deviceStates'] as $deviceStates){
            
            if ($deviceStates['entity']['entityId'] != $this->getEntityID())
                continue;

            foreach ($deviceStates['capabilityStates'] as $state){

                $state = json_decode($state, true);

                switch ($state['namespace']){
                    case 'Alexa.EndpointHealth':
                        if ( isset($state['value']['value']) &&  $state['value']['value'] == 'OK' ){
                            $value = true;
                        }else {
                            $value = false;
                        }
                        @$this->SetValue('connectivity', $value);
                        break;

                    case 'Alexa.PowerController':
                        $value = ($state['value'] == 'ON') ? true : false;
                        @$this->SetValue('powerState', $value);
                        break;

                    case 'Alexa.PercentageController':
                        $value = intval($state['value']);
                        @$this->SetValue('percentage', $value);
                        break;

                    case 'Alexa.BrightnessController':
                        $value = intval($state['value']);
                        @$this->SetValue('brightness', $value);
                        break;

                    case 'Alexa.ColorController':
                        $rgb = $this->hsv2rgb( $state['value']['hue'], $state['value']['saturation']*100, $state['value']['brightness']*100);
                        @$this->SetValue('color', hexdec( $rgb['hex']));
                        break;

                    case 'Alexa.ColorTemperatureController':
                        $value = intval($state['value']);
                        @$this->SetValue('colorTemperatureInKelvin', $value);
                        break;

                    case 'Alexa.TemperatureSensor':
                        $value = floatval($state['value']['value']);
                        @$this->SetValue('temperature', $value);
                        break;

                    case 'Alexa.LightSensor':
                        if ($state['name'] == "illuminance"){
                            $value = intval($state['value']);
                            @$this->SetValue('illuminance', $value);
                        }
                        break;

                    /*
                    case 'Alexa.MotionSensor':
                        $value = ($state['value'] == 'DETECTED') ? true : false;
                        $this->SetValue('detectionState', $value);
                        break;       
                    */

                    case 'Alexa.ThermostatController':
                        if ($state['name'] == "targetSetpoint"){
                            $value = floatval($state['value']['value']);
                            @$this->SetValue('targetSetpoint', $value);
                        }

                        if ($state['name'] == "thermostatMode"){
                            $value = $state['value'];
                            @$this->SetValue('thermostatMode', $value);
                        }
                        break;

                    case 'Alexa.ModeController':
                        $ident = 'mode_'.str_replace('.', '_', $state['instance']);
                        $value = $state['value'];
                        @$this->SetValue($ident, $value);
                        break;

                    case 'Alexa.ToggleController':
                        $value = ($state['value'] == 'ON') ? true : false;
                        $ident = 'toggleState_'.str_replace('.', '_', $state['instance']);
                        @$this->SetValue($ident, $value);
                        break;

                    case 'Alexa.LockController':
                        $value = ($state['value'] == 'LOCKED') ? true : false;
                        @$this->SetValue('lockState', $value);
                        break;
                        
                        
                }
            }
        }
    }

    public function Update()
    {
        $lastUpdate = intval($this->GetBuffer('LastUpdate'));

        if ( time() - $lastUpdate <= 900) {
            trigger_error( 'Too many requests. Function can be called only once every 15 minutes.' , E_USER_ERROR);
            return false;
        }

        $this->SetBuffer('LastUpdate', time());

        $this->UpdateState();

    }

    private function UpdateState()
    {

            $states = $this->GetState();

            $this->processStates($states);

    }

    private function GetState()
    {
        $url = '/api/phoenix/state';

        $data['stateRequests'][] = [
            'entityId' => $this->getEntityID(),
            'entityType'=> 'ENTITY'
        ];
        $result = $this->SendCommand( $url, $data , 'POST');
        
        $this->SendDebug('deviceStates', json_encode($result) , 0);

        return $result;
    }

    private function Switch( bool $state )
    {
        $url = '/api/phoenix/state';

        $action = $state ? 'turnOn' : 'turnOff';

        $data['controlRequests'][] = [
            'entityId' => $this->getEntityID(),
            'entityType'=> 'ENTITY',
            'parameters' => ['action' => $action]
        ];
        $result = $this->SendCommand( $url, $data , 'PUT'); 
        
        return $result;
    }

    private function SetPercentage( int $percentage )
    {
        $url = '/api/phoenix/state';

        if ($percentage > 100) $percentage = 100;
        if ($percentage < 0) $percentage = 0;

        $data['controlRequests'][] = [
            'entityId' => $this->getEntityID(),
            'entityType'=> 'ENTITY',
            'parameters' => [
                'action' => 'setPercentage',
                'percentage' => $percentage
            ]
        ];
        $result = $this->SendCommand( $url, $data , 'PUT');  
        
        return $result;
    }

    private function Scene( bool $state )
    {
        $url = '/api/phoenix/state';

        $action = $state ? 'sceneActivate' : 'sceneDeactivate';

        $data['controlRequests'][] = [
            'entityId' => $this->getEntityID(),
            'entityType'=> 'ENTITY',
            'parameters' => ['action' => $action]
        ];
        $result = $this->SendCommand( $url, $data , 'PUT'); 
        
        return $result;
    }

    private function SetBrightness( int $brightness )
    {
        $url = '/api/phoenix/state';

        if ($brightness > 100) $brightness = 100;
        if ($brightness < 0) $brightness = 0;

        $data['controlRequests'][] = [
            'entityId' => $this->getEntityID(),
            'entityType'=> 'ENTITY',
            'parameters' => [
                'action' => 'setBrightness',
                'brightness' => $brightness
            ]
        ];
        $result = $this->SendCommand( $url, $data , 'PUT');   
        
        return $result;
    }

    private function RampBrightness( int $brightness, int $durationMinutes)
    {
        $url = '/api/phoenix/state';

        if ($brightness > 100) $brightness = 100;
        if ($brightness < 0) $brightness = 0;

        if ($durationMinutes > 60) $durationMinutes = 60;
        if ($durationMinutes < 5) $durationMinutes = 5;

        $data['controlRequests'][] = [
            'entityId' => $this->getEntityID(),
            'entityType'=> 'ENTITY',
            'parameters' => [
                'action' => 'rampBrightness',
                'brightness' => $brightness, 
                'duration' => $durationMinutes
            ]
        ];
        $result = $this->SendCommand( $url, $data , 'PUT');   
        
        return $result;
    }

    private function SetColorTemperature( int $kelvin )
    {
        $url = '/api/phoenix/state';

        if ($kelvin > 10000) $kelvin = 10000;
        if ($kelvin < 1000) $kelvin = 1000;

        $data['controlRequests'][] = [
            'entityId' => $this->getEntityID(),
            'entityType'=> 'ENTITY',
            'parameters' => [
                'action' => 'setColorTemperature',
                'colorTemperatureInKelvin' => $kelvin
            ]
        ];
        $result = $this->SendCommand( $url, $data , 'PUT');  
        
        return $result;
    }

    private function SetColor( int $hexColor )
    {
        $color = $this->nearestColorName( dechex($hexColor ) );

        $this->SetValue('color', hexdec($color['color']));

        return $this->SetColorByName($color['name'] );
    }


    private function SetColorByName( string $colorName )
    {
        $url = '/api/phoenix/state';

        $data['controlRequests'][] = [
            'entityId' => $this->getEntityID(),
            'entityType'=> 'ENTITY',
            'parameters' => [
                'action' => 'setColor',
                'colorName' => $colorName,
            ]
        ];

        $result = $this->SendCommand( $url, $data , 'PUT');   
        
        return $result;
    }

    private function SetTemperature( float $temperature )
    {
        $url = '/api/phoenix/state';

        $data['controlRequests'][] = [
            'entityId' => $this->getEntityID(),
            'entityType'=> 'ENTITY',
            'parameters' => [
                'action' => 'setTargetTemperature',
                'targetTemperature.value' => $temperature,
                'targetTemperature.scale' => 'celsius'
            ]
        ];

        $result = $this->SendCommand( $url, $data , 'PUT');  
        
        return $result;
    }

    private function SetThermostatMode( string $thermostatMode )
    {
        $url = '/api/phoenix/state';

        $data['controlRequests'][] = [
            'entityId' => $this->getEntityID(),
            'entityType'=> 'ENTITY',
            'parameters' => [
                'action' => 'setThermostatMode',
                'thermostatMode.value' => $thermostatMode
            ]
        ];

        $result = $this->SendCommand( $url, $data , 'PUT');  
        
        return $result;
    }

    private function SetMode( string $instance, string $mode )
    {
        $url = '/api/phoenix/state';

        $action = 'setModeValue';

        $data['controlRequests'][] = [
            'entityId' => $this->getEntityID(),
            'entityType'=> 'ENTITY',
            'parameters' => [
                'action' => $action, 
                'mode' => $mode,
                'instance' => $instance
            ]
        ];

        $result = $this->SendCommand( $url, $data , 'PUT');  
        
        return $result;
    }

    private function ToggleState( string $instance, bool $state )
    {
         // function to be verified
        $url = '/api/phoenix/state';

        $action = $state ? 'turnOnToggle' : 'turnOffToggle';

        //$action = $action.'@'.$this->getEntityID().'_'.$instance;

        $data['controlRequests'][] = [
            'entityId' => $this->getEntityID(),
            'entityType'=> 'ENTITY',
            'parameters' => [
                'action' => $action,
                'instance' => $instance
            ]
        ];
        $result = $this->SendCommand( $url, $data , 'PUT'); 
        
        return $result;
    }

    private function Lock( bool $state )
    {
        $url = '/api/phoenix/state';

        $lockState = $state ? 'LOCKED' : 'UNLOCKED';

        $data['controlRequests'][] = [
            'entityId' => $this->getEntityID(),
            'entityType'=> 'ENTITY',
            'parameters' => [
                'action' => 'lockAction',
                'targetLockState.value' => $lockState
            ]
        ];
        $result = $this->SendCommand( $url, $data , 'PUT'); 
        
        return $result;
    }

    private function getSmartHomeDevice()
    {
        $devices = $this->getSmartHomeDeviceList(true);

        foreach ($devices as $device){
            if ($device['entityId'] == $this->getEntityID()){
                $this->SendDebug('deviceInformation', json_encode($device), 0);
                return $device;
            }
        }

        return [];
    }



    private function getSmartHomeEntity()
    {
        $entities = $this->getSmartHomeEntities();

        $entityID = $this->getEntityID();

        foreach( $entities as $entity){
            if ($entity['id'] == $entityID ){
                return $entity;
            }
        }

        return array();
    }



    private function getEntityID()
    {
        return $this->ReadPropertyString('EntityID');
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
        $data = json_decode($JSONString, true);
        $payload = $data['Payload'];
        $this->SendDebug('Receive Data', $JSONString, 0);
        
        if ( $data['EntityID'] != $this->getEntityID() &&  $data['EntityID'] != 'AlexaSmartHomeDevice')
            return;

        switch ($data['Type'])
        {            
            case 'deviceStates':
                $this->processStates($data['Payload']);
                break;
        }

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

    private function FormElements(): array
    {

        $elements[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'EntityID',
            'caption' => "EntityID"
        ];

        $elements[] = [
            'type'    => 'Select',
            'name'    => 'UpdateInterval',
            'caption' => "Update interval",
            'options' => [
                [ 'caption' => 'disabled', 'value' => 0],
                [ 'caption' => '15 minutes', 'value' => 15],
                [ 'caption' => '60 minutes', 'value' => 60],
                [ 'caption' => '24 hours', 'value' => 1440]
            ]
        ];

        $elements[] = [
            'type'    => 'Label',
            'name'    => 'DeviceInformation',
            'caption' => $this->FormGetDeviceInformation(),
            'italic' => false,
            'color' => 7566195
        ];
        $elements[] = [
            'type'    => 'Button',
            'caption' => 'Load Device Information',
            'onClick' => 'IPS_RequestAction($id, "UpdateDeviceInformation", "");', 
        ];

        return $elements;
    }


    private function FormActions(): array
    {
        $elements[] = [
            'type'    => 'Button',
            'caption' => 'Update state variables',
            'onClick' => 'IPS_RequestAction($id, "Update", "");', 
        ];

        $elements[] = [
            'type'    => 'TestCenter',
            'caption' => ""
        ];
        return $elements;
    }


    private function FormStatus(): array
    {
        $form = [
            [
                'code' => 201,
                'icon' => 'error',
                'caption' => 'Invalid EntityID'
            ]
        ];

        return $form;
    }

    private function FormGetDeviceInformation()
    {
        $info = json_decode( $this->ReadAttributeString('DeviceInformation'), true);

        $text = $this->Translate("Device Information").":\n\n";
        if ($info != [] && isset($info['applianceId'])){
            $text .= $this->Translate("Name").": \t\t\t". $info['friendlyName']."\n";
            $text .= $this->Translate("Description").":\t". $info['friendlyDescription']."\n";
            $text .= $this->Translate("Manufacturer").":\t\t". $info['manufacturerName']."\n";
            $text .= $this->Translate("Type").": \t\t\t". implode(', ', $info['applianceTypes'])."\n";
            $text .= $this->Translate("Connection").": \t\t". $this->getDeviceConnection($info) ."\n";
            
            $text .= "\n";
            $text .= $this->Translate('Capabilities').':  '."\n";

            $capabilities = array_column( $info['capabilities'], 'interfaceName');
            $capabilities = array_unique($capabilities);
            asort( $capabilities);
            foreach($capabilities as $capability){
                if ($this->isCapabilitySupported($capability)){
                    $text .= '  ☑  '.$capability."\n";
                } else {
                    $text .= '  ☐  '.$capability."\n";
                }
                
            }

        }

        return $text;
    }
}
