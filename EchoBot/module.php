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
        $this->RegisterPropertyString('Text1', '');
        $this->RegisterPropertyString('Text2', '');
        $this->RegisterPropertyInteger('VariableID1', 0);
        $this->RegisterPropertyString('Script', "<?php\n//Note: always return the text message as string \n\n\$text = \"This is my answer\";\n\nreturn \$text;");
        $this->RegisterPropertyInteger('ScriptID', 0);
        $this->RegisterPropertyString('ActionID', '');

        $this->RegisterAttributeString('Automations', '');

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

                $this->LogMessage($payload['utterance'], KL_NOTIFY);

                if ($this->GetStatus() !== IS_ACTIVE) return;

                switch ($this->ReadPropertyInteger('ActionType'))
                {
                    case 0:
                        $text[] = $this->ReadPropertyString('Text1');
                        if (IPS_VariableExists($this->ReadPropertyInteger('VariableID1')) ) {
                            $text[] = ' '.GetValueFormatted($this->ReadPropertyInteger('VariableID1'));
                        }
                        $text[] = $this->ReadPropertyString('Text2');
                        $this->TextToSpeech(implode(' ', $text ), $payload['serialNumber'] , $payload['deviceType'] );
                        break;

                    case 1:
                        $this->RunTextToSpeechScript($payload);
                        break;

                    case 2:
                        if (IPS_ScriptExists( $this->ReadPropertyInteger('ScriptID') ) ){
                            IPS_RunScriptEx($this->ReadPropertyInteger('ScriptID'), $payload);
                        }
                        break;

                    case 3:
                        $action = json_decode($this->ReadPropertyString('ActionID'), true);
                        $parameters = array_merge($payload, $action['parameters']);
                        print_r($parameters);
                        IPS_RunAction($action['actionID'], $parameters);
                        break;
                }

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
        $script = $this->ReadPropertyString('Script');
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
    public function TextToSpeech(string $tts, string $deviceSerial, string $deviceType, array $options = [] ): bool
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
            'caption' => 'Select routine',
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

    public function UpdateConfigurationForm(string $name, $value)
    {
        switch ($name){
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
                'onChange' => 'ECHOBOT_UpdateConfigurationForm($id, "AutomationID", $AutomationID);',
                'width'    => '500px',
                'options' => $this->GetAutomationsForSelect()
                ],
                [
                'type'    => 'Button',
                'caption' => 'Refresh',
                'onClick' => 'ECHOBOT_UpdateConfigurationForm($id, "RefreshAutomations", $AutomationID);', 
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
            'onChange' => 'ECHOBOT_UpdateConfigurationForm($id, "ActionType", $ActionType);',
            'options' => [
                [ 'caption' => 'Text-to-speech response (simple)', 'value' => 0],
                [ 'caption' => 'Text-to-speech response (extended)', 'value' => 1],
                [ 'caption' => 'Run script', 'value' => 2],
                [ 'caption' => 'Run action', 'value' => 3]
            ]
        ];
        
        $elements[] = [
            'type' => 'RowLayout',
            'name' => 'ActionType_0',
            'visible' => $actionType == 0,
            'items' => [
                    [
                        'name'    => 'Text1',
                        'type'    => 'ValidationTextBox',
                        'caption' => 'Text'
                    ],
                    [
                        'name'    => 'VariableID1',
                        'type'    => 'SelectVariable',
                        'caption' => 'Variable'
                    ],
                    [
                        'name'    => 'Text2',
                        'type'    => 'ValidationTextBox',
                        'caption' => 'Text'
                    ]
            ]
        ];

        $elements[] = [
            'type' => 'ColumnLayout',
            'name' => 'ActionType_1',
            'visible' => $actionType == 1,
            'items' => [
                [
                'name'    => 'Script',
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
                ]
            ]
        ];

        $elements[] = [
            'type' => 'ColumnLayout',
            'name' => 'ActionType_3',
            'visible' => $actionType == 3,
            'items' => [
                [
                'name'    => 'ActionID',
                'type'    => 'SelectAction',
                'caption' => 'Action'
                ]
            ]
        ];

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
        
        return $elements;
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
                'type'    => 'TestCenter',]
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
