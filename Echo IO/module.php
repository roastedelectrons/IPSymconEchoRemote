<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/EchoBufferHelper.php';
require_once __DIR__ . '/../libs/EchoDebugHelper.php';

// Modul für Amazon Echo Remote

class AmazonEchoIO extends IPSModule
{
    use EchoBufferHelper;
    use EchoDebugHelper;

    private const STATUS_INST_NOT_AUTHENTICATED = 214; // authentication must be performed.
    private const STATUS_INST_REFRESH_TOKEN_IS_EMPTY = 215; // authentication must be performed.

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.

        //the following Properties can be set in the configuration form
        $this->RegisterPropertyBoolean('active', false);
        $this->RegisterPropertyInteger('language', 0);
        $this->RegisterPropertyString('refresh_token', '');    
        $this->RegisterPropertyBoolean('TimerLastAction', true);
        $this->RegisterPropertyBoolean('LogMessageEx', false);
        
        $this->RegisterPropertyString(
            'browser', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:58.0) Gecko/20100101 Firefox/58.0'
        );

        $this->RegisterAttributeString('devices', '[]');
        $this->RegisterAttributeString('CookiesFileName', IPS_GetKernelDir() . 'alexa_cookie_'. $this->InstanceID .'.txt');
        $this->RegisterAttributeInteger('LastDeviceTimeStamp', 0);
        $this->RegisterAttributeInteger('LastCookieRefresh', 0);
        $this->RegisterAttributeInteger('CookieExpirationDate', 0);

        $this->RegisterTimer('TimerLastDevice', 0, 'ECHOIO_GetLastDevice(' . $this->InstanceID . ');');
        $this->RegisterTimer('RefreshCookie', 0, 'ECHOIO_LogIn(' . $this->InstanceID . ');');
        $this->RegisterTimer('UpdateStatus', 0, 'ECHOIO_UpdateStatus(' . $this->InstanceID . ');');

        //we will wait until the kernel is ready
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
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



    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->SendDebug(__FUNCTION__, '== started ==', 0);

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        // Migration of cookie file name
        if ( $this->ReadAttributeString('CookiesFileName') == IPS_GetKernelDir() . 'alexa_cookie.txt' )
        {
            $oldFileName = $this->ReadAttributeString('CookiesFileName');
            $newFileName = IPS_GetKernelDir() . 'alexa_cookie_'. $this->InstanceID .'.txt'; 

            $this->WriteAttributeString('CookiesFileName', $newFileName);

            if ( file_exists($oldFileName) )
            {
                rename($oldFileName, $newFileName);
            }
            
        }

        $this->RegisterVariableInteger('cookie_expiration_date', $this->Translate('Cookie expiration date'), '~UnixTimestamp', 0);

        $active = $this->ReadPropertyBoolean('active');

        if (!$active)
        {
            $this->LogOff();
            $this->SetTimerInterval('TimerLastDevice', 0);
            $this->SetTimerInterval('RefreshCookie', 0);
            $this->SetTimerInterval('UpdateStatus', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }



        if ( $this->ReadPropertyString('refresh_token') == "")
        {
            $this->SetStatus(self::STATUS_INST_REFRESH_TOKEN_IS_EMPTY);
            die('refresh token missing');
            return false;
        } 

        
        if ( !$this->CheckLoginStatus() )
        {
            if ( !$this->LogIn() )
                return;
        }

        
        $TimerLastAction = $this->ReadPropertyBoolean('TimerLastAction');

        // Update devices and get DeviceList
        $this->UpdateDeviceList();

        $devices = $this->GetDeviceList();

        if (empty($devices)) {
            $this->SendDebug(__FUNCTION__, 'DeviceInfo missing', 0);
        } else {
            $device_association = [];
            $max = 1;
            foreach ($devices as $key => $device) {
                $accountName = $device['accountName'];
                $device_serialNumber = $device['serialNumber'];
                $device_association[] = [$key + 1, $accountName, '', -1];
                $max = $max + 1;
            }
            $this->SendDebug('Devices Profile', json_encode($device_association), 0);
            $this->RegisterProfileAssociation(
                'EchoRemote.LastDevice', '', '', '', 1, $max, 0, 0, VARIABLETYPE_INTEGER, $device_association);
            $this->RegisterVariableInteger('last_device', $this->Translate('last device'), 'EchoRemote.LastDevice', 1);
            if ($TimerLastAction) {
                $this->SetTimerInterval('TimerLastDevice', 2000);
            } else {
                $this->SetTimerInterval('TimerLastDevice', 0);
            }
        }

        $this->SetTimerInterval('UpdateStatus', 60000);
    }


    private function GetCookieByRefreshToken()
    {

        $header = [
            'Connection: keep-alive',
            'x-amzn-identity-auth-domain: api.'.$this->GetAmazonURL() 
        ];

        $url = "https://api.".$this->GetAmazonURL()."/ap/exchangetoken/cookies";

        $post['requested_token_type'] = 'auth_cookies';
        $post['app_name'] = 'Amazon Alexa';
        $post['domain'] = 'www.'.$this->GetAmazonURL();
        $post['source_token_type'] = 'refresh_token';
        $post['source_token'] = $this->ReadPropertyString('refresh_token');

        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);    
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);   
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->ReadPropertyString('browser'));
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));

        curl_setopt($ch, CURLOPT_URL, $url);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            trigger_error('Error:' . curl_error($ch));
        }
        $info = curl_getinfo($ch);
        curl_close($ch);

        $result = $this->getReturnValues($info, $result);

        if ($result['http_code'] == 400) 
        {
            $response = json_decode($result['body']);
            if ($response !== false)
            {
                die( $response->response->error->code.": ".$response->response->error->message );
            }
            return false;
        }

        if ($result['http_code'] == 200)
        {
            $cookieTXT = $this->convertJSONtoPlainTxtCookie( $result['body'] );

            if ($cookieTXT != "" ) 
            {
                file_put_contents($this->ReadAttributeString('CookiesFileName') , $cookieTXT);
                return true;
            }
        }

        return false;
    }

    private function convertJSONtoPlainTxtCookie( $cookieJSON )
    {

        $amazonURL = ".".$this->GetAmazonURL();
  
        $cookieJSON = json_decode($cookieJSON, true);

        $cookieTXT = "";
        foreach ( $cookieJSON['response']['tokens']['cookies'][$amazonURL] as $entry )
        {
            $cookie = [];
        
            $entry['Domain'] = $amazonURL;
        
            if ( $entry['HttpOnly'] == true )
            {
                $entry['Domain'] = "#HttpOnly_".$amazonURL;
            }
        
         
        
            if ($entry['Secure'])
            {
                $entry['Secure'] = "TRUE";
            } else
            {
                $entry['Secure'] = "FALSE";
            }

            // Workaround on 32-bit systems for cookie expiration dates after 2038
            if ( PHP_INT_SIZE == 4 &&  explode( " ", $entry['Expires'] )[2] >= 2038 )
            {
                $expires = 2147483647;
            } else {
                $expires = strtotime( $entry['Expires']  );
            }
        
            $cookie[0] = $entry['Domain'];
            $cookie[1] = "TRUE";
            $cookie[2] = $entry['Path'];
            $cookie[3] = $entry['Secure'];
            $cookie[4] = $expires;
            $cookie[5] = $entry['Name'];
            $cookie[6] = $entry['Value'];
        
            $cookieTXT .= implode( "\t", $cookie)."\n";
        }

        return $cookieTXT;

    }

    private function GetCSRF()
    {
        // get CSRF
        //
        // ${CURL} ${OPTS} -s -c ${COOKIE} -b ${COOKIE} -A "${BROWSER}" -H "DNT: 1" -H "Connection: keep-alive" -L\
        // -H "Referer: https://alexa.${AMAZON}/spa/index.html" -H "Origin: https://alexa.${AMAZON}"\
        // https://${ALEXA}/api/language > /dev/null

        /*
        Damit die XHR-Aufrufe gegen cross-site Attacken gesichert werden, muss für das Cookie noch ein CSRF Token erstellt werden.
        Dies erfolgt beim ersten Aufruf von einer API auf layla.amazon.de. z.B. /api/language unter Angabe des oben gespeicherten Cookies
        => CSRF wird ins Cookie geschrieben
         */       

        $urls = [
            '/api/language',
            '/spa/index.html',
            '/api/devices-v2/device?cached=false',
            '/templates/oobe/d-device-pick.handlebars',
            '/api/strings'
        ];

        $headers = [
            'User-Agent: ' . $this->ReadPropertyString('browser'),
            'DNT: 1',
            'Connection: keep-alive',
            'Referer: https://alexa.' . $this->GetAmazonURL() . '/spa/index.html',
            'Origin: https://alexa.' . $this->GetAmazonURL()
        ];


        foreach ( $urls as $path )
        {
            $url = 'https://' . $this->GetAlexaURL() . $path;
            
            $this->HttpRequestCookie($url, $headers);
    
            if ( $this->getCsrfFromCookie() !== false )
            {
                // CSRF found
                $this->SendDebug(__FUNCTION__, 'Successfully got csrf from:  '.$url  , 0);
                return true;
            }
            $this->SendDebug(__FUNCTION__, 'Failed to get csrf from: ' . $url , 0);
        }
        
        $this->LogMessage('Failed to get CSRF', KL_ERROR);

        die('Failed to get CSRF');

    }

    private function getCsrfFromCookie()
    {

        $CookiesFileName = $this->ReadAttributeString('CookiesFileName');

        if (file_exists($CookiesFileName)) {
            //get CSRF from cookie file
            $cookie_line = array_values(preg_grep('/\tcsrf\t/', file($CookiesFileName)));
            if (isset($cookie_line[0])) {
                $csrf = preg_split('/\s+/', $cookie_line[0])[6];
                return $csrf;
            }
        }

        return false;

    }

    private function getExpirationDateFromCookie()
    {

        $expirationDate = 0;

        $CookiesFileName = $this->ReadAttributeString('CookiesFileName');

        if (file_exists($CookiesFileName)) {
            
            $cookie_line = array_values(preg_grep('/\tat-acbde\t/', file($CookiesFileName)));
            if (isset($cookie_line[0])) {
                $expirationDate = preg_split('/\s+/', $cookie_line[0])[4];
            }
        }

        return $expirationDate;

    }

    public function LogIn(): bool
    {
        $this->SendDebug(__FUNCTION__, '== started ==', 0);

        $this->SetTimerInterval('RefreshCookie', 0);

        if ( !$this->ReadPropertyBoolean('active') )
        {
            die('EchoIO Instance is inactive');
        }

        $result = $this->GetCookieByRefreshToken();

        if ( !$result )
            return false;

        $result = $this->GetCSRF();

        if ( !$result )
            return false;
        
        $this->SetValue('cookie_expiration_date', $this->getExpirationDateFromCookie() );
        $this->WriteAttributeInteger('CookieExpirationDate', $this->getExpirationDateFromCookie() );
        $this->WriteAttributeInteger('LastCookieRefresh', time() );

        if ( !$this->CheckLoginStatus() )
            return false;

        //Update device list
        $this->UpdateDeviceList();

        return true;

    }

    public function LogOff(): bool
    {
        $this->SendDebug(__FUNCTION__, '== started ==', 0);
        $url = $this->GetAlexaURL() . '/logout';

        $headers = [
            'DNT: 1',
            'Connection: keep-alive']; //the header must not contain any cookie

        $return = $this->HttpRequestCookie($url, $headers);

        if ($return['http_code'] === 200) { //OK
            $this->SetStatus(self::STATUS_INST_NOT_AUTHENTICATED);
            $this->WriteAttributeInteger('CookieExpirationDate', 0); 
            return $this->deleteFile($this->ReadAttributeString('CookiesFileName'));
        }

        return false;
    }

    /**
     * checks if the user is authenticated and saves the custonmerId in a buffer.
     *
     * @return bool
     */
    public function CheckLoginStatus(): bool
    {
        $this->SendDebug(__FUNCTION__, '== started ==', 0);
        //######################################################
        //
        // bootstrap with GUI-Version writes GUI version to cookie
        //  returns among other the current authentication state
        //
        // AUTHSTATUS=$(${CURL} ${OPTS} -s -b ${COOKIE} -A "${BROWSER}" -H "DNT: 1" -H "Connection: keep-alive" -L https://${ALEXA}/api/bootstrap?version=${GUIVERSION}
        //   | sed -r 's/^.*"authenticated":([^,]+),.*$/\1/g')

        if ( !$this->ReadPropertyBoolean('active') )
        {
            die('EchoIO Instance is inactive');
        }

        $guiversion = 0;

        $getfields = ['version' => $guiversion];

        $url = 'https://' . $this->GetAlexaURL() . '/api/bootstrap?' . http_build_query($getfields);
        $return_data = $this->HttpRequest($url, $this->GetHeader());

        if ($return_data['body'] === null) {
            $return = null;
        } else {
            $return = json_decode($return_data['body'], false);
        }

        if ($return === null) {
            $this->SendDebug(__FUNCTION__, 'Not authenticated (return is null)! ', 0);

            $authenticated = false;
        } elseif (!property_exists($return, 'authentication')) {
            $this->SendDebug(
                __FUNCTION__, 'Not authenticated (property authentication not found)! ' . $return_data['body'], 0
            );

            $authenticated = false;
        } elseif ($return->authentication->authenticated) {
            //$this->WriteAttributeString('customerID', $return->authentication->customerId); //TEST
            $this->SetBuffer('customerID', $return->authentication->customerId);
            $this->SendDebug(__FUNCTION__, 'CustomerID: ' . $return->authentication->customerId, 0);
            $authenticated = true;
        } else {
            $this->SendDebug(
                __FUNCTION__, 'Not authenticated (property authenticated is false)! ' . $return_data['body'], 0
            );

            $authenticated = false;
        }

        if (!$authenticated) {
            //$this->WriteAttributeString('customerID', ''); //TEST
            $this->SetBuffer('customerID', '');
            $statusCode = self::STATUS_INST_NOT_AUTHENTICATED;
        } else 
        {
            $statusCode = IS_ACTIVE;
            $this->setCookieRefreshTimer();
        }

        if ( $this->GetStatus() != $statusCode)
        {
            $this->SetStatus( $statusCode );
        }

        return $authenticated;
    }

    /**  Send to Echo API
     *
     * @param string $url
     * @param array $postfields
     * @param string $type
     *
     * @return mixed
     */
    private function SendEcho(string $url, array $postfields = null, string $method = null)
    {

        if ( $this->GetStatus() != 102 )
        {
            $this->SendDebug(__FUNCTION__, 'EchoIO not active. Status: '.$this->GetStatus(), 0);
            //Workaroud since the Echo Device Instances expext an array response to load the Configurationform properly
            return ['http_code' => 502, 'header' => '', 'body' => 'EchoIO not active. Status: '.$this->GetStatus() ];
        }

        $header = $this->GetHeader();

        return $this->HttpRequest($url, $header, $postfields, null, $method );
    }

    /**  Send http request
     *
     * @param string $url
     * @param array $header
     * @param array $postfields
     * @param bool|null $optpost
     * @param string $type
     *
     * @return mixed
     */
    private function HttpRequest(string $url, array $header, array $postfields = null, bool $optpost = null, string $type = null)
    {
        $this->SendDebug(__FUNCTION__, 'Header: ' . json_encode($header), 0);

        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 6, //timeout after 6 seconds
            CURLOPT_HEADER => true,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_ENCODING => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1];


        $options[CURLOPT_COOKIEFILE] = $this->ReadAttributeString('CookiesFileName'); //this file is read


        if ($postfields !== null) 
        {
            $this->SendDebug(__FUNCTION__, 'Postfields: ' . json_encode($postfields), 0);
            $options[CURLOPT_POSTFIELDS] = json_encode($postfields);
        }

        if ($optpost !== null && $type == null) {
            $options[CURLOPT_POST] = $optpost;
        }

        if ($type == 'PUT') {
            $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
        }
        if ($type == 'DELETE') {
            $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }

        $this->SendDebug(__FUNCTION__, 'Options: ' . json_encode($options), 0);
        curl_setopt_array($ch, $options);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            $this->SendDebug(__FUNCTION__, 'Error: (' . curl_errno($ch) . ') ' . curl_error($ch), 0);
            if ($this->ReadPropertyBoolean('LogMessageEx') )
            {
                $this->LogMessage('Error: (' . curl_errno($ch) . ') ' . curl_error($ch), KL_ERROR);
            }  
            //Workaroud since the Echo Device Instances expext an array response
            return ['http_code' => 502, 'header' => '', 'body' => 'Error: (' . curl_errno($ch) . ') ' . curl_error($ch) ];
        }

        $info = curl_getinfo($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $this->SendDebug(__FUNCTION__, 'Send to URL: ' . print_r($url, true), 0);
        $this->SendDebug(__FUNCTION__, 'Curl Info: ' . $http_code . ' ' . print_r($info, true), 0);
        curl_close($ch);
        //eine Fehlerbehandlung macht hier leider keinen Sinn, da 400 auch kommt, wenn z.b. der Bildschirm (Show) ausgeschaltet ist

        if ($info['http_code'] == 401)
        {
            $this->SetStatus(self::STATUS_INST_NOT_AUTHENTICATED);
        }

        return $this->getReturnValues($info, $result);
    }


    private function HttpRequestCookie(string $url, array $header, array $postfields = null): array
    {
        $this->SendDebug(__FUNCTION__, 'url: ' . $url, 0);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);  
        curl_setopt($ch, CURLOPT_TIMEOUT, 6); 
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->ReadAttributeString('CookiesFileName')); //this file is read
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->ReadAttributeString('CookiesFileName'));  //this file is written
        curl_setopt($ch, CURLOPT_USERAGENT, $this->ReadPropertyString('browser'));
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if ($postfields !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        }

        curl_setopt($ch, CURLOPT_URL, $url);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            trigger_error('Error:' . curl_error($ch));
            return ['http_code' => 502, 'header' => '', 'body' => 'Error:' . curl_error($ch)];
        }
        $info = curl_getinfo($ch);
        curl_close($ch);

        return $this->getReturnValues($info, $result);
    }

    private function GetHeader(): array
    {
        $csrf = '';

        $csrf = $this->getCsrfFromCookie();

        $headers = [
            'User-Agent: ' . $this->ReadPropertyString('browser'),
            'DNT: 1',
            'Connection: keep-alive',
            'Content-Type: application/json; charset=UTF-8',            
            'Referer: http://alexa.' . $this->GetAmazonURL() . '/spa/index.html',
            'Origin: https://alexa.' . $this->GetAmazonURL()
        ];

        if ($csrf) {
            $headers[] = 'csrf: ' . $csrf;
        }

        return $headers;
    }


    private function getReturnValues(array $info, string $result): array
    {
        $HeaderSize = $info['header_size'];

        $http_code = $info['http_code'];
        $this->SendDebug(__FUNCTION__, 'Response (http_code): ' . $http_code, 0);

        $header = explode("\n", substr($result, 0, $HeaderSize));
        $this->SendDebug(__FUNCTION__, 'Response (header): ' . json_encode($header), 0);

        $body = substr($result, $HeaderSize);
        $this->SendDebug(__FUNCTION__, 'Response (body): ' . $body, 0);

        return ['http_code' => $http_code, 'header' => $header, 'body' => $body];
    }


    private function GetAlexaURL(): string
    {
        $language = $this->ReadPropertyInteger('language');
        switch ($language) {
            case 0: // de
                $alexa_url = 'alexa.amazon.de';
                break;

            case 1:
                $alexa_url = 'pitangui.amazon.com';
                break;

            default:
                trigger_error('Unexpected language: ' . $language);
                $alexa_url = '';
        }

        return $alexa_url;
    }

    private function GetAmazonURL(): string
    {
        $language = $this->ReadPropertyInteger('language');
        switch ($language) {
            case 0: // de
                $amazon_url = 'amazon.de';
                break;

            case 1:
                $amazon_url = 'amazon.com';
                break;

            default:
                trigger_error('Unexpected language: ' . $language);
                $amazon_url = '';
        }

        return $amazon_url;
    }

    private function GetLanguage(): string
    {
        $language = $this->ReadPropertyInteger('language');
        switch ($language) {
            case 0: // de
                $language_string = 'de-DE';
                break;

            case 1:
                $language_string = 'en-us';
                break;

            default:
                trigger_error('Unexpected language: ' . $language);
                $language_string = '';
        }

        return $language_string;
    }

    private function deleteFile(string $FileName): bool
    {
        if (file_exists($FileName)) {
            $Success = unlink($FileName);

            if ($Success) { //the cookie file was deleted successfully
                $this->SendDebug(__FUNCTION__, 'File \'' . $FileName . '\' was deleted', 0);
                return true;
            }
            $this->SendDebug(__FUNCTION__, 'File \'' . $FileName . '\' was not deleted', 0);
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'File \'' . $FileName . '\' does not exist', 0);
        return true;
    }


    public function UpdateStatus()
    {
        $this->UpdateDeviceList();
        $this->UpdateAllDeviceVolumes();
    }

    public function GetDevice( string $deviceSerial, string $deviceType)
    {
        $devices = $this->GetDeviceList();

        foreach($devices as $device)
        {
            if ($device['deviceType'] == $deviceType && $device['serialNumber'] == $deviceSerial)
                return $device;
        }

        return false;
    }

    public function GetDeviceList()
    {
        $devices = json_decode( $this->ReadAttributeString('devices'), true);
        if ($devices == array() ) {

            $result = $this->UpdateDeviceList();

            if ($result['http_code'] === 200) {
                $this->SendDebug('Response IO:', $result['body'], 0);

                $devices = json_decode($result['body'], true);
                if ($devices === false) {
                    $devices = array();
                }
            } else {
                $devices = array();
            }
        }
        $this->SendDebug('Echo Devices:', json_encode($devices), 0);
        return $devices;
    }

    /** get JSON device list
     *
     *
     * @return mixed
     */
    public function UpdateDeviceList()
    {
        
        $cached = false;

        $getfields = [
            'cached' => $cached ? 'true' : 'false'];

        $url = 'https://' . $this->GetAlexaURL() . '/api/devices-v2/device?' . http_build_query($getfields);

        $result = $this->SendEcho($url);

        if ($result['http_code'] !== 200) {
            return $result;
        }

        $devices_arr = json_decode($result['body'], true);
        
        if ($devices_arr === false)
        {
            $result['http_code'] = 502;
            return $result;
        }

        $devices = $devices_arr['devices'];

        // Save device list to attribute
        $this->WriteAttributeString('devices', json_encode($devices));

        foreach($devices as $device)
        {
            $this->SendDataToChild($device['serialNumber'], $device['deviceType'], 'DeviceInfo', $device );
        }
        
        $result['body'] = json_encode($devices);

        return $result;
    }

    private function getClusterMembers( $serialNumber )
    {
        
        $devices = $this->GetDeviceList();

        $clusterMembers = array();
        $clusterMembersSerials = array();

        foreach ( $devices as $device )
        {
            if ($device['serialNumber' ] == $serialNumber )
            {
                if ($device['deviceFamily'] == 'WHA' && $device['clusterMembers'] != array() )
                {
                    $clusterMembersSerials = $device['clusterMembers'];
                    break;
                }
            }
        }

        // Get DeviceType of each clusterMember
        foreach( $clusterMembersSerials as $serial)
        {
            foreach( $devices as $device )
            {
                if ($device['serialNumber' ] == $serial )
                {
                    $clusterMembers[] = [
                        'deviceSerialNumber' => $device['serialNumber' ],
                        'deviceType'   => $device['deviceType' ]
                    ];
                }
            }
        }

        return $clusterMembers;
    }

    private function setCookieRefreshTimer()
    {
        
        $cookieRefreshDate = $this->ReadAttributeInteger('CookieExpirationDate');

        // For backward compatibility, if attribute CookieExpirationDate is not set yet
        if ( $cookieRefreshDate <= 0 )
        {
            $cookieRefreshDate = $this->getExpirationDateFromCookie();
        }

        // Invalid or no cookie found
        if ( $cookieRefreshDate <= 0 )
        {
            // IP-Symcon watchdog will handle reconnect
            $this->SendDebug(__FUNCTION__, 'No valid cookie found. RefreshCookie disabled', 0);
            $this->SetTimerInterval('RefreshCookie', 0);
            return;
        }        
            
        $refreshInterval = $cookieRefreshDate - time();

        if ( $refreshInterval > 3600) 
        {
            // Cookie expires in more than 1 houre
            $this->SetTimerInterval('RefreshCookie', ( $refreshInterval - 3600) *1000);
            $this->SendDebug(__FUNCTION__, 'RefreshCookie in: '. ($refreshInterval-3600) .' s', 0);
            
        }
        else 
        {
            // Cookie is expired or expires in less than 1 houre, refresh now
            // Last cookie refresh should be more than 1 houre ago to avoid endless login loop
            if ( $this->ReadAttributeInteger('LastCookieRefresh') < time() - 3600 )
            {
                $this->SendDebug(__FUNCTION__, 'RefreshCookie now', 0);
                $this->SetTimerInterval('RefreshCookie', 1000);
            }
            else
            {
                // IP-Symcon watchdog will handle reconnect
                $this->SendDebug(__FUNCTION__, 'RefreshCookie disabled', 0);
                $this->SetTimerInterval('RefreshCookie', 0);
            }
        }

        
        
    }

    /**
     * register profile association.
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
    protected function RegisterProfileAssociation($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $Stepsize, $Digits, $Vartype, $Associations)
    {
        if (is_array($Associations) && count($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        }
        $this->RegisterProfile($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $Stepsize, $Digits, $Vartype);

        if (is_array($Associations)) {
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

    /**
     * register profiles.
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
    protected function RegisterProfile($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits, $Vartype)
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, $Vartype); // 0 boolean, 1 int, 2 float, 3 string,
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != $Vartype) {
                $this->_debug('profile', 'Variable profile type does not match for profile ' . $Name);
            }
        }
        $profile = IPS_GetVariableProfile($Name);
        $profile_type = $profile['ProfileType'];
        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        if ($profile_type != VARIABLETYPE_STRING) {
            IPS_SetVariableProfileDigits($Name, $Digits); //  Nachkommastellen
            IPS_SetVariableProfileValues(
                $Name, $MinValue, $MaxValue, $StepSize
            ); // string $ProfilName, float $Minimalwert, float $Maximalwert, float $Schrittweite
        }
    }

    /**
     * send debug log.
     *
     * @param string $notification
     * @param string $message
     * @param int $format 0 = Text, 1 = Hex
     */
    private function _debug(string $notification = null, string $message = null, $format = 0)
    {
        $this->SendDebug($notification, $message, $format);
    }


    public function GetLastDevice()
    {
        if ( IPS_SemaphoreEnter ( 'GetLastDevice.'.$this->InstanceID , 1000) )
        {
            $response_activities = $this->CustomCommand('https://{AlexaURL}/api/activities?startTime=&size=10&offset=1');
            IPS_SemaphoreLeave ( 'GetLastDevice.'.$this->InstanceID );
        } 
        else
        {
            return [];
        }

        $last_device = ['name' => '', 'serialnumber' => '', 'creationTimestamp' => '', 'summary' => ''];
        if ($response_activities != false) {
            $http_code = $response_activities['http_code'];

            $serialNumber = '';
            if ($http_code == 200) {
                $payload_activities = $response_activities['body'];
                if (!empty($payload_activities)) {
                    $activities_array = json_decode($payload_activities, true);
                    $activities = $activities_array['activities'];
                    foreach ($activities as $key => $activity) {
                        $state = $activity['activityStatus'];
                        if ($state == 'SUCCESS') {
                            $sourceDeviceIds = $activity['sourceDeviceIds'][0];
                            $serialNumber = $sourceDeviceIds['serialNumber'];
                            $creationTimestamp = intval( $activity['creationTimestamp'] / 1000);
                            $description = $activity['description'];
                            $summary = json_decode($description)->summary;
                            break;
                        }
                    }
                }
            }
            $devices = $this->GetDeviceList();

            if (empty($devices)) {
                return [];
            }

            if ($serialNumber != '') {
                foreach ($devices as $key => $device) {
                    $accountName = $device['accountName'];
                    $device_serialNumber = $device['serialNumber'];
                    if ($serialNumber == $device_serialNumber) {
                        $this->SendDebug('Echo Device', 'account name: ' . $accountName, 0);
                        $this->SendDebug('Echo Device', 'serial number: ' . $device_serialNumber, 0);
                        $this->SendDebug('Echo Command', 'summary: ' . $summary, 0);
                        $last_device = ['name' => $accountName, 'serialnumber' => $device_serialNumber, 'creationTimestamp' => $creationTimestamp, 'summary' => $summary];
                        //$payload = json_encode(['DataID' => '{E41E38AC-30D7-CA82-DEF5-9561A5B06CD7}', 'Buffer' => $last_device]);
                        //$this->SendDataToChildren($payload);
                        $this->SendDataToChild($device_serialNumber, '', 'LastAction', $last_device);

                        //$this->SendDebug('Forward Data Last Device', $payload, 0);

                        $this->SendDebug('Echo LastDevice', 'LastDeviceTimeStamp: ' . $this->ReadAttributeInteger( 'LastDeviceTimeStamp' ), 0);
                        $this->SendDebug('Echo LastDevice', 'creationTimestamp: ' . $creationTimestamp, 0);
                        
                        if ( $creationTimestamp != $this->ReadAttributeInteger( 'LastDeviceTimeStamp' ))
                        {
                            $this->SetValue('last_device', $key + 1);
                            $this->WriteAttributeInteger( 'LastDeviceTimeStamp', $creationTimestamp);
                        }  
                    }
                }
            }
        }
        return $last_device;
    }

    private function CustomCommand(string $url, array $postfields = null, string $method = 'GET')
    {
        $url = str_replace(['{AlexaURL}', '{AmazonURL}'], [$this->GetAlexaURL(), $this->GetAmazonURL()], $url);

        return $this->SendEcho($url, $postfields, $method);
    }

    private function SendDataToChild($deviceSerial, $deviceType, $type, $data)
    {
        $payload['DataID']          = '{E41E38AC-30D7-CA82-DEF5-9561A5B06CD7}';
        $payload['Type']            = $type;
        $payload['DeviceSerial']    = $deviceSerial;
        $payload['DeviceType']    = $deviceType;
        $payload['Payload']            = $data;

        $this->SendDataToChildren( json_encode($payload) );
    }


    /**
     * @param $JSONString
     *
     * @return bool|false|string
     */
    public function ForwardData($JSONString)
    {
        $this->SendDebug(__FUNCTION__, 'Incoming: ' . $JSONString, 0);
        // Empfangene Daten von der Device Instanz
        $data = json_decode($JSONString, true);

        if (!isset($data['Type'])) {
            trigger_error('Property \'Type\' is missing');
            return false;
        }

        $this->SendDebug(__FUNCTION__, '== started == (Type \'' . $data['Type'] . '\')', 0);
        //$this->SendDebug(__FUNCTION__, 'Method: ' . $data->method, 0);

        $payload = $data['Payload'];

        switch ($data['Type']) {

            case 'SendEcho':
                $url = 'https://'. $this->GetAlexaURL() . $data['Payload']['url'];
                $method = '';
                $postfields = null;
                if ( isset($data['Payload']['method'])) $method = $data['Payload']['method'];
                if ( isset($data['Payload']['postfields'])) $postfields = $data['Payload']['postfields'];

                $result = $this->SendEcho($url, $postfields, $method);
                break;

            case 'BehaviorsPreview':
                $postfields = $payload['postfields'];

                $result = $this->BehaviorsPreview($postfields);
                break;

            case 'BehaviorsPreviewAutomation':
                $result = $this->BehaviorsPreviewAutomation( $payload['device'], $payload['automation'] );
                break;

            case 'CustomCommand':
                // DEPRECATED will be replaced by SendEcho
                $url = null;
                $postfields = null;
                $method = null;

                if (isset($payload['postfields'] ))
                    $postfields = $payload['postfields'];

                if (isset($payload['method'] ))
                    $postfields = $payload['method'];

                if (isset($payload['url'])) {
                    $url = $payload['url'];
                }

                $result = $this->CustomCommand($url, $postfields, $method);
                break;

            case 'GetDevices':
                $result = $this->UpdateDeviceList();
                break;

            case 'GetDNDState':
                $result = $this->GetDNDState();
                break;

            case 'GetCustomerID':
                $result = $this->GetBuffer('customerID');
                break;

            case 'GetLanguage':
                $result = $this->GetLanguage();
                break;

            default:
                trigger_error('Type \'' . $data['Type'] . '\' not yet supported');
                return false;
        }

        $ret = json_encode($result);
        $this->SendDebug(__FUNCTION__, 'Return: ' . strlen($ret) . ' Zeichen', 0);
        return $ret;
    }


    public function UpdateAllDeviceVolumes()
    {
        /*
        Array
        (
            [alertVolume] => 
            [deviceType] => XXXX
            [dsn] => XXX
            [error] => 
            [speakerMuted] => 
            [speakerVolume] => 30
        )
        */

        $url = 'https://' . $this->GetAlexaURL() . '/api/devices/deviceType/dsn/audio/v1/allDeviceVolumes';

        $result = $this->SendEcho($url); 
        
        if ($result['http_code'] == 200)
        {
            $volumes = json_decode( $result['body'], true );

            if (isset($volumes['volumes']) && is_array($volumes['volumes']))
            {
                $this->SetBuffer('DeviceVolumes', json_encode($volumes['volumes'] ));

                foreach($volumes['volumes'] as $device)
                {
                    $payload = [
                        'EchoVolume' => $device['speakerVolume'],
                        'Mute' => $device['speakerMuted'],
                    ];
                    $this->SendDataToChild($device['dsn'], $device['deviceType'], 'Volume', $payload );
                }
                
                return $volumes['volumes'];
            }
        }
        return [];
    }

    private function GetDeviceVolume( $deviceSerial, $deviceType )
    {

        $buffer = $this->GetBuffer('DeviceVolumes');

        if ($buffer != '')
        {
            $deviceVolumes = json_decode ($buffer, true);
        }
        else
        {
            $deviceVolumes = $this->UpdateAllDeviceVolumes();
        }
            
        foreach ($deviceVolumes as $device)
        {
            if ($device['dsn'] == $deviceSerial && $device['deviceType'] == $deviceType)
            {
                return $device['speakerVolume'];
            }
        }

        return false;
    }
    
    private function BehaviorsPreview( array $postfields)
    {
        $url = 'https://alexa.' . $this->GetAmazonURL() . '/api/behaviors/preview';
        $locale = $this->GetLanguage();
        $type = $postfields['type'];

        $operationPayload = $postfields['operationPayload'];
        
        // Extend operation Payload
        $operationPayload['locale'] =  $locale;

        $nodes = array();

        // Get target devices, e.g. single device, multiroom-group members or announcements members

        if ( $type == 'AlexaAnnouncement' )
        {
            // Check for Multiroom-groups and replace them with their clusterMembers
            $this->UpdateDeviceList();
            
            $devices = array();
            foreach ( $operationPayload['target']['devices'] as $device )
            {
                $members = $this->getClusterMembers( $device['deviceSerialNumber'] );
                if ( $members === array() )
                {
                    //Singel device
                    $devices[] = $device;
                }
                else
                {
                    // Add clusterMembers of multiroom-group
                    foreach ($members as $member)
                    {
                        // it is important to rename deviceType to deviceTypeId for announcements to work properly
                        $devices[] = [
                            'deviceSerialNumber'    => $member['deviceSerialNumber'],
                            'deviceTypeId'          => $member['deviceType'],                               
                        ];
                    }
                    
                }
            }

            if ($devices !== array() )
            {
                $operationPayload['target']['devices'] = $devices;
            }
            
            $nodes[] = $this->createNode( $type, $operationPayload);

        }
        elseif ( $type == 'Alexa.Speak' )
        {
            // Check for Multiroom-groups and replace them with their clusterMembers
            $this->UpdateDeviceList();

            $devices = array();
            foreach ( $operationPayload['_devices'] as $device )
            {
                $members = $this->getClusterMembers( $device['deviceSerialNumber'] );
                if ( $members === array() )
                {
                    //Singel device
                    $devices[] = $device;
                }
                else
                {
                    // Add clusterMembers of multiroom-group
                    foreach($members as $member)
                    {
                        // in case device has additional keys (e.g. _setVolume), merge arrays (keys of member will overwrite identical keys in device) 
                        $devices[] = array_merge( $device, $member);
                    }
                }
            }
            unset($operationPayload['_devices']);

            if ( isset($operationPayload['volume']))
            {
                $this->UpdateAllDeviceVolumes();
            }


            foreach( $devices as $device )
            {
    
                if ( $this->GetDevice($device['deviceSerialNumber'], $device['deviceType'] )['online'] == false )
                    continue;

                if ( isset ($operationPayload['volume'] ))
                {
                    // Set new volume for tts
                    $payload = array();
                    $payload['customerId']         = $operationPayload['customerId'];
                    $payload['locale']             = $operationPayload['locale'];
                    $payload['deviceType']         = $device['deviceType'];
                    $payload['deviceSerialNumber'] = $device['deviceSerialNumber'];                    
                    $payload['value']              = $operationPayload['volume'];

                    $nodesSetVolume[] = $this->createNode( 'Alexa.DeviceControls.Volume', $payload);

                    // Reset volume to current value
                    $payload = array();
                    $payload['customerId']         = $operationPayload['customerId'];
                    $payload['locale']             = $operationPayload['locale'];
                    $payload['deviceType']         = $device['deviceType'];
                    $payload['deviceSerialNumber'] = $device['deviceSerialNumber'];  
                    $payload['value']              = $this->GetDeviceVolume($device['deviceSerialNumber'], $device['deviceType']);

                    $nodesResetVolume[] = $this->createNode( 'Alexa.DeviceControls.Volume', $payload);

                }

                $payload = array();
                $payload = $operationPayload;
                //Cleanup payload
                if ( isset($payload['volume'])) 
                    unset( $payload['volume']) ;

                //Set target device 
                $payload['deviceType']         = $device['deviceType'];
                $payload['deviceSerialNumber'] = $device['deviceSerialNumber'];

                $nodesCmd[] = $this->createNode( $type, $payload);
            }

            if (isset($nodesSetVolume))
            {
                $nodes[] = [
                    'sequenceType' => 'ParallelNode',
                    'nodes' => $nodesSetVolume
                ];
            }

            if (isset($nodesCmd))
            {
                $nodes[] = [
                    'sequenceType' => 'ParallelNode',
                    'nodes' => $nodesCmd
                ];
            }

            if (isset($nodesResetVolume))
            {
                $nodes[] = [
                    'sequenceType' => 'ParallelNode',
                    'nodes' => $nodesResetVolume
                ];
            }
        } 
        else 
        {
            $nodes[] = $this->createNode( $type, $operationPayload);
        }


        $startNode = $this->buildSequenceNodeStructure($nodes); 

        $sequence = [
            '@type' => 'com.amazon.alexa.behaviors.model.Sequence',
            'startNode' => $startNode];

        unset($postfields);
        $postfields = [
            'behaviorId' => 'PREVIEW',
            'sequenceJson' => json_encode($sequence),
            'status' => 'ENABLED'];


        if ( IPS_SemaphoreEnter ( 'BehaviorsPreview.'.$this->InstanceID , 6000) )
        {

            $result = $this->SendEcho($url, $postfields);

            // Rate limit for BehaviorsPreview requests: 1 request per 2 seconds
            IPS_Sleep( 2000 );
            IPS_SemaphoreLeave('BehaviorsPreview.'.$this->InstanceID );
        }
        else
        {
            $result = ['http_code' => 502, 'header' => '', 'body' => 'Too many parallel BehaviorsPreview requests.' ];
        }

        return $result;
    }

    private function createNode( string $type, array $operationPayload)
    {
        $node = [
            '@type' => 'com.amazon.alexa.behaviors.model.OpaquePayloadOperationNode',
            'type' => $type,
            'operationPayload' => $operationPayload
        ];

        return $node;
    }

    private function buildSequenceNodeStructure(array $nodes, string $sequenceType = 'SerialNode') 
    {

        $nodeStructure = [];
        foreach ($nodes as $node)
        {
            if (isset($node['nodes']) && isset($node['sequenceType']) ) 
            {
                $nodeStructure[] = $this->buildSequenceNodeStructure($node['nodes'] , $node['sequenceType']) ;
            } else {
                $nodeStructure[] = $node;
            }
        }

        $result = [
            '@type' => 'com.amazon.alexa.behaviors.model.'. $sequenceType,
            'name' => null,
            'nodesToExecute' => $nodeStructure
        ];

        return $result;

    }

    private function BehaviorsPreviewAutomation(array $deviceinfos, array $automation)
    {
        $url = 'https://alexa.' . $this->GetAmazonURL() . '/api/behaviors/preview';

        $postfields = [
                'behaviorId' => $automation['automationId'],
                'sequenceJson' => json_encode($automation['sequence']),
                'status' => 'ENABLED'];

        $postfields = str_replace(
            ['ALEXA_CURRENT_DEVICE_TYPE', 'ALEXA_CURRENT_DSN'], [$deviceinfos['deviceType'], $deviceinfos['deviceSerialNumber']], $postfields
        );
        $utterance = '';

        foreach ($automation['triggers'] as $trigger) {
            if (isset($trigger['payload']['utterance'])) {
                $utterance = $trigger['payload']['utterance'];
            }
            if (empty($automation['name'])) {
                $automation_name = '';
            } else {
                $automation_name = $automation['name'];
            }
        }
        $this->SendDebug('Trigger Automation', 'automation name: ' . $automation_name . ', automation utterance: ' . $utterance, 0);
        return $this->SendEcho($url, $postfields);
    }

    private function GetDNDState()
    {
        $url = 'https://' . $this->GetAlexaURL() . '/api/dnd/device-status-list';

        return $this->SendEcho($url);
    }


    /**
     * build configuration form.
     *
     * @return string
     */
    public function GetConfigurationForm(): string
    {
        // return current form
        return json_encode(
            [
                'elements' => $this->FormElements(),
                'actions' => $this->FormActions(),
                'status' => $this->FormStatus()]
        );
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
                'name' => 'active',
                'type' => 'CheckBox',
                'caption' => 'active'],
            [
                'name' => 'language',
                'type' => 'Select',
                'caption' => 'Echo language',
                'options' => $this->GetEchoLanguageList()],
            [
                'name' => 'refresh_token',
                'type' => 'PasswordTextBox',
                'caption' => 'Refresh-Token'],
            [
                'type' => 'Label',
                'link' => 'true',
                'caption' => 'To generate the refresh token, follow the instructions from: https://github.com/roastedelectrons/IPSymconEchoRemote#einrichtung'],              
            [
                'name' => 'TimerLastAction',
                'type' => 'CheckBox',
                'caption' => 'Get last action'],
            [
                'name' => 'LogMessageEx',
                'type' => 'CheckBox',
                'caption' => 'Extented log messages']

        ];
    }

    private function GetEchoLanguageList(): array
    {
        $options = [
            [
                'caption' => 'Please choose',
                'value' => -1],
            [
                'caption' => 'german',
                'value' => 0],
            [
                'caption' => 'english',
                'value' => 1]];
        return $options;
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
                'type' => 'Label',
                'caption' => 'Test the Registration:'],
            [
                'type' => 'Button',
                'caption' => 'login',
                'onClick' => "if (EchoIO_LogIn(\$id)){echo 'Die Anmeldung war erfolgreich.';} else {echo 'Bei der Anmeldung ist ein Fehler aufgetreten.';}"],
            [
                'type' => 'Button',
                'caption' => 'logoff',
                'onClick' => "if (EchoIO_LogOff(\$id)){echo 'Die Abmeldung war erfolgreich.';} else {echo 'Bei der Abmeldung ist ein Fehler aufgetreten.';}"],
            [
                'type' => 'Button',
                'caption' => 'Login Status',
                'onClick' => "if (EchoIO_CheckLoginStatus(\$id)){echo 'Sie sind angemeldet.';} else {echo 'Sie sind nicht angemeldet.';}"]];

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
                'code' => 214,
                'icon' => 'error',
                'caption' => 'not authenticated.'],
            [
                'code' => 215,
                'icon' => 'error',
                'caption' => 'refresh token must not be empty']];

        return $form;
    }

    /**
     * return incremented position.
     *
     * @return int
     */
    private function _getPosition()
    {
        $this->position++;
        return $this->position;
    }
}
