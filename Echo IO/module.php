<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/EchoBufferHelper.php';
require_once __DIR__ . '/../libs/EchoDebugHelper.php';
require_once __DIR__ . '/../libs/AlexaWebsocket.php';
require_once __DIR__ . '/../libs/VariableProfileHelper.php';

// Modul für Amazon Echo Remote

class AmazonEchoIO extends IPSModule
{
    use IPSymconEchoRemote\EchoBufferHelper;
    use IPSymconEchoRemote\EchoDebugHelper;
    use IPSymconEchoRemote\AlexaWebsocket;
    use IPSymconEchoRemote\VariableProfileHelper;

    private const STATUS_INST_WEBSOCKET_ERROR = 200; // websocket error
    private const STATUS_INST_NOT_AUTHENTICATED = 214; // authentication must be performed.
    private const STATUS_INST_REFRESH_TOKEN_IS_EMPTY = 215; // authentication must be performed.

    private const UserAgentBrowser  = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Safari/605.1.15';
    private const UserAgentApp      = 'AppleWebKit PitanguiBridge/2.2.595606.0-[HARDWARE=iPhone14_7][SOFTWARE=17.4.1][DEVICE=iPhone]';

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
        $this->RegisterPropertyBoolean('TimerLastAction', false);
        $this->RegisterPropertyBoolean('VariablesLastActivity', true);    
        $this->RegisterPropertyBoolean('Websocket', true);
        $this->RegisterPropertyBoolean('LogMessageEx', false);
        $this->RegisterPropertyInteger('UpdateInterval', 60);
        $this->RegisterPropertyInteger('GetLastActivityInterval', 60);

        $this->RegisterAttributeString('devices', '[]');
        $this->RegisterAttributeString( 'LastActivityID', '' ); 
        $this->RegisterAttributeInteger('LastCookieRefresh', 0);
        $this->RegisterAttributeInteger('CookieExpirationDate', 0);
        $this->RegisterAttributeString( 'CsrfToken', '' ); 

        $this->RegisterTimer('RefreshCookie', 0, 'ECHOIO_LogIn(' . $this->InstanceID . ');');
        $this->RegisterTimer('UpdateStatus', 0, 'ECHOIO_UpdateStatus(' . $this->InstanceID . ');');
        $this->RegisterTimer('GetLastActivity', 0, 'ECHOIO_GetLastActivity(' . $this->InstanceID . ');');

        //we will wait until the kernel is ready
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function Destroy() {

        // string profiles
        $this->UnregisterProfile('Echo.LastDevice.'.$this->InstanceID);

        // legacy profiles
        $this->UnregisterProfile('EchoRemote.LastDevice');

        //Never delete this line!
        parent::Destroy();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        // Kernel message
        if ($SenderID == 0)
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

    }



    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->SendDebug(__FUNCTION__, '== started ==', 0);

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        // Migration of Idents
        if ( @$this->GetIDForIdent('last_device') !== false && @$this->GetIDForIdent('LastDevice') === false ) IPS_SetIdent( $this->GetIDForIdent('last_device'), 'LastDevice');
        if ( @$this->GetIDForIdent('cookie_expiration_date') !== false && @$this->GetIDForIdent('CookieExpirationDate') === false  ) IPS_SetIdent( $this->GetIDForIdent('cookie_expiration_date'), 'CookieExpirationDate');

        // Disconnect from websocket client, because it is no longer supported
        $parentID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ( $parentID > 0 )
        {
            $this->wsSetConfiguration(0);
            IPS_DisconnectInstance($this->InstanceID);
            $this->UnregisterMessage($parentID , IM_CHANGESTATUS);
        }


        $this->RegisterVariableInteger('CookieExpirationDate', $this->Translate('Cookie expiration date'), '~UnixTimestamp', 0);


        $active = $this->ReadPropertyBoolean('active');

        if (!$active)
        {
            $this->LogOff();
            $this->SetTimerInterval('RefreshCookie', 0);
            $this->SetTimerInterval('UpdateStatus', 0);
            $this->SetTimerInterval('GetLastActivity', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }



        if ( $this->ReadPropertyString('refresh_token') == "")
        {
            $this->SetStatus(self::STATUS_INST_REFRESH_TOKEN_IS_EMPTY);
            $this->LogMessage( __FUNCTION__ .': refresh token missing'  , KL_ERROR);
            return false;
        } 

        
        if ( !$this->CheckLoginStatus() )
        {
            if ( !$this->LogIn() )
                return;
        }


        // Update devices and get DeviceList
        $this->UpdateDeviceList();

        $this->RegisterVariableProfileLastDevice(); 

        $keep = $this->ReadPropertyBoolean('VariablesLastActivity');
        $this->MaintainVariable('LastDevice', $this->Translate('Last activity: device'), 3, 'Echo.LastDevice.'.$this->InstanceID, 10, $keep); 
        $this->MaintainVariable('LastActivityTimestamp', $this->Translate('Last activity: timestamp'), 1, "~UnixTimestamp", 11, $keep );
        $this->MaintainVariable('LastAction', $this->Translate('Last activity: command'), 3, '', 12, $keep);
        $this->MaintainVariable('LastActivityResponse', $this->Translate('Last activity: response'), 3, "", 13, $keep );
        $this->MaintainVariable('LastActivityIntent', $this->Translate('Last activity: intent'), 3, "", 14, $keep );
        $this->MaintainVariable('LastActivityPerson', $this->Translate('Last activity: person'), 3, "", 15, $keep );

        $interval = $this->ReadPropertyInteger('UpdateInterval') * 1000;

        if ($interval > 0 && $interval < 60000){
            $interval = 60000;
        }

        $this->SetTimerInterval('UpdateStatus', $interval);

        
        if ( $this->ReadPropertyBoolean('TimerLastAction')){
            $interval = $this->ReadPropertyInteger('GetLastActivityInterval') * 1000;
            if ($interval < 3000){
                $interval = 3000;
            }
            $this->SetTimerInterval('GetLastActivity', $interval);
        } else {
            $this->SetTimerInterval('GetLastActivity', 0);
        }  
        
    }

    public function RequestAction($ident, $value)
    {
        switch ($ident) {
            case 'TimerLastAction':
                $this->UpdateFormField('GetLastActivityInterval', 'visible', $value);
                break;
        }
    }


    private function GetCookieByRefreshToken()
    {

        $header = [
            'Connection: keep-alive',
            'x-amzn-identity-auth-domain: api.'.$this->GetAmazonURL() 
        ];

        $url = "https://api.".$this->GetAmazonURL()."/ap/exchangetoken/cookies";
        $this->SendDebug(__FUNCTION__, 'url: ' . $url, 0);

        $post['requested_token_type'] = 'auth_cookies';
        $post['app_name'] = 'Amazon Alexa';
        $post['domain'] = 'www.'.$this->GetAmazonURL();
        $post['source_token_type'] = 'refresh_token';
        $post['source_token'] = $this->ReadPropertyString('refresh_token');

        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);    
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);   
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->GetUserAgent());
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));

        curl_setopt($ch, CURLOPT_URL, $url);

        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        $this->SendDebug(__FUNCTION__ , 'Curl info: ' . json_encode($info), 0);

        if ( curl_errno($ch) ) {
            $this->LogMessage( 'Error in function ' . __FUNCTION__ .' : ' . curl_error($ch) .' ('. curl_errno($ch) . ')' , KL_ERROR);
            $this->SendDebug(__FUNCTION__ , 'Curl error: ' . curl_error($ch) .' ('. curl_errno($ch) . ')', 0);
            return false;
        } 
        
        curl_close($ch);

        $result = $this->getReturnValues($info, $result);

        if ($result['http_code'] == 400) 
        {
            $response = json_decode($result['body']);
            if ($response !== false)
            {
                $this->LogMessage('Error:' . $response->response->error->code.": ".$response->response->error->message, KL_ERROR);
            }
            return false;
        }

        if ($result['http_code'] == 200)
        {
            $cookieTXT = $this->convertJSONtoPlainTxtCookie( $result['body'] );

            if ($cookieTXT != "" ) 
            {
                file_put_contents($this->getCookiesFileName(), $cookieTXT);
                return true;
            }
        }

        return false;
    }

    private function convertJSONtoPlainTxtCookie( string $cookieJSON )
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
            'User-Agent: ' . $this->GetUserAgent(),
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

        return false;

    }

    private function getCookiesFileName()
    {
        return IPS_GetKernelDir() . 'alexa_cookie_'. $this->InstanceID .'.txt';
    }

    private function getCsrfFromCookie()
    {

        $CookiesFileName = $this->getCookiesFileName();

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

        $CookiesFileName = $this->getCookiesFileName();

        if (file_exists($CookiesFileName)) {
            
            $cookie_line = array_values(preg_grep('/\tat-acbde\t/', file($CookiesFileName)));
            if (isset($cookie_line[0])) {
                $expirationDate = preg_split('/\s+/', $cookie_line[0])[4];
            }
        }

        return $expirationDate;

    }

    private function getCsrfToken()
    {
        // csfr-token is needed for customer-history-records requests

        $url = 'https://www.' . $this->GetAmazonURL() . '/alexa-privacy/apd/activity?disableGlobalNav=true&ref=activityHistory';

        $headers[] = 'User-Agent: '. self::UserAgentApp;
        $headers[] = 'DNT: 1';
        $headers[] = 'Connection: keep-alive';
        $headers[] = 'Content-Type: application/json; charset=UTF-8';

        $result = $this->HttpRequest($url, $headers, null, 'GET');

        if ($result['http_code'] == 200){
            $csrfToken = $this->getStringBetween($result['body'], '<meta name="csrf-token" content="',  '" />');

            if ($csrfToken != ''){
                $this->WriteAttributeString('CsrfToken', $csrfToken );
                return true;
            }
        }

        return false;
    }

    private function getStringBetween($string, $start, $end)
    {
        $ini = strpos($string, $start);
        if ($ini == 0) return '';
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    public function LogIn(): bool
    {
        $this->SendDebug(__FUNCTION__, '== started ==', 0);

        $this->SetTimerInterval('RefreshCookie', 0);

        if ( !$this->ReadPropertyBoolean('active') )
        {
            $this->LogMessage( __FUNCTION__ .': EchoIO Instance is inactive'  , KL_ERROR);
            return false;
        }

        $result = $this->GetCookieByRefreshToken();

        if ( !$result )
            return false;

        $result = $this->GetCSRF();

        if ( !$result )
            return false;

        $this->getCsrfToken();
        
        $this->SetValue('CookieExpirationDate', $this->getExpirationDateFromCookie() );
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
        $url = 'https://' . $this->GetAlexaURL() . '/logout';

        $headers = [
            'DNT: 1',
            'Connection: keep-alive']; //the header must not contain any cookie

        $this->HttpRequest($url, $headers);

        $this->SetStatus(self::STATUS_INST_NOT_AUTHENTICATED);
        $this->WriteAttributeInteger('CookieExpirationDate', 0);
        $this->WriteAttributeString('CsrfToken', '');
        return $this->deleteFile($this->getCookiesFileName());
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
            $this->LogMessage( __FUNCTION__ .': EchoIO Instance is inactive'  , KL_ERROR);
            return false;
        }

        $url = 'https://'. $this->GetAlexaURL().'/api/bootstrap?version=0';

        $headers[] = 'User-Agent: ' . self::UserAgentApp;
        $headers[] = 'DNT: 1';
        $headers[] = 'Connection: keep-alive';
        $headers[] = 'Content-Type: application/json; charset=UTF-8';            
        $headers[] = 'Origin: https://' . $this->GetAlexaURL();
        $headers[] = 'csrf: ' . $this->getCsrfFromCookie();

        $result = $this->HttpRequest($url, $headers);

        if ($result['body'] === null) {
            $return = null;
        } else {
            $return = json_decode($result['body'], false);
        }

        if ($return === null) {
            $this->SendDebug(__FUNCTION__, 'Not authenticated (return is null)! ', 0);

            $authenticated = false;
        } elseif (!property_exists($return, 'authentication')) {
            $this->SendDebug(
                __FUNCTION__, 'Not authenticated (property authentication not found)! ' . $result['body'], 0
            );

            $authenticated = false;
        } elseif ($return->authentication->authenticated) {
            //$this->WriteAttributeString('customerID', $return->authentication->customerId); //TEST
            $this->SetBuffer('customerID', $return->authentication->customerId);
            $this->SendDebug(__FUNCTION__, 'CustomerID: ' . $return->authentication->customerId, 0);
            $authenticated = true;
        } else {
            $this->SendDebug(
                __FUNCTION__, 'Not authenticated (property authenticated is false)! ' . $result['body'], 0
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
    private function AlexaApiRequest(string $url, array $postfields = null, string $method = null)
    {

        if ( $this->GetStatus() != 102 )
        {
            $this->SendDebug(__FUNCTION__, 'EchoIO not active. Status: '.$this->GetStatus(), 0);
            //Workaroud since the Echo Device Instances expext an array response to load the Configurationform properly
            return ['http_code' => 502, 'header' => '', 'body' => 'EchoIO not active. Status: '.$this->GetStatus() ];
        }

        $headers[] = 'User-Agent: ' . self::UserAgentApp;
        $headers[] = 'DNT: 1';
        $headers[] = 'Connection: keep-alive';
        $headers[] = 'Content-Type: application/json; charset=UTF-8';            
        $headers[] = 'Origin: https://' . $this->GetAlexaURL();
        $headers[] = 'csrf: ' . $this->getCsrfFromCookie();


        return $this->HttpRequest($url, $headers, $postfields, $method );
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
    private function HttpRequest(string $url, array $header, array $postfields = null, string $type = null)
    {
        $this->SendDebug(__FUNCTION__, 'Header: ' . json_encode($header), 0);

        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 10, //timeout after 6 seconds
            CURLOPT_HEADER => true,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_ENCODING => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1];


        $options[CURLOPT_COOKIEFILE] = $this->getCookiesFileName(); //this file is read


        if ($postfields != null) 
        {
            $this->SendDebug(__FUNCTION__, 'Postfields: ' . json_encode($postfields), 0);
            $options[CURLOPT_POSTFIELDS] = json_encode($postfields);
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
                $this->LogMessage( 'Error in function ' . __FUNCTION__ .' : ' . curl_error($ch) .' ('. curl_errno($ch) . ')' , KL_ERROR);
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

        $returnValues = $this->getReturnValues($info, $result);

        if ($info['http_code'] == 401)
        {
            $this->SetStatus(self::STATUS_INST_NOT_AUTHENTICATED);
        }

        if ($info['http_code'] == 400) 
        {
            $response = json_decode($returnValues['body'], true);

            if (isset($response['message']))
            {
                if ( $response['message'] == 'Rate exceeded'){
                    trigger_error($response['message']);
                } else {
                    if ($this->ReadPropertyBoolean('LogMessageEx') )
                    {
                        $this->LogMessage( __FUNCTION__ .': Bad Request (400): '. $response['message'] , KL_ERROR);
                    }  
                }
            }
        }

        if ($info['http_code'] == 429) 
        {
            trigger_error('Too many requests!');
        }

        return $returnValues;
    }


    private function HttpRequestCookie(string $url, array $header, array $postfields = null): array
    {
        $this->SendDebug(__FUNCTION__, 'url: ' . $url, 0);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);  
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); 
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->getCookiesFileName() ); //this file is read
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->getCookiesFileName() );  //this file is written
        curl_setopt($ch, CURLOPT_USERAGENT, $this->GetUserAgent());
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
            $this->LogMessage( 'Error in function ' . __FUNCTION__ .' : ' . curl_error($ch) .' ('. curl_errno($ch) . ')' , KL_ERROR);
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
            'User-Agent: ' . $this->GetUserAgent(),
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

    private function GetUserAgent()
    {
        return 'AppleWebKit PitanguiBridge/2.2.556530.0-[HARDWARE=iPhone14_7][SOFTWARE=16.6][DEVICE=iPhone]';
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

        $result = $this->AlexaApiRequest($url);

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
        if ( $this->ReadAttributeString('devices') != json_encode($devices) ){

            $this->WriteAttributeString('devices', json_encode($devices));

            // Update Variable Profile for LastDevice if DeviceList has changed
            $this->RegisterVariableProfileLastDevice();
        }
    

        foreach($devices as $device)
        {
            $this->SendDataToChild($device['serialNumber'], $device['deviceType'], 'DeviceInfo', $device );
        }
        
        $result['body'] = json_encode($devices);

        return $result;
    }

    private function getEchoDevices()
    {
        $devices = $this->GetDeviceList();

        $echos = array();

        foreach ( $devices as $device )
        {
            if ( $device['deviceFamily'] == 'ECHO' || $device['deviceFamily'] == 'KNIGHT' || $device['deviceFamily'] == 'ROOK' )
            {
                $echos[] = [
                    'deviceSerialNumber' => $device['serialNumber' ],
                    'deviceType'   => $device['deviceType' ]
                ];
            }
        }
        return $echos;
    }

    private function getClusterMembers( string $serialNumber )
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

        // Workaround to prevent integer overflow of timer: Refresh cookie at least after 2 weeks.
        if ($refreshInterval > 3600*24*14)
        {
            $refreshInterval = 3600*24*14;
        }

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


    private function RegisterVariableProfileLastDevice()
    {
        $devices = $this->GetDeviceList();
        $deviceAssociation = [];

        foreach ($devices as $key => $device) {
            $deviceAssociation[] = [$device['serialNumber'], $device['accountName'], '', -1];
        }

        $this->RegisterProfileAssociation('Echo.LastDevice.'.$this->InstanceID, '', '', '', 0, 0, 0, 0, VARIABLETYPE_STRING, $deviceAssociation);
            
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


    public function GetLastActivity()
    {

        if ( IPS_SemaphoreEnter ( 'GetLastActivity.'.$this->InstanceID , 2500) )
        {
            // Throttle requests due to rate limit
            $delay = microtime(true) - floatval( $this->GetBuffer( 'GetLastActivityRequestTimestamp' ));

            if ( $delay < 2.5)
            {
                $this->SendDebug(__FUNCTION__, 'waiting', 0);
                IPS_Sleep(2500 - $delay*1000);
            }

            $startTime =intval( $this->GetBuffer('LastActivityTimestamp') );
            $endTime = (time() + 60*60)*1000;
    
            $result = $this->GetCustomerHistoryRecords($startTime, $endTime);

            $this->SetBuffer( 'GetLastActivityRequestTimestamp', microtime(true));
            IPS_SemaphoreLeave ( 'GetLastActivity.'.$this->InstanceID );
        } 
        else
        {
            return [];
        }
        
        $lastActivity = [];

        if (is_array($result) && isset($result['customerHistoryRecords']) )
        {
            foreach ($result['customerHistoryRecords'] as $activity)
            {
                if ( isset($activity['utteranceType']) && in_array($activity['utteranceType'], array('GENERAL')) ) // 'ROUTINES_OR_TAP_TO_ALEXA'
                {
                    $ids = explode('#', $activity['recordKey']); //0:customerID, 1:timestamp, 2:deviceType, 2:deviceSerial
                    $lastActivity['id'] =  $activity['recordKey'];
                    $lastActivity['timestamp'] =  round( ($activity['timestamp'] / 1000), 3);
                    $lastActivity['timestampMilliseconds'] = $activity['timestamp'];
                    $lastActivity['deviceType'] =  $ids[2];
                    $lastActivity['serialNumber'] =  $ids[3];
                    $lastActivity['deviceName'] = $activity['device']['deviceName'];
                    $lastActivity['utteranceType'] = $activity['utteranceType'];
                    $lastActivity['domain'] = $activity['domain'];
                    $lastActivity['intent'] = $activity['intent'];
                    $lastActivity['utterance'] = '';
                    $lastActivity['response'] = '';
                    $lastActivity['person']  = '';
                    $lastActivity['instanceID']  = $this->GetInstanceIDBySerialNumber($lastActivity['serialNumber'], $lastActivity['deviceType']);

                    foreach($activity['voiceHistoryRecordItems'] as $recordItem) {

                        if ($recordItem['recordItemType'] == 'CUSTOMER_TRANSCRIPT' || $recordItem['recordItemType'] == 'ASR_REPLACEMENT_TEXT') {
                            $lastActivity['utterance'] = $recordItem['transcriptText'];
                        }

                        if ($recordItem['recordItemType'] == 'ALEXA_RESPONSE' || $recordItem['recordItemType'] == 'TTS_REPLACEMENT_TEXT') {
                            $lastActivity['response'] .= $recordItem['transcriptText'] . ' ';
                        }
                    }

                    if (isset($activity['personsInfo'][0]['personFirstName'])) {
                        $lastActivity['person'] = $activity['personsInfo'][0]['personFirstName'];
                    }
                    
                    break;
                }

            }

        }

        if ($lastActivity != [])
        {
            if ( $lastActivity['id'] != $this->ReadAttributeString( 'LastActivityID' ) )
            {
                $this->SetValueEx('LastDevice', $lastActivity['serialNumber'] );
                $this->SetValueEx('LastAction', $lastActivity['utterance'] );
                $this->SetValueEx('LastActivityTimestamp', intval($lastActivity['timestamp']) );
                $this->SetValueEx('LastActivityIntent', $lastActivity['intent'] );
                $this->SetValueEx('LastActivityResponse', $lastActivity['response'] );
                $this->SetValueEx('LastActivityPerson', $lastActivity['person'] );
                $this->SendDataToChild( $lastActivity['serialNumber'] , $lastActivity['deviceType'] , 'LastAction', $lastActivity);
                $this->WriteAttributeString( 'LastActivityID', $lastActivity['id']);        
                $this->SetBuffer('LastActivityTimestamp',  $lastActivity['timestampMilliseconds']);        
            }

            if (@$this->GetValue('LastActivityPerson') != $lastActivity['person'])
            {
                $this->SetValueEx('LastActivityPerson', $lastActivity['person'] );
            }
        }

        return $lastActivity;
    }

    private function GetInstanceIDBySerialNumber( $serialNumber, $deviceType)
    {
        foreach (IPS_GetInstanceListByModuleID('{496AB8B5-396A-40E4-AF41-32F4C48AC90D}') as $instanceID)  // Echo Remote Devices
        {
            if (IPS_GetInstance($instanceID)['ConnectionID'] === $this->InstanceID) {
                if (IPS_GetProperty($instanceID, 'Devicetype') == $deviceType && IPS_GetProperty($instanceID, 'Devicenumber') && $serialNumber ) {
                    return $instanceID;
                }
            }
        }
        return 0;
    }

    public function GetCustomerHistoryRecords( int $startTime, int $endTime)
    {
        if ( $this->GetStatus() != 102 )
        {
            $this->SendDebug(__FUNCTION__, 'EchoIO not active. Status: '.$this->GetStatus(), 0);
            return false;
        }

        $csrfToken = $this->ReadAttributeString('CsrfToken');

        if ($csrfToken == ''){
            $this->getCsrfToken();
            $csrfToken = $this->ReadAttributeString('CsrfToken');
        }

        //$url = 'https://www.'. $this->GetAmazonURL() .'/alexa-privacy/apd/rvh/customer-history-records-v2?startTime='. $startTime .'&endTime='. $endTime .'&disableGlobalNav=false';
        $url = 'https://www.'. $this->GetAmazonURL() .'/alexa-privacy/apd/rvh/customer-history-records-v2/?startTime='. $startTime .'&endTime='. $endTime .'&pageType=VOICE_HISTORY';

        $headers = array();
        $headers[] = 'Content-Type: application/json;charset=utf-8';
        $headers[] = 'Accept: application/json, text/plain, */*';
        $headers[] = 'Accept-Language: '.$this->GetLanguage();
        $headers[] = 'Origin: https://www.' . $this->GetAmazonURL();
        $headers[] = 'User-Agent: '. self::UserAgentApp;
        $headers[] = 'authority: https://www.' . $this->GetAmazonURL();
        $headers[] = 'referer: https://www.' . $this->GetAmazonURL() . '/alexa-privacy/apd/activity?disableGlobalNav=true&ref=activityHistory';
        $headers[] = 'anti-csrftoken-a2z: ' . $csrfToken;

        $postfields['previousRequestToken'] = null;

        $result = $this->HttpRequest($url, $headers, $postfields, 'POST');

        if (isset($result['http_code']) && $result['http_code'] == 403) {
            // invalid csrf-token
            trigger_error('403 Forbidden - csrf-token invalid(?)');
            $this->WriteAttributeString('CsrfToken', '');
        }

        if (isset($result['http_code']) && $result['http_code'] == 200) {
            return json_decode($result['body'], true);
        }
 
        return false;
    }

    public function CustomCommand(string $url, array $postfields = null, string $method = null)
    {
        $url = str_replace(['{AlexaURL}', '{AmazonURL}'], [$this->GetAlexaURL(), $this->GetAmazonURL()], $url);

        return $this->AlexaApiRequest($url, $postfields, $method);
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


    /** Receive data from children and forward to api
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

            case 'AlexaApiRequest':
                $url = 'https://'. $this->GetAlexaURL() . $data['Payload']['url'];
                $method = '';
                $postfields = null;
                if ( isset($data['Payload']['method'])) $method = $data['Payload']['method'];
                if ( isset($data['Payload']['postfields'])) $postfields = $data['Payload']['postfields'];

                $result = $this->AlexaApiRequest($url, $postfields, $method);
                break;

            case 'BehaviorsPreview':
                $result = $this->BehaviorsPreview($payload);
                break;

            case 'BehaviorsPreviewAutomation':
                $result = $this->BehaviorsPreviewAutomation( $payload['device'], $payload['automation'] );
                break;

            case 'CustomCommand':
                $result = $this->CustomCommand( $payload['url'] , $payload['postfields'], $payload['method']);
                break;

            case 'GetDevices':
                $result = $this->UpdateDeviceList();
                break;

            case 'GetDeviceList':
                $result = $this->GetDeviceList();
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

            case 'UpdateAutomations':
                $url = 'https://'. $this->GetAlexaURL() . $data['Payload']['url'];
                $method = '';
                $postfields = null;
                if ( isset($data['Payload']['method'])) $method = $data['Payload']['method'];
                if ( isset($data['Payload']['postfields'])) $postfields = $data['Payload']['postfields'];

                $result = $this->AlexaApiRequest($url, $postfields, $method);

                if ($result['http_code'] === 200) {
                    $this->SendDataToChild('ALL_DEVICES', 'ALL_DEVICE_TYPES', 'Automations', $result['body'] );
                }

                break;

            default:
                trigger_error('Type \'' . $data['Type'] . '\' not yet supported');
                return false;
        }

        $ret = json_encode($result);
        $this->SendDebug(__FUNCTION__, 'Return: ' . strlen($ret) . ' Zeichen', 0);
        return $ret;
    }

    // Receive data from websocket
    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);

        $msg = $this->wsDecodeMessageAH($data->Buffer);

        // Handshake
        if ($msg['service'] == 'TUNE')
        {
            // Method 1
            if ($msg['content']['protocolName'] == 'A:H')
            {
                $this->wsSendMessage( $this->encodeWSHandshake() );
                IPS_Sleep( 100 );
    
                $this->wsSendMessage( $this->encodeGWHandshake() );
                IPS_Sleep( 100 );
    
                $this->wsSendMessage( $this->encodeGWRegisterAH() );   
            }

             // Method 2
             if ($msg['content']['protocolName'] == 'A:F')
             {
                 $this->wsSendMessage( $this->encodeWSHandshake() );
                 IPS_Sleep( 100 );
     
     
                 $this->wsSendMessage( $this->encodeGWRegisterAF() );   
             }

        }

        if ($msg['service'] == 'FABE')
        {
            if (isset ($msg['content']['payload']['command']))
            {
                switch ( $msg['content']['payload']['command'] )
                {
                    case 'PUSH_ACTIVITY':
                        $this->RegisterOnceTimer("GetLastActivityTimer", 'ECHOIO_GetLastActivity(' . $this->InstanceID . ');' );
                        break;
                }
            }
        }

        return true;

        //$this->SendDataToChildren(json_encode(['DataID' => '{26039E09-3FE0-A12C-D4F3-D68BCF46A884}', 'Buffer' => $data->Buffer]));
    }

    public function GetConfigurationForParent()
    {
        $config = $this->wsGetConfiguration( 1 );

        return json_encode($config, JSON_UNESCAPED_SLASHES);
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

        $result = $this->AlexaApiRequest($url); 
        
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

    private function GetDeviceVolume(string $deviceSerial, string $deviceType )
    {

        $deviceVolumes = json_decode( $this->GetBuffer('DeviceVolumes'), true);

        if ($deviceVolumes == null)
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
        $locale = $this->GetLanguage();
        $type = $postfields['type'];

        $operationPayload = $postfields['operationPayload'];
        if ( isset($postfields['skillId']) )
        {
            $skillId = $postfields['skillId'];
        }
        else
        {
            $skillId = '';
        }

        $nodes = array();
        $nodeType = 'SerialNode';

        // Get target devices, e.g. single device, multiroom-group members or announcements members

        if ( $type == 'AlexaAnnouncement' )
        {
            // Check for Multiroom-groups and replace them with their clusterMembers
            $this->UpdateDeviceList();
            
            $devices = array();
            if ( isset($operationPayload['target']['devices'] ))
            {
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
            }
            
            $nodes[] = $this->createNode( $type, $operationPayload, $skillId);

        }
        elseif ( $type == 'Alexa.Speak' )
        {
            // Check for Multiroom-groups and replace them with their clusterMembers
            $this->UpdateDeviceList();

            $devices = array();

            $targetDevices = $postfields['devices'];
            if ( $targetDevices == 'ALL_DEVICES' )
            {
                $targetDevices = $this->getEchoDevices();
            }

            foreach ( $targetDevices as $device )
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

            if ( isset($postfields['volume']))
            {
                $this->UpdateAllDeviceVolumes();
                $volume = $postfields['volume'];
            }


            foreach( $devices as $device )
            {
    
                if ( $this->GetDevice($device['deviceSerialNumber'], $device['deviceType'] )['online'] == false )
                    continue;

                if ( isset ($volume ))
                {
                    // Set new volume for tts
                    $payload = array();
                    $payload['customerId']         = $operationPayload['customerId'];
                    $payload['locale']             = $operationPayload['locale'];
                    $payload['deviceType']         = $device['deviceType'];
                    $payload['deviceSerialNumber'] = $device['deviceSerialNumber'];                    
                    $payload['value']              = $volume;

                    $nodesSetVolume[] = $this->createNode( 'Alexa.DeviceControls.Volume', $payload, $skillId);

                    // Reset volume to current value
                    $payload = array();
                    $payload['customerId']         = $operationPayload['customerId'];
                    $payload['locale']             = $operationPayload['locale'];
                    $payload['deviceType']         = $device['deviceType'];
                    $payload['deviceSerialNumber'] = $device['deviceSerialNumber'];  
                    $payload['value']              = $this->GetDeviceVolume($device['deviceSerialNumber'], $device['deviceType']);

                    $nodesResetVolume[] = $this->createNode( 'Alexa.DeviceControls.Volume', $payload, $skillId);

                }

                $payload = array();
                $payload = $operationPayload;

                //Set target device 
                $payload['deviceType']         = $device['deviceType'];
                $payload['deviceSerialNumber'] = $device['deviceSerialNumber'];

                $nodesCmd[] = $this->createNode( $type, $payload, $skillId);
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
            // All other command types 

            $nodes[] = $this->createNode( $type, $operationPayload, $skillId);

            if ( isset($operationPayload['deviceSerialNumber']) )
            {
                $members = $this->getClusterMembers( $operationPayload['deviceSerialNumber'] );

                if ( $members !== array() )
                {
                    $nodes = array();
                    // In case of Multiroom Group, send the command only to one groupmember
                    foreach ($members as $member)
                    {
                        $operationPayload['deviceSerialNumber'] = $member['deviceSerialNumber'];
                        $operationPayload['deviceType'] = $member['deviceType'];
                        $nodes[] = $this->createNode( $type, $operationPayload, $skillId);
                        break;
                    }
                    
                }
            } 

            $nodeType = 'ParallelNode';
        }


        $startNode = $this->buildSequenceNodeStructure($nodes, $nodeType); 

        $sequence = [
            '@type' => 'com.amazon.alexa.behaviors.model.Sequence',
            'startNode' => $startNode
        ];

        $sequenceJson = json_encode($sequence);

        // Replace placeholder
        $sequenceJson = str_replace( 'ALEXA_CURRENT_LOCALE', $this->GetLanguage(), $sequenceJson);

        $automation = [
            'behaviorId' => 'PREVIEW',
            'sequenceJson' => $sequenceJson,
            'status' => 'ENABLED'
        ];
        
        $result = $this->RunBehavior($automation);

        return $result;
    }

    private function createNode( string $type, array $operationPayload, string $skillId)
    {
        $node = [
            '@type' => 'com.amazon.alexa.behaviors.model.OpaquePayloadOperationNode',
            'type' => $type,
            'operationPayload' => $operationPayload
        ];

        if ($skillId != '')
        {
            $node['skillId'] = $skillId;
        }

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

    private function RunBehavior( array $automation )
    {
        $url = 'https://alexa.' . $this->GetAmazonURL() . '/api/behaviors/preview';

        $postfields['behaviorId'] = $automation['behaviorId'];
        $postfields['sequenceJson'] = $automation['sequenceJson'];
        $postfields['status'] = 'ENABLED';

        if ( IPS_SemaphoreEnter ( 'RunBehavior.'.$this->InstanceID , 10000) )
        {

            // Throttle requests due to rate limit
            $delay = microtime(true) - floatval( $this->GetBuffer( 'RunBehaviorRequestTimestamp' ));

            if ( $delay < 3.0)
            {
                IPS_Sleep(3000 - $delay*1000);
            }


            $this->SendDebug(__FUNCTION__, $postfields, 0);
            $result = $this->AlexaApiRequest($url, $postfields);

            if ($result['http_code'] == 429 )
            {
                // Rate limit for BehaviorsPreview requests: wait 2.5s and try again
                IPS_Sleep( 3000 );
                $this->SendDebug(__FUNCTION__, $postfields, 0);
                $result = $this->AlexaApiRequest($url, $postfields);
            }

            $this->SetBuffer( 'RunBehaviorRequestTimestamp', (string) microtime(true) );

            IPS_SemaphoreLeave('RunBehavior.'.$this->InstanceID );
        }
        else
        {
            $result = ['http_code' => 502, 'header' => '', 'body' => 'Too many parallel requests.' ];
        }

        if ( $result['http_code'] != 200 )
        {
            trigger_error($result['body']);
        }

        return $result;
    }

    private function BehaviorsPreviewAutomation(array $deviceinfos, array $automation)
    {
        $postfields = [
                'behaviorId' => $automation['automationId'],
                'sequenceJson' => json_encode($automation['sequence']),
                'status' => 'ENABLED'
            ];

        $postfields = str_replace(
            ['ALEXA_CURRENT_DEVICE_TYPE', 'ALEXA_CURRENT_DSN'], [$deviceinfos['deviceType'], $deviceinfos['deviceSerialNumber']], $postfields
        );

        return $this->RunBehavior( $postfields);
    }

    private function GetDNDState()
    {
        $url = 'https://' . $this->GetAlexaURL() . '/api/dnd/device-status-list';

        return $this->AlexaApiRequest($url);
    }

    private function SetValueEx( $ident, $value)
    {
        if(@$this->GetIDForIdent($ident) > 0)
        {
            $this->SetValue($ident, $value);
        }    
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
                'caption' => 'To generate the refresh token, follow the instructions on: https://github.com/roastedelectrons/IPSymconEchoRemote#einrichtung'],              
            [
                'name' => 'VariablesLastActivity',
                'type' => 'CheckBox',
                'caption' => 'setup variables for last activity'],
            [
                'type' => 'RowLayout',
                'items' => [
                    [
                        'name' => 'TimerLastAction',
                        'type' => 'CheckBox',
                        'caption' => 'Update last activity periodically',
                        'width' => '400px',
                        'onChange' => 'IPS_RequestAction($id, "TimerLastAction", $TimerLastAction);'
                    ],
                    [
                        'name'    => 'GetLastActivityInterval',
                        'type'    => 'NumberSpinner',
                        'caption' => 'Interval',
                        'suffix'  => 'seconds',
                        'minimum' => 3,
                        'width'   => '150px',
                        'visible' => $this->ReadPropertyBoolean('TimerLastAction')
                    ]
                ]
            ],

            [
                'type'    => 'ExpansionPanel',
                'caption' => 'Expert settings',
                'expanded' => false,
                'items'   => [   
                    [
                        'name'    => 'UpdateInterval',
                        'type'    => 'NumberSpinner',
                        'caption' => 'Update interval',
                        'suffix'  => 'seconds',
                        'minimum' => 60
                    ],
                    [
                        'name' => 'LogMessageEx',
                        'type' => 'CheckBox',
                        'caption' => 'Extented log messages'
                    ]
                ]
            ]

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
                'type' => 'Button',
                'caption' => 'login',
                'onClick' => "if (EchoIO_LogIn(\$id)){echo '".$this->Translate('Login was successful')."';} else {echo '".$this->Translate('An error has occurred. See the message log for more information.')."';}"],
            [
                'type' => 'Button',
                'caption' => 'logoff',
                'onClick' => "if (EchoIO_LogOff(\$id)){echo '".$this->Translate('Logout was successful')."';} else {echo '".$this->Translate('An error has occurred. See the message log for more information.')."';}"],
            [
                'type' => 'Button',
                'caption' => 'Login Status',
                'onClick' => "if (EchoIO_CheckLoginStatus(\$id)){echo '".$this->Translate('You are logged in')."';} else {echo '".$this->Translate('You are not logged in')."';}"]];

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
                'code' => self::STATUS_INST_WEBSOCKET_ERROR,
                'icon' => 'error',
                'caption' => 'Websocket can not connect'],            
            [
                'code' => 214,
                'icon' => 'error',
                'caption' => 'Not authenticated! See message log for more information.'],
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
