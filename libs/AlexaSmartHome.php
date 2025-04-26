<?php
namespace IPSymconEchoRemote;

trait AlexaSmartHome
{
    private function isCapabilitySupported( string $capability)
    {
        $supportedCapabilities = [
            'Alexa.EndpointHealth',
            'Alexa.PowerController',
            'Alexa.PercentageController',
            'Alexa.BrightnessController',
            'Alexa.ColorController',
            'Alexa.ColorTemperatureController',
            'Alexa.TemperatureSensor',
            'Alexa.HumiditySensor',
            'Alexa.LightSensor',
            'Alexa.ThermostatController',
            'Alexa.SceneController',
            'Alexa.ModeController',
            'Alexa.RangeController',
            'Alexa.ToggleController',
            'Alexa.LockController',
            'Alexa.InventoryLevelSensor'
        ];

        return in_array($capability, $supportedCapabilities);
    }


    private function getSmartHomeDeviceList( bool $filterDuplicates = true)
    {

        $devices = array();

        $result = $this->getSmartHomeDevices();

        if (!isset($result['networkDetail']))
            return [];

        $networkDetail = json_decode($result['networkDetail'], true);

        if (! isset($networkDetail['locationDetails']['locationDetails']['Default_Location']['amazonBridgeDetails']['amazonBridgeDetails']))
            return [];

        foreach ( $networkDetail['locationDetails']['locationDetails']['Default_Location']['amazonBridgeDetails']['amazonBridgeDetails'] as $key => $amazonBridgeDetails){

            foreach($amazonBridgeDetails['applianceDetails']['applianceDetails'] as $applianceDetails){
                $devices[] = $applianceDetails;
            }
        }

        // Filter duplicates
        if ($filterDuplicates){
            $entityIDList =  array_unique(array_column($devices, 'entityId'));
            $uniqueDevices = array();
    
            foreach($entityIDList as $entityID){
                $device = $this->filterSmartHomeDeviceByEntityID($entityID, $devices);
                if ($device != array()){
                    $uniqueDevices[] = $device;
                }
            }
    
            return $uniqueDevices;
        }

        return $devices;
    }

    private function filterSmartHomeDeviceByEntityID( string $entityID, array $deviceList)
    {
        $devices = [];

        foreach( $deviceList as $device){
            if ( $device['entityId'] == $entityID){
                $devices[] = $device;
            }
        }

        if (count($devices) == 0){
            return [];
        }

        // Workaround for devices with multiple integrations, like Phillips HUE, AlexaBridges (Echo)
        if (count($devices) > 1){
            // return integration via Skill (prefered)
            foreach($devices as $entry){
                if ( stripos( $entry['applianceId'], 'SKILL') === 0){
                    return $entry;
                }
            }

            // otherwise return integration via Amazon SonarCloudService 
            foreach($devices as $entry){
                if (stripos( $entry['applianceId'], 'AAA') === 0){
                    return $entry;
                }
            }
        }

        return $devices[0];      
    }

    private function getDeviceConnection( $device )
    {
        $connection = '';

        if (isset($device['applianceDriverIdentity']['namespace'])){
            $connection = $device['applianceDriverIdentity']['namespace'];
        }
        if (isset($device['connectedVia']) && $device['connectedVia'] != '' ){
            $connection = 'via '.$device['connectedVia'];
        }
        if ($connection == "AAA"){
            $connection = "via Echo Hub";
        }

        return $connection;
    }

    private function getSmartHomeEntities()
    {
        $url = '/api/behaviors/entities?skillId=amzn1.ask.1p.smarthome';

        $data = [];

        $result = $this->SendCommand( $url, $data , 'GET');  
        
        return $result;        
    }

    private function getSmartHomeDevices()
    {
        //$url = '/api/phoenix?includeRelationships=true';
        $url = '/api/phoenix';

        $data = [];

        $result = $this->SendCommand( $url, $data , 'GET');  
        
        return $result;        
    }

    private function getSmarthomeBehaviourActionDefinitions()
    {
        $url = '/api/behaviors/actionDefinitions?skillId=amzn1.ask.1p.smarthome';

        $data = [];

        $result = $this->SendCommand( $url, $data , 'GET');  
        
        return $result;        
    }

    private function SendCommand( string $url, array $data, string $method)
    {
        $payload['url'] =  $url;
        $payload['postfields'] = $data;
        $payload['method'] =  $method;

        $result = $this->SendDataPacket('AlexaApiRequest', $payload);

        if (isset($result['http_code']) && ($result['http_code'] === 200)) {
            return json_decode($result['body'], true);
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
}