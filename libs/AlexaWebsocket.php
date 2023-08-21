<?php
/*
* Alexa Websocket class
*
* implementation base on:
* https://github.com/Apollon77/alexa-remote/blob/master/alexa-wsmqtt.js
*
*/

trait AlexaWebsocket
{
    protected $protocol = "A:H";
    protected $messageID = 0;
    protected $macDms = [];

    protected function wsSendMessage( $msg, $binary = false )
    {
        $this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => $msg]));
        $this->messageID++;
    }


    protected function wsGetInstanceID()
    {
        return IPS_GetInstance($this->InstanceID)['ConnectionID'];
    }


    protected function wsGetConfiguration( int $method = 1)
    {
        $header = $this->wsGetHeader($method);

        foreach( $header as $name=>$value)
        {
            $headerProperty[] = [
                'Name' => $name,
                'Value' => $value
            ];
        }
        $config['Active'] = $this->ReadPropertyBoolean('active');
        $config['URL'] = $this->wsGetUrl($method);
        $config['Headers'] = json_encode($headerProperty, JSON_UNESCAPED_SLASHES);

        return $config;        
    }

    protected function wsSetConfiguration( int $method = 1 )
    {
        $config = $this->wsGetConfiguration( $method);

        $wsID = $this->wsGetInstanceID();

        if ( $wsID > 0)
        {
            /* @ Symcon Module-Store Review:
                Die Verwendung von IPS_SetProperty ist in diesem Anwendungsfall notwendig und abgesprochen.
                Wenn das Cookie nicht mehr gültig ist, muss ein aktuelles Cookie durch das Modul in den Header geschrieben werden.
                Die entsprechenden Eigenschaften sind auch über GetConfigurationForParent im EchoIO für Eingaben durch den Nutzer gesperrt.
            */
            IPS_SetProperty($wsID, 'Active', $config['Active']);
            IPS_SetProperty($wsID, 'URL', $config['URL']);
            IPS_SetProperty($wsID, 'Headers', $config['Headers']);
            IPS_ApplyChanges($wsID);
        }
    }

    protected function wsGetUrl( int $method = 1)
    {
        if ($method == 1)
        {
            $accountSerial = $this->wsGetValueFromCookie( 'ubid-acbde' );
            $url = 'wss://dp-gw-na.'.$this->GetAmazonURL().'/?x-amz-device-type=ALEGCNGL9K0HM&x-amz-device-serial='.$accountSerial.'-'. time() ; // url has to be unique, therefore add timestamp
        }
        else 
        {
            $url = 'wss://dp-gw-na.'.$this->GetAmazonURL().'/tcomm/';
        }

        return $url;
    }

    protected function wsGetHeader( int $method = 1)
    {
        if ($method ==1)
        {
            $header = [
                //'Host'          => 'dp-gw-na.'.$amazonUrl,  // Will be set by IP-Symcon
                //'Connection'    => 'keep-alive, Upgrade',   // Will be set by IP-Symcon
                //'Upgrade'       => 'websocket',             // Will be set by IP-Symcon
                'Origin'        => 'https://alexa.'.$this->GetAmazonURL(),
                'Pragma'        =>'no-cache',
                'Cache-Control' => 'no-cache',
                'Cookie'       =>  $this->wsGetCookie()   
            ];
        }
        else
        {
            $header = [
                //'Host'          => 'dp-gw-na.'.$amazonUrl,  // Will be set by IP-Symcon
                //'Connection'    => 'keep-alive, Upgrade',   // Will be set by IP-Symcon
                //'Upgrade'       => 'websocket',             // Will be set by IP-Symcon
                'Origin'        => 'https://alexa.'.$this->GetAmazonURL(),
                'Cookie'       =>  $this->wsGetCookie(),    
                'Pragma'        =>'no-cache',
                'Cache-Control' => 'no-cache',
                'x-dp-comm-tuning' => 'A:F;A:H',
                'x-dp-reason' => 'ClientInitiated;1',
                'x-dp-tcomm-purpose' => 'Regular',
                'x-dp-obfuscatedBssid' => '-2019514039',
                'x-dp-tcomm-versionName' => '2.2.443692.0',
                'x-adp-signature' => $this->wsCreateRequestSignature('GET', '/tcomm/', ''),
                'x-adp-token' => $this->macDms['adp_token'],
                'x-adp-alg'=> 'SHA256WithRSA:1.0'
            ];
        }

        return $header;
    }


    protected function wsGetValueFromCookie( $name )
    {
        $CookiesFileName = $this->getCookiesFileName();

        if (file_exists($CookiesFileName)) {
            //get CSRF from cookie file
            $cookie_line = array_values(preg_grep('/\t'.$name.'\t/', file($CookiesFileName)));
            if (isset($cookie_line[0])) {
                $value = preg_split('/\s+/', $cookie_line[0])[6];
                return $value;
            }
        }

        return false;

    }

    protected function wsGetCookie()
    {

        $CookiesFileName = $this->getCookiesFileName();

        $cookie = "";
        if (file_exists($CookiesFileName)) {
            foreach( file($CookiesFileName) as $line)
            {
                $line = rtrim( $line);
                if (substr($line, 0,1) == "#"   && substr($line, 0,9) != '#HttpOnly' ) continue;
                $parts = explode("\t", $line);
                if ( count( $parts) < 7 ) continue;
                

                $parts[6] = str_replace('"', '', $parts[6]);

                if ($cookie != "") $cookie .= '; ';

                if ($parts[5] == 'session-id') {
                    $cookie .= 'session-id-time='.$parts[4].'; ';
                }
                $cookie .= $parts[5].'='.$parts[6];
            }
            return $cookie;
        }
        return false;

    }

    protected function wsCreateRequestSignature ($method, $path, $body)
    {
        $adp_token = $this->macDms['adp_token'];
        $device_private_key = $this->macDms['device_private_key'];

        $now = new DateTime('now', new DateTimeZone('UTC'));
        $date = $now->format('Y-m-d\TH:i:s').'Z'; //datetime.datetime.utcnow().isoformat("T") + "Z"
        //echo "\nDate:\n".$date;
        $body = utf8_encode($body);
        $data = $method."\n".$path."\n".$date."\n".$body."\n".$adp_token;
        
        $private_key_string = "-----BEGIN RSA PRIVATE KEY-----\n".$device_private_key."\n-----END RSA PRIVATE KEY-----";

        //if ($private_key_string !== utf8_encode($private_key_string)) echo "\nDifferent encoding\n";
        //if ($data !== utf8_encode($data)) echo "\n Different encoding\n";
        //echo "\n===DATA START\n".$data."\n===DATA END";

        $privateKey = openssl_pkey_get_private( $private_key_string );

        openssl_sign( $data, $signature, $privateKey, 'sha256WithRSAEncryption' ); //OPENSSL_ALGO_SHA256

        $signature_encoded = base64_encode($signature);
        
        return $signature_encoded.':'.$date;
        //print_r(openssl_get_md_methods() );
        //openssl_sign('test', 'key');
    }

    protected function encodeWSHandshake( )
    {
        // 0xa6f6a951 0x0000009c {"protocolName":"A:H","parameters":{"AlphaProtocolHandler.receiveWindowSize":"16","AlphaProtocolHandler.maxFragmentSize":"16000"}}TUNE

        if ($this->protocol == "A:F") 
            $msg = '0xfe88bc52 0x0000009c {"protocolName":"A:F","parameters":{"AlphaProtocolHandler.receiveWindowSize":"16","AlphaProtocolHandler.maxFragmentSize":"16000"}}TUNE'; 
        else 
            $msg = '0xa6f6a951 0x0000009c {"protocolName":"A:H","parameters":{"AlphaProtocolHandler.receiveWindowSize":"16","AlphaProtocolHandler.maxFragmentSize":"16000"}}TUNE';
        
        return $msg;
    }

    
    protected function encodeGWHandshake()
    {
        // MSG 0x00000361 0x00000001 f 0x00000001 0x4d6fede7 0x0000009b INI 0x00000003 1.0 0x00000024 3fae56ab-5022-bfa9-c381-c90a6eb555ad 0x00000186daff5cf0 END FABE

        $messageid = $this->messageID;

        $messageidHex =  $this->encodeNumber( $messageid, 8);
        if ($this->protocol == "A:F") 
            $msg = 'MSG 0x00000361 '.$messageidHex.' f 0x00000001 '; 
        else 
            $msg = 'MSG 0x00000361 '.$messageidHex.' f 0x00000001 ';

        $idx1 = strlen($msg);
        $msg .= '0x00000000 '; // checksum, will be replaced later
        $idx2 = strlen($msg);

        $msg .= '0x0000009b '; // Content length
        $msg .= 'INI 0x00000003 1.0 0x00000024 '; // Content
        $msg .= $this->uuid4() .' '; // UUID
        $msg .= $this->encodeNumber( time()*1000, 16); // Time
        $msg .= ' END FABE';

        $checksum = $this->encodeNumber( $this->computeChecksum($msg, $idx1, $idx2), 8);
        /*
        echo "\nidx:".$idx;
        echo "\nidx2:".$idx2;
        echo "\nmsg:". $msg;
        echo "\nChecksum:".computeChecksum($msg, $idx, $idx2)."\n";
        */
        $msg = substr_replace($msg, $checksum, $idx1, strlen($checksum) );
        return $msg;
    }

    protected function encodeGWRegisterAH() 
    {
        //MSG 0x00000362 0x00000002 f 0x00000001 0xf4004fed 0x00000109 GWM MSG 0x0000b479 0x0000003b urn:tcomm-endpoint:device:deviceType:0:deviceSerialNumber:0 0x00000041 urn:tcomm-endpoint:service:serviceName:DeeWebsiteMessagingService {"command":"REGISTER_CONNECTION"}FABE
        $messageid = $this->messageID;

        $msg = 'MSG 0x00000362 '; // Message-type and Channel = GW_CHANNEL;
        $msg .= $this->encodeNumber($messageid, 8) . ' f 0x00000001 ';

        $idx1 = strlen($msg);
        $msg .= '0x00000000 '; // checksum, will be replaced later
        $idx2 = strlen($msg);

        $msg .= '0x00000109 '; // length content
        $msg .= 'GWM MSG 0x0000b479 0x0000003b urn:tcomm-endpoint:device:deviceType:0:deviceSerialNumber:0 0x00000041 urn:tcomm-endpoint:service:serviceName:DeeWebsiteMessagingService {"command":"REGISTER_CONNECTION"}FABE';

        $checksum = $this->encodeNumber( $this->computeChecksum($msg, $idx1, $idx2), 8);
        $msg = substr_replace($msg, $checksum, $idx1, strlen($checksum) );
        return $msg;        
    }


    protected function encodeGWRegisterAF() 
    {
        //pubrelBuf = new Buffer('MSG 0x00000362 0x0e414e46 f 0x00000001 0xf904b9f5 0x00000109 GWM MSG 0x0000b479 0x0000003b urn:tcomm-endpoint:device:deviceType:0:deviceSerialNumber:0 0x00000041 urn:tcomm-endpoint:service:serviceName:DeeWebsiteMessagingService {"command":"REGISTER_CONNECTION"}FABE');

        $messageid = $this->messageID;

        $msg = 'MSG'; 
        $msg .= pack('N', 0x00000362); // N	vorzeichenloser Long-Typ (immer 32 Bit, Byte-Folge Big-Endian)
        $msg .= pack('N', $messageid);
        $msg .= 'f'; 
        $msg .= pack('N', 0x00000001);

        $idx1 = strlen($msg);
        $msg .= pack('N', 0x00000000); // checksum, will be replaced later
        $idx2 = strlen($msg);

        $msg .= pack('N', 0x000000e4); // length content
        $msg .= 'GWM MSG 0x0000b479 0x0000003b urn:tcomm-endpoint:device:deviceType:0:deviceSerialNumber:0 0x00000041 urn:tcomm-endpoint:service:serviceName:DeeWebsiteMessagingService {"command":"REGISTER_CONNECTION"}FABE';
        /*
        echo "\nid1x:".$idx1;
        echo "\nidx2:".$idx2;
        echo "\nmsg:". $msg;
        echo "\nmsg-len:". strlen($msg);
        echo "\nChecksum:".computeChecksum($msg, $idx1, $idx2)."\n";
        */
        
        $checksum = pack('N', $this->computeChecksum($msg, $idx1, $idx2));
       // echo "\n".bin2hex($msg);
        $msg = substr_replace($msg, $checksum, $idx1, strlen($checksum) );
        //echo "\n".bin2hex($msg)."\n";
        return trim( $msg) ;        
    }

    protected function readHex(string $data, int $index,int $length) {
        $str = substr($data, $index, $length);
        return hexdec($str);
    }

    protected function wsDecodeMessageAH($data) {

        $idx = 0;
        $message = [];
        $message['service'] = substr($data, -4);

        if ($message['service'] === 'TUNE' )//'TUNE') 
        {
            $message['checksum'] = $this->readHex($data, $idx, 10);
            $idx += 11; // 10 + delimiter;
            $contentLength = $this->readHex($data, $idx, 10);
            $idx += 11; // 10 + delimiter;
            $message['content'] = substr($data, $idx, -4);
            if ( substr( $message['content'], 0, 1) == '{' && substr( $message['content'], -1, 1) == '}') 
            {
                if ( json_decode($message['content'], true) != null )
                {
                    $message['content'] = json_decode($message['content'], true);
                }
            }
        }
        
        elseif ($message['service'] === 'FABE') {
            $message['messageType'] = substr($data, $idx, 3);      
            $idx += 4;
            $message['channel'] = $this->readHex($data, $idx, 10);
            $idx += 11; // 10 + delimiter;
            $message['messageId'] = $this->readHex($data, $idx, 10);
            $idx += 11; // 10 + delimiter;
            $message['moreFlag'] = substr($data, $idx, 1);
            $idx += 2; // 1 + delimiter;
            $message['seq'] = $this->readHex($data, $idx, 10);
            $idx += 11; // 10 + delimiter;
            $message['checksum'] = $this->readHex($data, $idx, 10);
            $idx += 11; // 10 + delimiter;

            //const contentLength = readHex(idx, 10);
            $idx += 11; // 10 + delimiter;

            $message['content'] = [];
            $message['content']['messageType'] = substr($data, $idx, 3);
            $idx += 4;
            
            if ($message['channel'] === 0x361) { // GW_HANDSHAKE_CHANNEL
                if ($message['content']['messageType'] === 'ACK') {
                    $length = $this->readHex($data, $idx, 10);
                    $idx += 11; // 10 + delimiter;
                    $message['content']['protocolVersion'] = substr($data, $idx, $length);
                    $idx += $length + 1;
                    $length = $this->readHex($data, $idx, 10);
                    $idx += 11; // 10 + delimiter;
                    $message['content']['connectionUUID'] = substr($data, $idx, $length);
                    $idx += $length + 1;
                    $message['content']['established'] = $this->readHex($data, $idx, 10);
                    $idx += 11; // 10 + delimiter;
                    $message['content']['timestampINI'] = $this->readHex($data, $idx, 18);
                    $idx += 19; // 18 + delimiter;
                    $message['content']['timestampACK'] = $this->readHex($data, $idx, 18);
                    $idx += 19; // 18 + delimiter;
                }
            }
            elseif ($message['channel'] === 0x362) { // GW_CHANNEL
                if ($message['content']['messageType'] === 'GWM') {
                    $message['content']['subMessageType'] = substr($data, $idx, 3);
                    $idx += 4;
                    $message['content']['channel'] = $this->readHex($data, $idx, 10);
                    $idx += 11; // 10 + delimiter;

                    if ($message['content']['channel'] === 0xb479) { // DEE_WEBSITE_MESSAGING
                        $length = $this->readHex($data, $idx, 10);
                        $idx += 11; // 10 + delimiter;
                        $message['content']['destinationIdentityUrn'] = substr($data, $idx, $length);
                        $idx += $length + 1;

                        $length = $this->readHex($data, $idx, 10);
                        $idx += 11; // 10 + delimiter;
                        $idData = substr($data, $idx, $length);
                        $idx += $length + 1;

                        $idData = explode( ' ', $idData);
                        $message['content']['deviceIdentityUrn'] = $idData[0];
                        if (isset($idData[1]) )
                        {
                            $message['content']['payload'] = $idData[1];
                        }
                        else
                        {
                            $message['content']['payload'] = substr($data, $idx, -4);
                        }
                        if ( substr( $message['content']['payload'], 0, 1) === '{' && substr( $message['content']['payload'], -1, 1) === '}' )
                        {
                            try
                            {
                                $message['content']['payload'] = json_decode($message['content']['payload'], true);
                                if ($message['content']['payload'] && $message['content']['payload']['payload'] && is_string($message['content']['payload']['payload']) )
                                {
                                    $message['content']['payload']['payload'] = json_decode($message['content']['payload']['payload'], true);
                                }
                            }
                            catch (Exception $e) {
                                // Ignore
                            }
                        }
                    }
                }
            }
            elseif ($message['channel']  === 0x65) { // CHANNEL_FOR_HEARTBEAT
                $idx -= 1; // no delimiter!
                $message['content']['payload'] = substr($data, $idx, -4);
            }
        }

        return $message;
    }

    protected function uuid4()
    {
        return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4) );
    }


    protected function encodeNumber( int $value, int $byteLength)
    {
        return sprintf('0x%0'.$byteLength.'x', $value);
    }

    protected function str2bin( string $string )
    {
        $bin = null;
        foreach( str_split($string) as $char)
        {
            $bin .=  pack('C', $char) ; 
        }
        return $bin;
    }

    protected function computeChecksum( string $text, int $f, int $k) : int
    {
    /*
        Args:
            text (Text|bytearray): Input text
            f (int): begin of window
            k (int): end of window
        Returns:
            int: [description]
    */
        if(!function_exists("b")){
            function b( int $a, int $b): int
            {
                $a = c($a);
                while ($b != 0 && $a != 0)
                {
                    $a = floor($a / 2);
                    $b = $b-1;
                }
                return $a;
            }
        }
        
        if(!function_exists("c")){
            function c( int $a): int
            {
                if ($a < 0)
                {
                    $a = 4294967295 + $a + 1;
                }
                return $a;
            }
        }

        if ($k < $f)
            trigger_error("Invalid checksum exclusion window!");

        $a = unpack( 'C*', $text);// a: bytearray = bytearray(text, "utf-8") if isinstance(text, str) else text
        $a = array_values($a); // reindex to start from 0 (not 1 as result of unpack) 
        $h = 0;
        $temp_l = 0;
        $e = 0;

        while ($e < count($a) )
        {
            if ($e != $f)
            {
                $temp_l += c( $a[$e] << (($e & 3 ^ 3) << 3));
                $h += b($temp_l, 32);
                $temp_l = c($temp_l & 4294967295);
            }
            else
                $e = $k - 1;

            $e += 1;
        }

        while ($h > 0)
        {
            $temp_l += $h;
            $h = b($temp_l, 32);
            $temp_l &= 4294967295;
        }

        return c($temp_l);

    }
}