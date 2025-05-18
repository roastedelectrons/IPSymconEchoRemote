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

            case 'VisuGetList':
                $items = $this->GetItemsForVisu();
                $this->UpdateVisualizationValue(json_encode($items));
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

    private function UpdateVisualization()
    {
		$items = $this->GetItemsForVisu();

        $this->UpdateVariables($items);

        if (IPS_GetKernelVersion() >= 7.1){
            $this->UpdateVisualizationValue( json_encode($items) );
        }
    }

	private function UpdateVariables( array $listItems )
    {
        $string = '';

        foreach($listItems as $item){
            $symbol = "☐";
            if ($item['completed'])
            	$symbol = "☑";
            $string .= $symbol. "    " .$item['name']."\n\r";
        }
        
        $this->SetValue('List', $string);
    }

	private function GetItemsForVisu()
	{
		$items = json_decode($this->ReadAttributeString('ListItems'), true);

		if ($items === false)
			return [];

        $visuItems = array();

		foreach( $items as $key=>$item){

			if (!$this->ReadPropertyBoolean('ShowCompletedItems') && $item['itemStatus'] == 'COMPLETE'){
				continue;
			}

            $visuItems[] = [
                'id' => $item['itemId'],
                'name' => $item['itemName'],
                'completed' => ($item['itemStatus'] == 'COMPLETE')
            ];
		}
		return $visuItems;
	}


    public function Update()
    {
        $this->GetItems();
    }


    public function AddItem( string $text )
    {
        $item['listId']         = $this->ReadPropertyString('ListID');
        $item['itemName']      = $text;

        $result = $this->addListItem( $item );

        return is_array($result);
    }



    public function CheckItem( string $itemText )
    {
        $items = $this->getListItemByName( $itemText );

        $result = true; 

        foreach($items as $item){
            if ( !$this->CheckItemByID($item ['itemId']) )
                $result = false;
        }

        return $result;
    }

    public function CheckItemByID( string $itemID)
    {
        $item = $this->getListItemByID($itemID);
        $attibutesToUpdate['itemStatus'] = 'COMPLETE';

        $result = $this->updateListItem($item, $attibutesToUpdate);
        
        return $result;
    }

    public function UncheckItemByID( string $itemID)
    {
        $item = $this->getListItemByID($itemID);
        $attibutesToUpdate['itemStatus'] = 'ACTIVE';

        $result = $this->updateListItem($item, $attibutesToUpdate);
        
        return $result;
    }

    public function DeleteItem( string $itemText )
    {
        $items = $this->getListItemByName( $itemText );

        $result = true; 

        foreach($items as $item){
            if ( !$this->DeleteItemByID($item ['itemId']) )
                $result = false;
        }

        return $result;
    }

    public function DeleteItemByID( string $itemID )
    {
        $item = $this->getListItemByID( $itemID  );

        return $this->deleteListItem( $item);
    }

    public function GetItems(bool $includeCompletedItems = false)
    {

        $items = $this->getListItems();

        if ($items === false)
            return false;

        $this->WriteAttributeString('ListItems', json_encode($items));

        $this->UpdateVisualization();
 
		if ($includeCompletedItems === false){
			foreach($items as $key=>$item){
				if ( $item['itemStatus'] == 'COMPLETE'){
					unset($items[$key]);
				}
			}
		}

        return $items;
    }


    private function getListItems()
    {

        $url =  '/alexashoppinglists/api/v2/lists/' . $this->ReadPropertyString('ListID') . '/items/fetch?limit=100';

        $data['itemAttributesToProject'] = [];
        $data['itemAttributesToAggregate'] = [];
        $data['listAttributesToAggregate'] = [];


        $result = $this->SendCommand($url, $data, 'POST');

        if (isset($result['itemInfoList'])) return $result['itemInfoList'];

        return false;
    }

    private function getListItemByName( $itemText ): ?array
    {
        $items = json_decode($this->ReadAttributeString('ListItems'), true);

        $result = [];
        foreach ($items as $item){
            if (strtolower($item['itemName']) == strtolower($itemText)){
                $result[] = $item;
            }
        }

        return $result;
    }

    private function getListItemByID( $itemID ): ?array
    {
        $items = json_decode($this->ReadAttributeString('ListItems'), true);

        foreach ($items as $item){
            if ($item['itemId'] == $itemID){
                return $item;
            }
        }

        return false;
    }

    private function getListItem( $itemID ): ?array
    {

        $url =  '/alexashoppinglists/api/v2/lists/' . $this->ReadPropertyString('ListID') . '/items//'. $itemID;
        $data = null;

        $result = $this->SendCommand($url, $data, 'GET');

        return $result;
    }

    private function addListItem(array $item)
    {

        $url = '/alexashoppinglists/api/v2/lists/'.$item['listId']. '/items';

        $data['items'][] = [
            'itemType' => 'KEYWORD',
            'itemName' => $item['itemName'],
            'itemAttributesToCreate' => [],
            'itemAttributesToAggregate' => []
        ];

        $result = $this->SendCommand($url, $data, 'POST');

        return $result;
    }

    private function updateListItem(array $itemArray, array $attributesToUpdate)
    {

        $url = '/alexashoppinglists/api/v2/lists/'.$itemArray['listId']. '/items//' . $itemArray['itemId'] . '?version='.$itemArray['version'];

        $data['itemAttributesToUpdate'] = array();
        $data['itemAttributesToRemove'] = array();

        foreach($attributesToUpdate as $attribute => $value){
            $data['itemAttributesToUpdate'][] = [
                'type' => $attribute,
                'value' => $value
            ];
        }

        $result = $this->SendCommand($url, $data, 'PUT');

        return $result;
    }

    private function deleteListItem( array $itemArray)
    {

        $url = '/alexashoppinglists/api/v2/lists/'.$itemArray['listId']. '/items//' . $itemArray['itemId'] . '?version='.$itemArray['version'];

        $result = $this->SendCommand($url, null, 'DELETE');

        return $result;
    }


    private function GetLists( bool $cached = false)
    {

        if ($cached == true) {

            $items = json_decode( $this->ReadAttributeString('Lists'), true);

            if (is_array($items))
                return $items;     

        }
        $url = '/alexashoppinglists/api/v2/lists/fetch';
        $data = '{"listAttributesToAggregate":[],"listOwnershipType":null}';

        $result = $this->SendCommand($url, $data, 'POST');


        if (isset($result['listInfoList']) && is_array($result['listInfoList'])) {
            
            $this->WriteAttributeString('Lists', json_encode($result['listInfoList']));
    
            return $result['listInfoList'];
        }

        return [];

    }

    private function GetListIDsForSelect()
    {
        
        $items = $this->GetLists(true);

        $selectOptions = array();

        foreach( $items as $item){
            $option = array();
            if (!isset($item['listId'])) continue;

            if ( !isset($item['listName']) ) {
                $name = $this->Translate('Unnamed list');
                if ( $item['listType'] == 'SHOP' ) $name = $this->Translate('Shoppinglist (default)');
                if ( $item['listType'] == 'TODO' ) $name = $this->Translate('ToDo (default)');

                $option = [
                    'caption' => $name,
                    'value'   => $item['listId']
                ];
            } else {
                $option = [
                    'caption' => $item['listName'],
                    'value'   => $item['listId']
                ];                
            }

            $selectOptions[] = $option;
        }

        return $selectOptions;
    }

    private function SendCommand( string $url, mixed $data, string $method)
    {
        $payload['url'] =  $url;
        $payload['postfields'] = $data;
        $payload['method'] =  $method;

        $result = $this->SendDataPacket('AmazonApiRequest', $payload);

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
