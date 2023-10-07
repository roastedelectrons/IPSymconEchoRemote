<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/EchoBufferHelper.php';
require_once __DIR__ . '/../libs/EchoDebugHelper.php';

class AmazonEchoConfigurator extends IPSModule
{
    use IPSymconEchoRemote\EchoBufferHelper;
    use IPSymconEchoRemote\EchoDebugHelper;
    private const DEVICETYPES = [
        'A2E0SNTXJVT7WK'   => ['name' => 'Fire TV'],
        'A12GXV8XMS007S'   => ['name' => 'Fire TV (2.Gen)'],
        'ADVBD696BHNV5'    => ['name' => 'Fire TV Stick (1.Gen)'],
        'A2GFL5ZMWNE0PX'   => ['name' => 'Fire TV Stick 4K (1.Gen)'],
        'AKPGW064GI9HE'    => ['name' => 'Fire TV Stick 4K'],
        'A3EVMLQTU6WL1W'   => ['name' => 'Fire TV Stick 4K Max'],
        'A2LWARUGJLBYEW'   => ['name' => 'Fire TV Stick (2.Gen)'],
        'A31DTMEEVDDOIV'   => ['name' => 'Fire TV Stick Lite'],
        'A21Z3CGI8UIP0F'   => ['name' => 'Denon&Marantz (HEOS)'],
        'AVE5HX13UR5NO'    => ['name' => 'Zero Touch (Logitech)'],
        'AKOAGQTKAS9YB'    => ['name' => 'Amazon Echo Connect'],
        'A3GZUE7F9MEB4U'   => ['name' => 'Sony WH-100XM3'],
        'A2J0R2SD7G9LPA'   => ['name' => 'Lenovo P10'],
        'A2825NDLA7WDZV'   => ['name' => 'App'],
        'AB72C64C86AW2'    => ['name' => 'Echo'],
        'A3S5BH2HU6VAYF'   => ['name' => 'Echo Dot (2.Gen)'],
        'A32DOYMUN6DTXA'   => ['name' => 'Echo Dot (3.Gen)'],
        'A1RABVCI4QCIKC'   => ['name' => 'Echo Dot (3.Gen)'],
        'A2U21SRK4QGSE1'   => ['name' => 'Echo Dot (4.Gen)'],
        'AILBSA2LNTOYL'    => ['name' => 'Reverb App'],
        'A15ERDAKK5HQQG'   => ['name' => 'Sonos'],
        'A2OSP3UA4VC85F'   => ['name' => 'Sonos One'],
        'A1NL4BVLQ4L3N3'   => ['name' => 'Echo Show'],
        'AWZZ5CVHX2CD'     => ['name' => 'Echo Show (2.Gen)'],
        'A4ZP7ZC4PI6TO'    => ['name' => 'Echo Show 5'],
        'A1XWJRHALS1REP'   => ['name' => 'Echo Show 5 (2.Gen, 2021)'],  
        'A1Z88NGR2BK6A2'   => ['name' => 'Echo Show 8'],
        'A15996VY63BQ2D'   => ['name' => 'Echo Show 8 (2.Gen)'],
        'AIPK7MM90V7TB'    => ['name' => 'Echo Show 10'],   
        'A30YDR2MK8HMRV'   => ['name' => 'Echo Dot (3.Gen with Clock)'],
        'A2H4LV5GIZ1JFT'   => ['name' => 'Echo Dot (4.Gen with Clock)'],
        'A2DS1Q2TPDJ48U'   => ['name' => 'Echo Dot (5.Gen with Clock)'],
        'A4ZXE0RM7LQ7A'    => ['name' => 'Echo Dot (5.Gen)'],
        'A1J16TEDOYCZTN'   => ['name' => 'Amazon Tablet'],
        'A38EHHIB10L47V'   => ['name' => 'Fire HD 8 Tablet'],
        'A112LJ20W14H95'   => ['name' => 'Media Display'],
        'A3L0T0VL9A921N'   => ['name' => 'Tablet'],
        'A3R9S4ZZECZ6YL'   => ['name' => 'Tablet'],
        'A1DL2DVDQVK3Q'    => ['name' => 'App'],
        'A1RTAM01W29CUP'   => ['name' => 'PC App'],
        'A1H0CMF1XM0ZP4'   => ['name' => 'Bose Soundtouch'],
        'A1WAR447VT003J'   => ['name' => 'Yamaha AVR MusicCast'],
        'A3VRME03NAXFUB'   => ['name' => 'Echo Flex'],
        'AAMFMBBEW2960'    => ['name' => 'Garmin DriveSmart 65 with Amazon Alexa'],
        'A10A33FOX2NUBK'   => ['name' => 'Echo Spot'],
        'A7WXQPH584YP'     => ['name' => 'Echo (2.Gen)'],
        'A3FX4UWTP28V1P'   => ['name' => 'Echo (3.Gen)'],
        'A2M35JJZWCQOMZ'   => ['name' => 'Echo Plus'],
        'A18O6U1UQFJ0XK'   => ['name' => 'Echo Plus'],
        'A2IVLV5VM2W81'    => ['name' => 'Mobile Voice iOS'],
        'A2TF17PFR55MTB'   => ['name' => 'Mobile Voice Android'],
        'A1JJ0KFC4ZPNJ3'   => ['name' => 'Echo Input'],
        'A3V3VA38K169FO'   => ['name' => 'Fire Tablet'],
        'A3SSG6GR8UU7SN'   => ['name' => 'Echo Sub'],
        'AP1F6KUH00XPV'    => ['name' => '2.1 Soundsystem 2x Echo Stereo and Subwoofer'],
        'AVD3HM0HOJAAL'    => ['name' => 'Sonos One'],
        'A2JKHJ0PX4J3L3'   => ['name' => 'Fire TV Cube'],
        'A2M4YX06LWP8WI'   => ['name' => 'Fire 7 Tablet'],
        'A1C66CX2XD756O'   => ['name' => 'Fire HD 8 Tablet'],
        'A17LGWINFBUTZZ'   => ['name' => 'Anker Roav Car Charger'],
        'A2XPGY5LRKB9BE'   => ['name' => 'FitBit watch'],
        'A3NPD82ABCPIDP'   => ['name' => 'Sonos Beam'],
        'A2Y04QPFCANLPQ'   => ['name' => 'Bose QC35 II'],
        'A3BW5ZVFHRCQPO'   => ['name' => 'Alexa Car'],
        'A3C9PE6TNYLTCH'   => ['name' => 'Multiroom Music-Group'],
        'A303PJF6ISQ7IC'   => ['name' => 'Echo Auto'],
        'A1ZB65LA390I4K'   => ['name' => 'Fire HD 10 Tablet'],
        'AVU7CPPF2ZRAS'    => ['name' => 'Fire HD 8 Plus (2020)'],
        'A24Z7PEXY4MDTK'   => ['name' => 'Sony WF-1000X'],
        'ABN8JEI7OQF61'    => ['name' => 'Sony WF-1000XM3'],
        'A7S41FQ5TWBC9'    => ['name' => 'Sony WH-1000XM4'],
        'A265XOI9586NML'   => ['name' => 'Fire TV Stick with Alexa voice remote control (with TV control buttons)'],
        'AKKLQD9FZWWQS'    => ['name' => 'Jabra Elite 75t'],
        'A3RMGO6LYLH7YN'   => ['name' => 'Echo (4.Gen)'],
        'A38949IHXHRQ5P'   => ['name' => 'Amazon Tap'],
        'A3RBAYBE7VM004'   => ['name' => 'Echo Studio'],
        'ATH4K2BAIXVHQ'    => ['name' => 'Amazon Alexa App Android'],
        'A27VEYGQBW3YR5'   => ['name' => 'Echo Link'],
        'AO50AHDYKXRFG'    => ['name' => 'Bose Noise Cancelling Headphones 700'],
        'A1Q7QCGNMXAKYW'   => ['name' => 'Fire 7 Tablet'],
        'A17KNHDVUO2UVP'   => ['name' => 'Audi Alexa Integration'],
        'A93SQJNJQLDSS'    => ['name' => 'Grundig Fire TV '],
        'A1OECNXBQCC1P9'   => ['name' => 'Samsung Tizen'],
        'AOL0WUO16289B'    => ['name' => 'Teufel Holist S'],
        'AF473ZSOIRKFJ'    => ['name' => 'Onkyo VC-PX30'],
	    'A3QS1XP2U6UJX9'   => ['name' => 'Sony WF-1000XM4']
    ];

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //the following Properties can be set in the configuration form
        $this->RegisterPropertyInteger('targetCategoryID', 0);

        // initiate buffer
        $this->SetBuffer($this->InstanceID . '-alexa_devices', '');
        $this->ConnectParent('{C7F853A4-60D2-99CD-A198-2C9025E2E312}');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->RegisterReference($this->ReadPropertyInteger('targetCategoryID'));
    }

    /**
     * Interne Funktion des SDK.
     *
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function GetConfigurationForm(): string
    {
        $Form['elements'][] = [
            'type'    => 'SelectCategory',
            'name'    => 'targetCategoryID',
            'value'   => 0,
            'caption' => 'Target Category'];
        $Form['actions'][] = [
            'type'     => 'Configurator',
            'name'     => 'AmazonEchoConfiguration',
            'rowCount' => 15,
            'add'      => false,
            'delete'   => true,
            'sort'     => [
                'column'    => 'name',
                'direction' => 'ascending'],
            'columns'  => [
                ['caption' => 'device name', 'name' => 'name', 'width' => 'auto'],
                ['caption' => 'device type', 'name' => 'devicetype', 'width' => '200px'],
                ['caption' => 'device family', 'name' => 'devicefamily', 'width' => '200px'],
                ['caption' => 'device number', 'name' => 'devicenumber', 'width' => '200px'],
                ['caption' => 'device account id', 'name' => 'deviceaccountid', 'width' => '200px']],
            'values'   => $this->Get_ListConfiguration()];

        $jsonForm = json_encode($Form);
        $this->SendDebug('FORM', $jsonForm, 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);

        return $jsonForm;
    }


    protected function RegisterReference($ID)
    {
        if (method_exists('IPSModule', 'RegisterReference ')) {
            parent::RegisterReference($ID);
        }
    }

    /** Get Config Echo
     *
     * @return array
     */
    private function Get_ListConfiguration(): array
    {

        // Get all Echo Remote Device Instances conntected to this parent

        $EchoRemoteInstanceList = [];
        foreach (IPS_GetInstanceListByModuleID('{496AB8B5-396A-40E4-AF41-32F4C48AC90D}') as $instanceID)  // Echo Remote Devices
        {
            if (IPS_GetInstance($instanceID)['ConnectionID'] === IPS_GetInstance($this->InstanceID)['ConnectionID']) 
            {
                // Add instance configuration for existing instances to list  
                $EchoRemoteInstanceList[] = [
                    'instanceID'    => $instanceID,
                    'name'          => IPS_GetName($instanceID),
                    'devicetype'    => IPS_GetProperty($instanceID, 'Devicetype'),
                    'devicenumber'  => IPS_GetProperty($instanceID, 'Devicenumber'),
                    'devicefamily'  => '',  
                    'deviceaccountid' => ''
                ];
            }
        }

        // Get all Echo Devices conntected to this amazon account
        $devices = array();

        $devices_info = $this->SendDataPacket('GetDevices');

        if ($devices_info['http_code'] === 200) {
            $devices_JSON = $devices_info['body'];
            $this->SendDebug('Response IO:', $devices_JSON, 0);
            $this->SetBuffer($this->InstanceID . '-alexa_devices', $devices_JSON);
            if ($devices_JSON) {
                $devices = json_decode($devices_JSON, true);
                $this->SendDebug('Echo Devices:', json_encode($devices), 0);
            }
        }


        // Add devices from amazon account to config list
        $config_list = [];

        foreach ($devices as $key => $device) {
            $instanceID = 0;

            $accountName = $device['accountName'];
            $this->SendDebug('Echo Device', 'account name: ' . $accountName, 0);

            $deviceAccountId = $device['deviceAccountId'];
            $this->SendDebug('Echo Device', 'device account id: ' . $deviceAccountId, 0);

            $deviceFamily = $device['deviceFamily'];
            $this->SendDebug('Echo Device', 'device family: ' . $deviceFamily, 0);

            $deviceType = $device['deviceType'];
            if (array_key_exists($deviceType, self::DEVICETYPES)) {
                $device_type_name = $this->Translate(self::DEVICETYPES[$deviceType]['name']);
            } else {
                $device_type_name = 'unknown: ' . $deviceType;
                $this->SendDebug('Unknown DeviceType', $deviceType, 0);
            }
            $this->SendDebug('Echo Device', 'device type: ' . $deviceType . ', device type name: ' . $device_type_name, 0);

            $serialNumber = $device['serialNumber'];
            $this->SendDebug('Echo Device', 'serial number: ' . $serialNumber, 0);

            $config_list[] = [
                'instanceID'      => $instanceID,
                'name'            => $accountName,
                'devicetype'      => $device_type_name,
                'devicefamily'    => $this->Translate($deviceFamily),
                'devicenumber'    => $serialNumber,
                'deviceaccountid' => $deviceAccountId,
                'create'          => [
                    'moduleID'      => '{496AB8B5-396A-40E4-AF41-32F4C48AC90D}',
                    'configuration' => [
                        'Devicetype'   => $deviceType,
                        'Devicenumber' => $serialNumber],
                    'location'      => $this->getPathOfCategory($this->ReadPropertyInteger('targetCategoryID'))]];
        }

        // Add existing echo remote device instances to config list

        foreach ($EchoRemoteInstanceList as $EchoRemoteInstance) 
        {
            $exists = false;
            foreach ($config_list as $key => $device) 
            {
                if ( $EchoRemoteInstance['devicenumber'] ===  $device['devicenumber'] )
                {
                    $id = $device['instanceID'];
                    $device['instanceID'] = $EchoRemoteInstance['instanceID'];
                    $device['name'] = $EchoRemoteInstance['name'];

                    if ( $id == 0 )
                    {
                        $config_list[$key] = $device;
                    }
                    else
                    {
                        // If more than one instance with the same topic/serial exist, add a new device to list
                        $config_list[] = $device;
                    }
                    $exists = true;
                    break;
                }
            }

            // If existing instance is not found in amazon account, add it as erroneous to config list (i.e. it has no key 'create' an will be shown red in the configurator)
            if ( !$exists)
            {
                $config_list[] = $EchoRemoteInstance;
            }

        }

        return $config_list;
    }

    private function getPathOfCategory(int $categoryId): array
    {
        if ($categoryId === 0) {
            return [];
        }

        if ( !IPS_CategoryExists($categoryId) )
        {
            return [];
        }

        $path[] = IPS_GetName($categoryId);
        $parentId = IPS_GetObject($categoryId)['ParentID'];

        while ($parentId > 0) {
            $path[] = IPS_GetName($parentId);
            $parentId = IPS_GetObject($parentId)['ParentID'];
        }

        return array_reverse($path);
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
            $this->SendDebug(__FUNCTION__, 'Result: ' . json_encode($ResultJSON), 0);

            $ret = json_decode($ResultJSON, true);
            if ($ret) {
                return $ret; //returns an array of http_code, body and header
            }
        }

        $this->SendDebug( __FUNCTION__, sprintf(
                                               '\'%s\' (#%s): SendDataToParent returned with %s. $Data = %s', IPS_GetName($this->InstanceID),
                                               $this->InstanceID, json_encode($ResultJSON), json_encode($Data)
                                        ), 0
        );

        return ['http_code' => 502, 'header' => '', 'body' => ''];        
    }
}
