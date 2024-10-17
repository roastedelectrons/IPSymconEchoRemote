<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/EchoBufferHelper.php';
require_once __DIR__ . '/../libs/EchoDebugHelper.php';
require_once __DIR__ . '/../libs/AlexaSmartHome.php';

class AlexaSmartHomeDeviceConfigurator extends IPSModule
{
    use IPSymconEchoRemote\EchoBufferHelper;
    use IPSymconEchoRemote\EchoDebugHelper;
    use IPSymconEchoRemote\AlexaSmartHome;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        // initiate buffer
        //$this->SetBuffer($this->InstanceID . '-smarthome-devices', '');
        $this->ConnectParent('{C7F853A4-60D2-99CD-A198-2C9025E2E312}');

        $this->RegisterPropertyBoolean('filterDuplicates', true);
        $this->RegisterPropertyBoolean('filterUnsupported', true);

    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

    }

    public function GetConfigurationForm(): string
    {

        $Form['elements'][] = [
            'type' => 'CheckBox',
            'name' => 'filterDuplicates',
            'caption' => 'Filter duplicates'
        ];

        $Form['elements'][] = [
            'type' => 'CheckBox',
            'name' => 'filterUnsupported',
            'caption' => 'Filter unsupported devices'
        ];

        $Form['actions'][] = [
            'type'     => 'Configurator',
            'name'     => 'SmartHomeDeviceConfiguration',
            'discoveryInterval' => 86400,
            'rowCount' => 15,
            'add'      => false,
            'delete'   => true,
            'sort'     => [
                'column'    => 'name',
                'direction' => 'ascending'],
            'columns'  => [
                ['caption' => 'Name', 'name' => 'name', 'width' => 'auto'],
                ['caption' => 'Description', 'name' => 'description', 'width' => '250px'],
                ['caption' => 'Manufacturer', 'name' => 'manufacturer', 'width' => '250px'],
                ['caption' => 'Type', 'name' => 'type', 'width' => '150px'],
                ['caption' => 'Connection', 'name' => 'connection', 'width' => '150px'],
                ['caption' => 'EntityID', 'name' => 'entityID', 'width' => '150px']],
            'values'   => $this->getConfiguratorList()];

        $jsonForm = json_encode($Form);

        return $jsonForm;
    }



    private function getConfiguratorList()
    {
        $deviceList = $this->getSmartHomeDeviceList($this->ReadPropertyBoolean('filterDuplicates'));

        $devices = [];

        foreach($deviceList as $applianceDetails){
            $device = [];
            $device['instanceID'] = 0;
            $device['name'] = $applianceDetails['friendlyName'];
            $device['description'] = $applianceDetails['friendlyDescription'];
            $device['manufacturer'] = $applianceDetails['manufacturerName'];
            $device['type'] = $applianceDetails['applianceTypes'];
            $device['connection'] = $this->getDeviceConnection($applianceDetails);
            $device['entityID'] = $applianceDetails['entityId'];
            $device['capabilities'] = [];
            foreach($applianceDetails['capabilities'] as $capability){
                $device['capabilities'][] = $capability['interfaceName'];
            }

            $device['create'] = [
                'moduleID' => '{5C3C56EA-DF4C-4BC3-76C5-1EC8DC479CB5}',
                'configuration' => ['EntityID' => $applianceDetails['entityId']]
            ];
            $devices[] = $device;
        }

        // Get for existing instances
        $instanceList = [];
        foreach (IPS_GetInstanceListByModuleID('{5C3C56EA-DF4C-4BC3-76C5-1EC8DC479CB5}') as $instanceID)  // AlexaSmartHomeDevice
        {
            if (IPS_GetInstance($instanceID)['ConnectionID'] === IPS_GetInstance($this->InstanceID)['ConnectionID']) 
            {
                $instanceList[] = [
                    'instanceID'    => $instanceID,
                    'entityID'      => IPS_GetProperty($instanceID, 'EntityID'),
                    'name'          => IPS_GetName($instanceID),  
                    'description'   => '',  
                    'manufacturer'  => '',  
                    'type'          => '',  
                    'capabilities'  => []
                ];
            }
        }


        // Merge instances and entites
        foreach ($instanceList as $instance) 
        {
            $exists = false;
            foreach ($devices as $key => $device) 
            {
                if ( $instance['entityID'] ===  $device['entityID'] && isset($device['create']))
                {
                    $id = $device['instanceID'];
                    $device['instanceID'] = $instance['instanceID'];

                    if ( $id == 0 )
                    {
                        $devices[$key] = $device;
                    }
                    else
                    {
                        // If more than one instance with the same topic/serial exist, add a new device to list
                        $devices[] = $device;
                    }
                    $exists = true;
                    break;
                }
            }

            // If existing instance is not found in amazon account, add it as erroneous to config list (i.e. it has no key 'create' an will be shown red in the configurator)
            if ( !$exists)
            {
                $devices[] = $instance;
            }

        }


        // Filter unsupported devices
        if ($this->ReadPropertyBoolean('filterUnsupported')){
            $supportedDevices = [];

            foreach($devices as $index => $device){
                $supportedCapabilites = [];
                foreach($device['capabilities'] as $capability){
                    if ($this->isCapabilitySupported($capability)){
                        $supportedCapabilites[] = $capability;
                    }
                }

                if ($supportedCapabilites != [] || $device['instanceID'] != 0){ // always show devices with existing instance
                    $device['capabilities'] = '';
                    $supportedDevices[] = $device;
                }
            }

            unset($devices);
            $devices = $supportedDevices;
            unset($supportedDevices);
        }


        return $devices;
    }

}
