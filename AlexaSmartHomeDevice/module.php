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
            $interfaceNameIdent = $this->sanitizeIdent($capability['interfaceName']);
            switch ($capability['interfaceName']){
                case 'Alexa.EndpointHealth':
                    $ident = $interfaceNameIdent.'_connectivity';
                    $this->MaintainVariable($ident , $this->Translate('connectivity'), 0, '~Switch', 1, true );
                    break;

                case 'Alexa.PowerController':
                    $ident = $interfaceNameIdent.'_powerState';
                    $this->MaintainVariable($ident, $this->Translate('state'), 0, '~Switch', 1, true );
                    $this->EnableAction($ident);
                    break;

                case 'Alexa.PercentageController':
                    $ident = $interfaceNameIdent.'_percentage';
                    $this->MaintainVariable($ident, $this->Translate('percentage'), 1, '~Intensity.100', 1, true );
                    $this->EnableAction($ident);
                    break;

                case 'Alexa.BrightnessController':
                    $ident = $interfaceNameIdent.'_brightness';
                    $this->MaintainVariable($ident, $this->Translate('brightness'), 1, '~Intensity.100', 1, true );
                    $this->EnableAction($ident);
                    break;

                case 'Alexa.ColorController':
                    $ident = $interfaceNameIdent.'_color';
                    $this->MaintainVariable($ident, $this->Translate('color'), 1, '~HexColor', 1, true );
                    $this->EnableAction($ident);
                    break;

                case 'Alexa.ColorTemperatureController':
                    $ident = $interfaceNameIdent.'_colorTemperatureInKelvin';
                    $this->MaintainVariable($ident, $this->Translate('color temperature'), 1, '~TWColor', 1, true );
                    $this->EnableAction($ident);
                    break;

                case 'Alexa.TemperatureSensor':
                    $ident = $interfaceNameIdent.'_temperature';
                    $this->MaintainVariable($ident, $this->Translate('temperature'), 2, '~Temperature', 1, true );
                    break;

                case 'Alexa.HumiditySensor':
                    $ident = $interfaceNameIdent.'_relativeHumidity';
                    $this->MaintainVariable($ident, $this->Translate('relative humidity'), 2, '~Humidity.F', 1, true );
                    break;

                case 'Alexa.LightSensor':
                    $ident = $interfaceNameIdent.'_illuminance';
                    $this->MaintainVariable($ident, $this->Translate('illuminance'), 1, '~Illumination', 1, true );
                    break;

                /*
                case 'Alexa.MotionSensor':
                    $this->MaintainVariable('detectionState', $this->Translate('motion'), 0, '~Motion', 1, true );
                    break;  
                */     
                    
                case 'Alexa.ThermostatController':
                    // setpoint variable
                    $ident = $interfaceNameIdent.'_targetSetpoint';
                    $this->MaintainVariable($ident, $this->Translate('set temperature'), 2, '~Temperature', 1, true );
                    $this->EnableAction($ident);

                    //  mode variable
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

                    $ident = $interfaceNameIdent.'_thermostatMode';
                    $this->MaintainVariable($ident, $this->Translate('thermostat mode'), VARIABLETYPE_STRING, $profileName, 1, true );
                    if ($profileName !== ''){
                        $this->EnableAction($ident);
                    }
                    break;

                case 'Alexa.SceneController': 
                    $this->RegisterProfileAssociation(
                        'Alexa.Scene', '', '', '', 0, 1, 0, 0, VARIABLETYPE_INTEGER, [
                            [0, $this->Translate('Deactivate'), '', -1],
                            [1, $this->Translate('Activate'), '', -1]
                        ]
                    );

                    $ident = $interfaceNameIdent.'_scene';
                    $this->MaintainVariable($ident, $this->Translate('scene'), 1, 'Alexa.Scene', 0, true );
                    $this->SetValue($ident, -1);
                    $this->EnableAction($ident);
                    break;


                case 'Alexa.ModeController':

                    $instanceName = $capability['instance'];
                    if (isset($capability['resources']['friendlyNames'])){
                        $variableName = $this->getFriendlyName($capability['resources']['friendlyNames'], $capability['instance']);
                    } else {
                        $variableName = $capability['instance'];
                    }
                    $variableIdent = $interfaceNameIdent.'_mode_'.$this->sanitizeIdent($capability['instance']);                   

                    // Create Mode Profile
                    if (isset($capability['configuration']['supportedModes']) && count($capability['configuration']['supportedModes']) > 0 ){
                        $associations = array();
                        foreach($capability['configuration']['supportedModes'] as $mode){
                            $modeName = $mode['value'];

                            if (isset($mode['modeResources']['friendlyNames'])){
                                $modeName = $this->getFriendlyName($mode['modeResources']['friendlyNames'], $mode['value']);
                            }
                            $associations[] = [$mode['value'], $modeName, '', -1];
                        }
                        $profileName = 'Alexa.ModeController.'.$instanceName.'.'.$this->InstanceID;

                        $this->RegisterProfileAssociation($profileName, '', '', '', 0, 1, 0, 0, VARIABLETYPE_STRING, $associations);
                    } else {
                        $profileName = '';
                    }

                    // Register mode variable
                    $this->MaintainVariable($variableIdent, $variableName, VARIABLETYPE_STRING, $profileName, 1, true );
                    if ($profileName !== ''){
                        $this->EnableAction($variableIdent);
                    }
                    break;

                case 'Alexa.RangeController':

                    $instanceName = $capability['instance'];
                    if (isset($capability['resources']['friendlyNames'])){
                        $variableName = $this->getFriendlyName($capability['resources']['friendlyNames'], $capability['instance']);
                    } else {
                        $variableName = $capability['instance'];
                    }

                    $variableIdent = $interfaceNameIdent.'_rangeValue_'.$this->sanitizeIdent($capability['instance']);                   

                    // Presets
                    $associations = false;
                    if (isset($capability['configuration']['presets'])){
                        
                        foreach($capability['configuration']['presets'] as $preset){
                            $presetName = $preset['rangeValue'];

                            if (isset($preset['presetResources']['friendlyNames'])){
                                $presetName = $this->getFriendlyName($preset['presetResources']['friendlyNames'], $preset['rangeValue']);
                            }
                            $associations[] = [$preset['rangeValue'], $presetName, '', -1];
                            $associations[] = [$preset['rangeValue']+1, "%d", '', -1];
                        }
                    }

                    $MinValue = 0;
                    $MaxValue = 0;
                    $Stepsize = 1;

                    if (isset($capability['configuration']['supportedRange'])){
                        $MinValue = $capability['configuration']['supportedRange']['minimumValue'];
                        $MaxValue = $capability['configuration']['supportedRange']['maximumValue'];
                        $Stepsize = $capability['configuration']['supportedRange']['precision'];  
                    }

                    $profileName = 'Alexa.RangeController.'.$instanceName.'.'.$this->InstanceID;
                    $this->RegisterProfileAssociation($profileName, '', '', '', $MinValue, $MaxValue, $Stepsize, 0, VARIABLETYPE_INTEGER, $associations);

                    // Register mode variable
                    $this->MaintainVariable($variableIdent, $variableName, VARIABLETYPE_INTEGER, $profileName, 1, true );
                    $this->EnableAction($variableIdent);

                    break;

                case 'Alexa.ToggleController':
                    $instanceName = $capability['instance'];
                    $variableIdent = $interfaceNameIdent.'_toggleState_'.$this->sanitizeIdent($capability['instance']);
                    $variableName = $this->getFriendlyName($capability['resources']['friendlyNames'], $capability['instance']);

                    $this->MaintainVariable($variableIdent, $variableName, VARIABLETYPE_BOOLEAN, '~Switch', 1, true );
                    $this->EnableAction($variableIdent);
                    break;

                case 'Alexa.LockController':
                    $ident = $interfaceNameIdent.'_lockState';
                    $this->MaintainVariable($ident, $this->Translate('lock state'), VARIABLETYPE_BOOLEAN, '~Lock', 1, true );
                    $this->EnableAction($ident);
                    break;

                case 'Alexa.InventoryLevelSensor':

                    $instanceName = $capability['instance'];
                    $variableName = $this->getFriendlyName($capability['resources']['friendlyNames'], $capability['instance']);
                    $variableIdent = $interfaceNameIdent.'_level_'.$this->sanitizeIdent($capability['instance']);                   

                    // Variable Profile
                    switch ($capability['configuration']['measurement']['@type']){
                        case 'Percentage':
                            $profileName = '~Intensity.100';
                            break;

                        default:
                            $profileName = '';
                            break;
                    }

                    $this->MaintainVariable($variableIdent, $variableName, VARIABLETYPE_INTEGER, $profileName, 1, true );

                    break;
            }
        }
    }
    

    public function RequestAction($ident, $value)
    {
        $name = explode('_', $ident)[1];

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
                $instance = $this->getCapabilityInstanceByIdent($ident);
                $this->SetValue($ident, $value);
                $this->SetMode($instance, $value);

                $capability = $this->getCapability('Alexa.ModeController', $instance);

                // Reset variable if device does not report the mode state
                if (!$capability['properties']['retrievable']){
                    $this->SetValue($ident, '');
                }
                break;

            case 'rangeValue':
                $instance = $this->getCapabilityInstanceByIdent($ident);
                $this->SetValue($ident, $value);
                $this->SetRange($instance, $value);

                $capability = $this->getCapability('Alexa.RangeController', $instance);

                // Reset variable if device does not report the mode state
                if (!$capability['properties']['retrievable']){
                    $this->SetValue($ident, '');
                }
                break;

            case 'toggleState':
                $instance = $this->getCapabilityInstanceByIdent($ident);
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
                $namespaceIdent = $this->sanitizeIdent($state['namespace']);

                switch ($state['namespace']){
                    case 'Alexa.EndpointHealth':
                        if ( isset($state['value']['value']) &&  $state['value']['value'] == 'OK' ){
                            $value = true;
                        }else {
                            $value = false;
                        }
                        @$this->SetValue($namespaceIdent.'_connectivity', $value);
                        break;

                    case 'Alexa.PowerController':
                        $value = ($state['value'] == 'ON') ? true : false;
                        @$this->SetValue($namespaceIdent.'_powerState', $value);
                        break;

                    case 'Alexa.PercentageController':
                        $value = intval($state['value']);
                        @$this->SetValue($namespaceIdent.'_percentage', $value);
                        break;

                    case 'Alexa.BrightnessController':
                        $value = intval($state['value']);
                        @$this->SetValue($namespaceIdent.'_brightness', $value);
                        break;

                    case 'Alexa.ColorController':
                        $rgb = $this->hsv2rgb( $state['value']['hue'], $state['value']['saturation']*100, $state['value']['brightness']*100);
                        @$this->SetValue($namespaceIdent.'_color', hexdec( $rgb['hex']));
                        break;

                    case 'Alexa.ColorTemperatureController':
                        $value = intval($state['value']);
                        @$this->SetValue($namespaceIdent.'_colorTemperatureInKelvin', $value);
                        break;

                    case 'Alexa.TemperatureSensor':
                        $value = floatval($state['value']['value']);
                        @$this->SetValue($namespaceIdent.'_temperature', $value);
                        break;

                    case 'Alexa.HumiditySensor':
                        $value = floatval($state['value']['value']);
                        @$this->SetValue($namespaceIdent.'_relativeHumidity', $value);
                        break;

                    case 'Alexa.LightSensor':
                        if ($state['name'] == "illuminance"){
                            $value = intval($state['value']);
                            @$this->SetValue($namespaceIdent.'_illuminance', $value);
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
                            @$this->SetValue($namespaceIdent.'_targetSetpoint', $value);
                        }

                        if ($state['name'] == "thermostatMode"){
                            $value = $state['value'];
                            @$this->SetValue($namespaceIdent.'_thermostatMode', $value);
                        }
                        break;

                    case 'Alexa.ModeController':
                        $ident = $namespaceIdent.'_mode_'.$this->sanitizeIdent($state['instance']);
                        $value = $state['value'];
                        @$this->SetValue($ident, $value);
                        break;

                    case 'Alexa.RangeController':
                        $ident = $namespaceIdent.'_rangeValue_'.$this->sanitizeIdent($state['instance']);
                        $value = $state['value'];
                        @$this->SetValue($ident, $value);
                        break;

                    case 'Alexa.ToggleController':
                        $value = ($state['value'] == 'ON') ? true : false;
                        $ident = $namespaceIdent.'_toggleState_'.$this->sanitizeIdent($state['instance']);
                        @$this->SetValue($ident, $value);
                        break;

                    case 'Alexa.LockController':
                        $value = ($state['value'] == 'LOCKED') ? true : false;
                        @$this->SetValue($namespaceIdent.'_lockState', $value);
                        break;
                        
                    case 'Alexa.InventoryLevelSensor':
                        $ident = $namespaceIdent.'_level_'.$this->sanitizeIdent($state['instance']);
                        $value = intval($state['value']['value']);
                        @$this->SetValue($ident, $value);
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

    private function SetRange( string $instance, int $value )
    {
        $url = '/api/phoenix/state';

        $action = 'setRangeValue';

        $data['controlRequests'][] = [
            'entityId' => $this->getEntityID(),
            'entityType'=> 'ENTITY',
            'parameters' => [
                'action' => $action, 
                'rangeValue' =>[
                    'value' => $value
                ],
                'instance' => $instance
            ]
        ];

        $result = $this->SendCommand( $url, $data , 'PUT');  
        
        return $result;
    }

    private function ToggleState( string $instance, bool $state )
    {
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

    private function getCapability( string $name, string $instance = ''){
        $info = json_decode( $this->ReadAttributeString('DeviceInformation'), true);

        foreach ($info['capabilities'] as $capability ){
            if ($capability['interfaceName'] == $name){

                if ($instance == '' || ( $instance != '' && $capability['instance'] == $instance) ) {
                    return $capability;
                }
            }
        }

        return false;
    }

    private function getCapabilityInstanceByIdent(string $ident){
        $info = json_decode( $this->ReadAttributeString('DeviceInformation'), true);

        $ident = explode('_', $ident);

        if (count($ident)<3) return false;

        $interfaceNameIdent = $ident[0]; 
        $propertyIdent = $ident[1];
        $instanceIdent = $ident[2]; // This is the sanitized instance name

        foreach ($info['capabilities'] as $capability ){
            if ($this->sanitizeIdent($capability['interfaceName']) == $interfaceNameIdent){

                if ( $this->sanitizeIdent($capability['instance']) == $instanceIdent ) {
                    return $capability['instance'];
                }
            }
        }

        return false;
    }

    private function getEntityID()
    {
        return $this->ReadPropertyString('EntityID');
    }

    private function sanitizeIdent($input) {
        // Entfernt alle Zeichen außer Buchstaben, Zahlen und Unterstrich
        return preg_replace('/[^a-zA-Z0-9_]/', '', $input);
    }
    
    private function getFriendlyName(array $friendlyNames,  $default){
        $systemLanguage = str_replace('_', '-', IPS_GetSystemLanguage());

        $name = $this->getFriendlyNameByLanguage($friendlyNames, $systemLanguage );
        if ($name != '') return $name;

        $name = $this->getFriendlyNameByLanguage($friendlyNames, 'en-US');
        if ($name != '') return $name;

        return $default;
    }

    private function getFriendlyNameByLanguage( array $friendlyNames, string $language){

        foreach ($friendlyNames as $friendlyName){
            if ($friendlyName['@type'] == 'text' && strtolower($friendlyName['value']['locale']) == strtolower($language) ){
                return $friendlyName['value']['text'];
            }
        }
        return '';
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
            'onClick' => 'IPS_RequestAction($id, "Internal_UpdateDeviceInformation", "");', 
        ];

        return $elements;
    }


    private function FormActions(): array
    {
        $elements[] = [
            'type'    => 'Button',
            'caption' => 'Update state variables',
            'onClick' => 'IPS_RequestAction($id, "Internal_Update", "");', 
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
