<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/EchoBufferHelper.php';
require_once __DIR__ . '/../libs/EchoDebugHelper.php';
require_once __DIR__ . '/../libs/VariableProfileHelper.php';


class EchoBot extends IPSModule
{
    use IPSymconEchoRemote\EchoBufferHelper;
    use IPSymconEchoRemote\EchoDebugHelper;
    use IPSymconEchoRemote\VariableProfileHelper;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        $this->RegisterPropertyBoolean('Active', true);
        $this->RegisterPropertyString('AutomationID', '');
        $this->RegisterPropertyInteger('ActionType', 0);
        $this->RegisterPropertyString('TTSList', '');
        $this->RegisterPropertyString('TTSScript', "<?php\n// Note:  \n//  - always return the text message as string!\n//  - information about the last activity is available in \$_IPS \n\n\$text = \"This is my answer\";\n\nreturn \$text;");
        $this->RegisterPropertyInteger('ScriptID', 0);
        $this->RegisterPropertyString('ActionList', '');

        $this->RegisterAttributeString('Automations', '');
        $this->RegisterAttributeString('LastActivity', '');

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

        if ($this->ReadPropertyBoolean('Active')){
            $this->SetStatus(IS_ACTIVE);
        } else {
            $this->SetStatus(IS_INACTIVE);
        }

        //Apply filter
        $this->SetReceiveDataFilter('.*(LastAction|Automations).*');
    }

    public function Migrate($JSONData) {
        $config = json_decode($JSONData, true);

        // Beta migrations 2024-04-09
        if (isset($config['configuration']['Script'])){
            $config['configuration']['TTSScript'] = $config['configuration']['Script'];
        }

        if (isset($config['configuration']['Text1']) && $config['configuration']['Text1'] != ''){
            $config['configuration']['TTSList'] = [
                [
                'Device' => 'ALL_DEVICES',
                'Text_1' => $config['configuration']['Text1'],
                'Text_2' => $config['configuration']['Text2'],
                'VariableID_1' => $config['configuration']['VariableID1']
                ]
            ];
        }

        return json_encode($config);
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

    public function RequestAction($ident, $value)
    {
        switch ($ident) {
            case 'TestAction':
                $value = explode('#', $value);
                $lastActivity['timestamp'] = microtime(true);
                $lastActivity['timestampMilliseconds'] = round($lastActivity['timestamp']*1000, 0);
                $lastActivity['deviceType'] = $value[0];
                $lastActivity['serialNumber'] = $value[1];
                $lastActivity['id'] = $lastActivity['deviceType'].'#'.$lastActivity['serialNumber'].'#'.$lastActivity['timestampMilliseconds'];
                $lastActivity['deviceName'] = $this->GetDeviceName($lastActivity['serialNumber'], $lastActivity['deviceType'] );
                $lastActivity['utteranceType'] = 'GENERAL';
                $lastActivity['domain'] = 'Routines';
                $lastActivity['intent'] = 'InvokeRoutineIntent';
                $lastActivity['utterance'] = $this->GetUtteranceByAutomationId($this->ReadPropertyString('AutomationID'))[0];
                $lastActivity['response'] = '';
                $lastActivity['person'] = '';
                $lastActivity['instanceID'] = $this->GetInstanceIDBySerialNumber($lastActivity['serialNumber'], $lastActivity['deviceType']);
                $this->RunAction($lastActivity);
                break;

            case 'AutomationID':
                $this->UpdateFormField('Utterances', 'caption', $this->GetUtterancesForLabel($value) ) ;
                break;

            case 'ActionType':
                $this->UpdateFormField('ActionType_0', 'visible', $value == 0) ;
                $this->UpdateFormField('ActionType_1', 'visible', $value == 1) ;
                $this->UpdateFormField('ActionType_2', 'visible', $value == 2) ;
                $this->UpdateFormField('ActionType_3', 'visible', $value == 3) ;
                break;

            case 'RefreshAutomations':
                $this->UpdateAutomations();
                $this->UpdateFormField('AutomationID', 'options', json_encode($this->GetAutomationsForSelect() )) ;
                $this->UpdateFormField('Utterances', 'caption', $this->GetUtterancesForLabel($value) ) ;
                break;
        }
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString, true);
        $payload = $data['Payload'];
        $this->SendDebug('Receive Data', $JSONString, 0);

        
        switch ($data['Type'])
        {            
            case 'LastAction':
                
                $utterances = $this->GetUtteranceByAutomationId($this->ReadPropertyString('AutomationID'));

                $match = false;
                foreach($utterances as $utterance){
                    if ( stripos( $payload['utterance'], $utterance ) !== false){
                        $match = true;
                        break;
                    }
                }

                if ($match === false) return;

                $this->LogMessage($payload['deviceName'].': '.$payload['utterance'], KL_NOTIFY);
                $this->WriteAttributeString('LastActivity', json_encode($payload) ); 

                if ($this->GetStatus() !== IS_ACTIVE) return;

                $this->RunAction($payload);

                break;

            case 'Automations':
                if ($this->ReadAttributeString('Automations') != $payload )
                {
                    $this->WriteAttributeString('Automations', $payload );  
                }
                break;
        }

    }

    private function RunTextToSpeechScript($activity)
    {
        $script = $this->ReadPropertyString('TTSScript');
        $script = str_replace('<?php', '', $script);
        $script = str_replace('?>', '', $script);
        $_IPS = $activity;
        try {
            $text = @eval($script);
        } catch (ParseError $error) {
            $this->LogMessage('Error in text-to-speech script: ' . $error->getMessage() . ' on line '. $error->getLine(), KL_ERROR);
            $this->SetStatus(201);
            return false;
        }
        
        if ( $text !== null ){
            $this->TextToSpeech($text, $activity['serialNumber'] , $activity['deviceType'] );
        } else {
            $this->LogMessage('Error in text-to-speech script: return value missing.', KL_ERROR);
            $this->SetStatus(201);                            
        }

    }

    private function RunAction( $activity )
    {
        switch ($this->ReadPropertyInteger('ActionType'))
        {
            case 0:
                $this->RunTTSResponse($activity);
                break;

            case 1:
                $this->RunTextToSpeechScript($activity);
                break;

            case 2:
                if (IPS_ScriptExists( $this->ReadPropertyInteger('ScriptID') ) ){
                    IPS_RunScriptEx($this->ReadPropertyInteger('ScriptID'), $activity);
                }
                break;

            case 3:
                $this->RunActionList($activity);
                break;
        }
    }
    

    private function RunActionList($lastActivity)
    {
        $list = json_decode($this->ReadPropertyString('ActionList'), true);
        foreach ($list as $action){ 
           if ($lastActivity['deviceType'].'#'.$lastActivity['serialNumber'] == $action['Device']  || $action['Device'] == 'ALL_DEVICES') { 
               $action['Action'] = json_decode($action['Action'], true);
               $parameters = array_merge($lastActivity, $action['Action']['parameters']);
               IPS_RunAction($action['Action']['actionID'], $parameters); 
            } 
        } 
     
    }

    private function RunTTSResponse($lastActivity)
    {
        $list = json_decode($this->ReadPropertyString('TTSList'), true);
        foreach ($list as $listItem){ 
           if ($lastActivity['deviceType'].'#'.$lastActivity['serialNumber'] == $listItem['Device']  || $listItem['Device'] == 'ALL_DEVICES') { 
                $text = $this->CreateTTSResponse($listItem);
                $this->TextToSpeech($text, $lastActivity['serialNumber'] , $lastActivity['deviceType'] );
            } 
        } 
     
    }

    private function CreateTTSResponse( array $content)
    {
        $text[] = $content['Text_1'];
        if (IPS_VariableExists( (int) $content['VariableID_1']) ) {
            $text[] = ' '.GetValueFormatted($content['VariableID_1']);
        }
        $text[] = $content['Text_2'];
        
        return implode(' ', $text );
    }

    private function GetCustomerID()
    {
        return  $this->SendDataPacket( 'GetCustomerID' );
    }

    /** TextToSpeechEx
     *
     * @param string $tts
     * @param string $instanceIDList
     * @return array|string
     */
    private function TextToSpeech(string $tts, string $deviceSerial, string $deviceType, array $options = [] ): bool
    {
        $device = [
            'deviceSerialNumber'    => $deviceSerial,
            'deviceType'          => $deviceType                
        ];               

        $targetDevices[] = $device;

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

     /** Get all automations
     *
     * @return array
     */
    public function GetAllAutomations()
    {
        $automations = json_decode( $this->ReadAttributeString('Automations'), true );

        if ( !is_array($automations) )
        {
            $automations = $this->UpdateAutomations();
        }

        return $automations;
    }

    private function GetDeviceList()
    {
        $result = $this->SendDataPacket('GetDeviceList');

        if (isset($result['http_code']) && $result['http_code'] != 200) return [];

        return $result;
    }

    private function GetDevicesForSelect()
    {
        $devices = $this->GetDeviceList();

        $list[] = array('caption' => 'All Devices', 'value' => 'ALL_DEVICES');

        foreach( $devices as $device){
            if ( in_array( $device['deviceFamily'], array('ECHO', 'KNIGHT', 'ROOK', 'TABLET') ) ) {
                $list[] = [
                    'caption' => $device['accountName'], 
                    'value' => $device['deviceType'].'#'.$device['serialNumber']
                ];
            }
        }

        return $list;
    }

    private function GetDeviceName($serialNumber, $deviceType)
    {
        $devices = $this->GetDeviceList();

        foreach( $devices as $device){
            if ( $device['serialNumber'] == $serialNumber && $device['deviceType'] == $deviceType ) {
                return $device['accountName'];
            }
        }

        return 'Unknown Device';
    }

    private function GetInstanceIDBySerialNumber( $serialNumber, $deviceType)
    {
        foreach (IPS_GetInstanceListByModuleID('{496AB8B5-396A-40E4-AF41-32F4C48AC90D}') as $instanceID)  // Echo Remote Devices
        {
            if (IPS_GetInstance($instanceID)['ConnectionID'] === IPS_GetInstance($this->InstanceID)['ConnectionID']) {
                if (IPS_GetProperty($instanceID, 'Devicetype') == $deviceType && IPS_GetProperty($instanceID, 'Devicenumber') && $serialNumber ) {
                    return $instanceID;
                }
            }
        }
        return 0;
    }

    public function UpdateAutomations()
    {
        $payload['url'] = '/api/behaviors/v2/automations';

        $result = (array) $this->SendDataPacket('UpdateAutomations', $payload);

        if ($result['http_code'] !== 200) {
            return [];
        }
        
        $automations = json_decode($result['body'], true);

        if (!is_array($automations)) {
            return [];
        }

        $this->WriteAttributeString('Automations', $result['body']);

        return $automations;
    }


    private function GetAutomationList()
    {
        $automations = $this->GetAllAutomations();

        $list = array();

        foreach ($automations as $automation) {
            foreach ($automation['triggers'] as $trigger) {
                if ($trigger['type'] == 'CustomUtterance') {
                    $entry = array();
                    $utterances = array();
                    if (isset($trigger['payload']['utterances'])) {
                        $utterances = $trigger['payload']['utterances'];
                    } else {
                        $utterances[] = $trigger['payload']['utterance'];
                    }
                    $entry['name'] = $automation['name'];
                    $entry['automationId'] = $automation['automationId'];
                    $entry['utterances'] = $utterances;
                    $list[] = $entry;
                }
            }
        }

        return $list;
    }

    private function GetAutomationsForSelect()
    {
        $list = $this->GetAutomationList();
        $selectList[] = array(
            'caption' => $this->Translate('Select routine'),
            'value'   => ''
        );

        foreach($list as $entry){
            $selectList[] = [
                'caption' => $entry['name'],
                'value' => $entry['automationId'],
            ];
        }

        return $selectList;
    }

    private function GetUtteranceByAutomationId( $automationId)
    {
        $automations = $this->GetAutomationList();

        foreach($automations as $automation){
            if ($automation['automationId'] == $automationId) {
                return $automation['utterances'];
            }
        }

        return [];
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


    private function GetUtterancesForLabel($automationID)
    {
        return "    Alexa, ". implode( "\n    Alexa, ", $this->GetUtteranceByAutomationId( $automationID ));
    }

    public function GetSystemVariables()
    {
        $systemVariables = [
            'id',
            'timestamp',
            'timestampMilliseconds',
            'deviceType',
            'serialNumber',
            'deviceName',
            'utteranceType',
            'domain',
            'intent',
            'utterance',
            'response',
            'person',
            'instanceID'
        ];
        $string = "    \$_IPS['";
        $string .= implode("']\n    \$_IPS['", $systemVariables);
        $string .= "']";

        return $string;

    }

    private function GetLastActivityInformation()
    {
        $lastActivity = json_decode($this->ReadAttributeString('LastActivity'), true);
        $text = "";
        if  ($lastActivity != false){
            $text .= "    ".$this->Translate("Device").":    \t\t".$lastActivity['deviceName']."\n";
            $text .= "    ".$this->Translate("Utterance").": \t\t".$lastActivity['utterance']."\n";
            $text .= "    ".$this->Translate("Time").":      \t\t". date('Y-m-d H:i', intval($lastActivity['timestamp'])) ."\n";
        } else {
            $text .= "    ".$this->Translate("no information available yet");
        }
        return $text;
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
        $actionType = $this->ReadPropertyInteger('ActionType');

        $elements[] = [
            'type'    => 'CheckBox',
            'name'    => 'Active',
            'caption' => "Active"
        ];

        $elements[] = [
            'type'    => 'Label',
            'bold'    => true,
            'caption' => "If this routine is started:"
        ];

        $elements[] = [
            'type' => 'RowLayout',
            'items' => [
                [
                'name'    => 'AutomationID',
                'type'    => 'Select',
                'caption' => 'Routine name',
                'onChange' => 'IPS_RequestAction($id, "AutomationID", $AutomationID);',
                'width'    => '500px',
                'options' => $this->GetAutomationsForSelect()
                ],
                [
                'type'    => 'Button',
                'caption' => 'Refresh',
                'onClick' => 'IPS_RequestAction($id, "RefreshAutomations", $AutomationID);', 
                ]
            ]
        ];

        $elements[] = [
            'type'    => 'Label',
            'italic' => false,
            'color' => 7566195,
            'caption' => "Trigger:"
        ];

        $elements[] = [
            'name'    => 'Utterances',
            'type'    => 'Label',
            'color' => 7566195,
            'italic' => true,
            'caption' => $this->GetUtterancesForLabel($this->ReadPropertyString('AutomationID'))
        ];
        
        $elements[] = [
            'type'    => 'Label',
            'caption' => ""
        ];

        $elements[] = [
            'type'    => 'Label',
            'bold'    => true,
            'caption' => "Then perform the following action:"
        ];

        $elements[] = [
            'name'    => 'ActionType',
            'type'    => 'Select',
            'caption' => 'Action type',
            'onChange' => 'IPS_RequestAction($id, "ActionType", $ActionType);',
            'width'    => '500px',
            'options' => [
                [ 'caption' => 'Text-to-speech response (simple)', 'value' => 0],
                [ 'caption' => 'Text-to-speech response (extended)', 'value' => 1],
                [ 'caption' => 'Run script', 'value' => 2],
                [ 'caption' => 'Run action (depending on addressed device)', 'value' => 3]
            ]
        ];

        $values = json_decode($this->ReadPropertyString('TTSList'), true);

        if ($values == false) $values = array();

        foreach($values as $index => $item){
            $values[$index]['Response'] = $this->CreateTTSResponse($item);
        }

        $elements[] = [
            'type' => 'ColumnLayout',
            'name' => 'ActionType_0',
            'visible' => $actionType == 0,
            'items' => [
                [
                    'name' => 'TTSList',
                    'type' => 'List',
                    'add'  => true,
                    'delete' => true,
                    'loadValuesFromConfiguration' => false,
                    'columns' => [
                        [
                            'name' => 'Device',
                            'caption' => 'Addressed Device',
                            'width' => '300px',
                            'add' => '',
                            'edit' => [
                                'type' => 'Select',
                                'options' => $this->GetDevicesForSelect()
                            ]
                        ],
                        [
                            'name' => 'Response',
                            'caption' => 'Response (with current variable value)',
                            'width' => 'auto',
                            'add' => '(save changes to show response)'
                        ],                        
                        [
                            'name' => 'Text_1',
                            'caption' => 'Text',
                            'visible' => false,
                            'width' => '0px',
                            'add' => '',
                            'edit' => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'name' => 'VariableID_1',
                            'caption' => 'Variable',
                            'visible' => false,
                            'width' => '0px',
                            'add' => '',
                            'edit' => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'name' => 'Text_2',
                            'caption' => 'Text',
                            'visible' => false,
                            'width' => '0px',
                            'add' => '',
                            'edit' => [
                                'type' => 'ValidationTextBox'
                            ]
                        ]
                    ],
                    'values' => $values

                ]
            ]
        ];


        $elements[] = [
            'type' => 'ColumnLayout',
            'name' => 'ActionType_1',
            'visible' => $actionType == 1,
            'items' => [
                [
                'name'    => 'TTSScript',
                'type'    => 'ScriptEditor',
                'caption' => 'Script',
                'rowCount' => '10'
                ]
            ]
        ];

        $elements[] = [
            'type' => 'ColumnLayout',
            'name' => 'ActionType_2',
            'visible' => $actionType == 2,
            'items' => [
                [
                'name'    => 'ScriptID',
                'type'    => 'SelectScript',
                'caption' => 'Action script ID'
                ],
                [
                    'type'    => 'Label',
                    'color' => 7566195,
                    'caption' => 'Information about last activity is available in $_IPS system variable.'
                ],
            ]
        ];

        $elements[] = [
            'type' => 'ColumnLayout',
            'name' => 'ActionType_3',
            'visible' => $actionType == 3,
            'items' => [
                [
                    'name' => 'ActionList',
                    'type' => 'List',
                    'add'  => true,
                    'delete' => true,
                    'columns' => [
                        [
                            'name' => 'Device',
                            'caption' => 'Addressed Device',
                            'width' => '250px',
                            'add' => '',
                            'edit' => [
                                'type' => 'Select',
                                'options' => $this->GetDevicesForSelect()
                            ]
                                ],
                        [
                            'name' => 'Action',
                            'caption' => 'Action',
                            'width' => 'auto',
                            'add' => '',
                            'edit' => [
                                'type' => 'SelectAction'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        /*
        $elements[] = [
            'type' => 'ExpansionPanel',
            'caption' => 'Help',
            'items' => [
                [
                    'type'    => 'Label',
                    'color' => 7566195,
                    'caption' => 'The following system variables with information about the triggering activity are available in the script:'
                ],
                [
                    'type'    => 'Label',
                    'color' => 7566195,
                    'italic'    => true,
                    'caption' => $this->GetSystemVariables()
                ]
            ]
        ];
        */
        
        return $elements;
    }

    /**
     * return form actions by token.
     *
     * @return array
     */
    private function FormActions(): array
    {
        $devices = $this->GetDevicesForSelect();
        array_shift($devices);

        $form = [
            [
                'type' => 'RowLayout',
                'items' => [
                    [
                        'type' => 'Select',
                        'name' => 'TestDevice',
                        'options' => $devices
                    ],      
                    [
                        'type'    => 'Button',
                        'caption' => 'Test Action',
                        'onClick' => 'IPS_RequestAction($id, "TestAction", $TestDevice);', 
                    ]
                ]
            ],
            [
                'type' => 'Label',
                'caption' => ''
            ],
            [
                'type' => 'Label',
                'italic' => false,
                'color' => 7566195,
                'caption' => "Information about the last execution of the routine:"
            ],
            [
                'type' => 'Label',
                'italic' => true,
                'color' => 7566195,
                'caption' => $this->GetLastActivityInformation()
            ]
            
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
                'code' => 201,
                'icon' => 'error',
                'caption' => 'Error in text-to-speech script. See message log for more information.'
            ]
        ];

        return $form;
    }
}
