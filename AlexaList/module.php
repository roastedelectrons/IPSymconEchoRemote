<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/EchoBufferHelper.php';
require_once __DIR__ . '/../libs/EchoDebugHelper.php';
require_once __DIR__ . '/../libs/VariableProfileHelper.php';


class AlexaList extends IPSModule
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
        $this->RegisterPropertyString('ListID', '');
        $this->RegisterPropertyBoolean('ShowCompletedItems', false);
        $this->RegisterPropertyBoolean('DeleteCompletedItems', false);
        $this->RegisterPropertyInteger('UpdateInterval', 60);

        $this->RegisterAttributeString('Lists', '');
        $this->RegisterAttributeString('ListItems', '');

        $this->RegisterTimer('Update', 0, 'ALEXALIST_Update(' . $this->InstanceID . ');');

        $this->ConnectParent('{C7F853A4-60D2-99CD-A198-2C9025E2E312}');

        if (IPS_GetKernelVersion() >= 7.1)
            $this->SetVisualizationType(1);

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

        $this->MaintainVariable('AddItem', $this->Translate('Add Item'), 3, '', 1, true );
        $this->MaintainAction('AddItem', true);
        $this->MaintainVariable('List', $this->Translate('List'), 3, '~TextBox', 1, true );

        $this->SetTimerInterval('Update', $this->ReadPropertyInteger('UpdateInterval') * 60 *1000);

        $this->Update();

        //Apply filter
        $this->SetReceiveDataFilter('.*(AlexaList).*');
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
            case 'Update':
                $this->Update();
                break;

            case 'UpdateLists':
                $this->GetLists();
                $this->UpdateFormField('ListID', 'options', json_encode($this->GetListIDsForSelect() )) ;
                break;
            case 'AddItem':
                $this->SetValue($ident, $value);
                $this->AddItem($value);
                $this->Update();
                $this->SetValue($ident, '');
                break;

            case 'VisuAddItem':
                $this->AddItem($value);
                $this->Update();
                break;

            case 'VisuCheckItem':
                if ($this->ReadPropertyBoolean('DeleteCompletedItems')){
                    $this->DeleteItemByID( $value );
                } else {
                    $this->CheckItemByID( $value );
                }
                $this->Update();
                break;

            case 'VisuUncheckItem':
                $this->UncheckItemByID( $value );
                $this->Update();
                break;

        }

        //return true;
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString, true);
        $payload = $data['Payload'];
        $this->SendDebug('Receive Data', $JSONString, 0);

        
        switch ($data['Type'])
        {            
            case 'AlexaList':
                // Not implemented
                break;
        }

    }

    public function GetVisualizationTile() {

        // Füge statisches HTML aus Datei hinzu
        $module = file_get_contents(__DIR__ . '/module.html');

        return $module;
    }

    private function UpdateListVariable( array $listItems )
    {
        $string = '';

        foreach($listItems as $item){
            $symbol = "☐";
            if ($item['completed'])
                $symbol = "☑";
            $string .= $symbol. "    " .$item['value']."\n\r";
        }
        
        $this->SetValue('List', $string);
    }

    public function Update()
    {
        if ($this->ReadPropertyBoolean('ShowCompletedItems')){
            $items = $this->GetListItems(true);
        } else {
            $items = $this->GetListItems(false);
        }
        
        
        $this->WriteAttributeString('ListItems', json_encode($items));

        $this->UpdateListVariable($items);
        $this->UpdateVisualizationValue( json_encode($items) );
    }


    public function AddItem( string $text )
    {
        $item['listId']         = $this->ReadPropertyString('ListID');
        $item['completed']      = false;
        $item['value']          = $text;

        $result = $this->addListItem( $item );

        return is_array($result);
    }



    public function CheckItem( string $itemText )
    {
        $item = $this->getListItemByName( $itemText );

        if ($item != false)
            return $this->CheckItemByID($item ['id']);

        return false;
    }

    public function CheckItemByID( string $itemID)
    {
        $item = $this->getListItem($itemID);
        $item['completed'] = true;

        $result = $this->updateListItem($item);
        
        return $result;
    }

    public function UncheckItemByID( string $itemID)
    {
        $item = $this->getListItem($itemID);
        $item['completed'] = false;

        $result = $this->updateListItem($item);
        
        return $result;
    }

    public function DeleteItem( string $itemText )
    {
        $item = $this->getListItemByName( $itemText );

        if ($item != [])
            return $this->DeleteItemByID($item ['id']);

        return false;
    }

    public function DeleteItemByID( string $itemID )
    {
        $item['listId'] = $this->ReadPropertyString('ListID') ;
        $item['id'] = $itemID;
        $item['value'] = '';

        return $this->deleteListItem( $item);
    }


    public function GetListItems(bool $includeCompletedItems = false): ?array
    {
        $options = [];

        if ($includeCompletedItems === false)
            $options['completed'] = 'false';

        $payload['url'] =  '/api/namedLists/' . $this->ReadPropertyString('ListID') . '/items?'. http_build_query($options);

        $result = $this->SendDataPacket('AlexaApiRequest', $payload);

        if (isset($result['http_code']) && ($result['http_code'] === 200)) {
            return json_decode($result['body'], true)['list'];
        }

        return [];
    }

    private function getListItemByName( $itemText )
    {
        $items = json_decode($this->ReadAttributeString('ListItems'), true);

        foreach ($items as $item){
            if ($item['value'] == $itemText){
                return $item;
            }
        }

        return [];
    }

    private function getListItem( $itemID ): ?array
    {

        $payload['url'] =  '/api/namedLists/' . $this->ReadPropertyString('ListID') . '/item/'. $itemID;

        $result = $this->SendDataPacket('AlexaApiRequest', $payload);

        if (isset($result['http_code']) && ($result['http_code'] === 200)) {
            return json_decode($result['body'], true);
        }

        return [];
    }

    private function addListItem(array $itemArray)
    {

        $item['listId']         = $itemArray['listId'];
        $item['completed']      = $itemArray['completed'];
        $item['value']          = $itemArray['value'];
        $item['createdDateTime'] = time();


        $payload['postfields'] = $item;
        $payload['url'] =  '/api/namedLists/' . $item['listId'] . '/item';

        $result = $this->SendDataPacket('AlexaApiRequest', $payload);

        if (isset($result['http_code']) && ($result['http_code'] === 200)) {
            return json_decode($result['body'], true);
        }

        return null;
    }

    private function updateListItem(array $itemArray)
    {
        $item['id']     	    = $itemArray['id'];
        $item['listId']         = $itemArray['listId'];
        $item['version']        = $itemArray['version'];
        $item['completed']      = $itemArray['completed'];
        $item['value']          = $itemArray['value'];
        $item['updatedDateTime'] = time();

        $payload['postfields'] = $item;
        $payload['method'] = 'PUT';
        $payload['url'] =  '/api/namedLists/' . $item['listId']  . '/item//' . $item['id'];

        $result = $this->SendDataPacket('AlexaApiRequest', $payload);

        if (isset($result['http_code']) && ($result['http_code'] === 200)) {
            return json_decode($result['body'], true);
        }

        return null;
    }

    private function deleteListItem( array $itemArray)
    {

        $item['listId'] = $itemArray['listId'] ;
        $item['id'] = $itemArray['id'];
        $item['value'] = '';

        $payload['postfields'] = $item;
        $payload['method'] = 'DELETE';
        $payload['url'] =  '/api/namedLists/' . $item['listId'] . '/item//' . $item['id'];

        $result = $this->SendDataPacket('AlexaApiRequest', $payload);

        if (isset($result['http_code']) && ($result['http_code'] === 200)) {
            return json_decode($result['body'], true);
        }

        return null;
    }


    private function GetLists( bool $cached = false)
    {

        if ($cached == true) {

            $items = json_decode( $this->ReadAttributeString('Lists'), true);

            if (is_array($items))
                return $items;     

        }

        $payload['url'] =  '/api/namedLists';

        $result = $this->SendDataPacket('AlexaApiRequest', $payload);

        if (isset($result['http_code']) && $result['http_code'] != 200) return [];

        $lists = json_decode($result['body'], true)['lists'];

        if (!is_array($lists)) {
            return [];
        }

        $this->WriteAttributeString('Lists', json_encode($lists));

        return $lists;
    }

    private function GetListIDsForSelect()
    {
        
        $items = $this->GetLists(true);

        $selectOptions = array();

        foreach( $items as $item){
            $option = array();
            if ( $item['type'] == 'SHOPPING_LIST' && $item['defaultList'] == 1) {
                $option = [
                    'caption' => $this->Translate('Shoppinglist (default)'),
                    'value'   => $item['customerId'] . '-SHOP'
                ];
            }
            elseif ( $item['type'] == 'TO_DO' && $item['defaultList'] == 1) {
                $option = [
                    'caption' => $this->Translate('ToDo (default)'),
                    'value'   => $item['customerId'] . '-TODO'
                ];
            } else {
                $option = [
                    'caption' => $item['name'],
                    'value'   => $item['itemId']
                ];                
            }

            $selectOptions[] = $option;
        }

        return $selectOptions;
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

        $rowItems[] = [
            'type'    => 'Select',
            'name'    => 'ListID',
            'caption' => "List",
            'options' => $this->GetListIDsForSelect()
        ];

        $rowItems[] = [
            'type'    => 'Button',
            'caption' => 'Reload Lists',
            'onClick' => 'IPS_RequestAction($id, "UpdateLists", "");', 
        ];

        $elements[] = [
            'type'    => 'RowLayout',
            'items'    => $rowItems
        ];
        $elements[] = [
            'type'    => 'Label',
            'caption' => ''
        ];

        $elements[] = [
            'type'    => 'Label',
            'caption' => "Visualisation settings",
            'italic' => false,
            'color' => 7566195
        ];

        $elements[] = [
            'type'    => 'CheckBox',
            'name'    => 'ShowCompletedItems',
            'caption' => "Show completed items"
        ];

        $elements[] = [
            'type'    => 'CheckBox',
            'name'    => 'DeleteCompletedItems',
            'caption' => "Delete completed items from list"
        ];

        $elements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'UpdateInterval',
            'caption' => 'Update interval',
            'suffix' => 'minutes',
            'minimum' => 0
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
        $elements[] = [
            'type'    => 'Button',
            'caption' => 'Refresh',
            'onClick' => 'IPS_RequestAction($id, "Update", "");', 
        ];

        return $elements;
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
