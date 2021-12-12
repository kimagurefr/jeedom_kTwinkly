<?php
class TwinklyMusic {
    const SHARED_KEY_CHALLENGE = "evenmoresecret!!";    
    const DISCOVER_MESSAGE = "\x01WHEREAREYOU";
    const DISCOVER_PORT = 5556;

    private $ip;
    private $mac;
    private $endpoint;
    private $token;
    private $debug;
    private $debuglog;

    function __construct($ip, $mac, $debug=FALSE, $debuglog="/tmp/kTwinklyMusic.log", $cachepath="/tmp")
    {
        $this->ip = $ip;
        $this->mac = $mac;
        $this->endpoint = TwinklyMusic::get_endpoint($ip);
        $this->debug = $debug;
        $this->debuglog = $debuglog;
        $this->debug("TwinklyMusic::new($mac, $ip)");

        // Read token from cache
        $stringid = str_replace(":", "", $mac);
        $this->cache = $cachepath . '/twinklymusic_' . $stringid . '_auth.txt';
        $token_data = @file_get_contents($this->cache);
        if ($token_data !== FALSE) {
            $this->debug('  Reading auth data from ' . $this->cache . ' : ' . $token_data);
            $json_data = json_decode($token_data, TRUE);
            if ($json_data) {
                $this->token = $json_data;
            }
        } else {
            $this->debug('  No cached token found.');
        }
        $this->debug('');
    }

    function __destruct()
    {
        $this->debug("");
    }

    
    private function save_token()
    {
        $this->debug("TwinklyString::save_token - Storing current auth token in cache (" . $this->cache . ")");
        file_put_contents($this->cache, json_encode($this->token));
    }

    // Ecrit un message sur stdout et dans la log si le mode debug est actif
    private function debug($msg, $iserror=FALSE)
    {
        if ($this->debug === TRUE)
        {
            $msg = '[' . date('Y-m-d H:i:s') . ']' . ($iserror?'[ERROR]':'[DEBUG]') . ' ' . $msg . "\n";
            //echo $msg;
            file_put_contents($this->debuglog, $msg, FILE_APPEND);
        }
    }

    // Formate un message json pour qu'il soit facilement lisible
    private function json_print($json)
    {
        if (is_array($json) || is_object($json)) {
            return json_encode($json, JSON_PRETTY_PRINT);
        } else {
            return json_encode(json_decode($json), JSON_PRETTY_PRINT);
        }
    }

    // Calcul de la réponse au challenge pour confirmer l'authenticité du client
	// Le challence est une chaine aléatoire de 32 octets
	// La réponse renvoyée par le serveur avec l'API Verify doit correspondre à sha1(rc4(derive_key(SHARED_KEY_CHALLENGE, mac_address)    
	// L'appel à /verify est obligatoire, mais le client peut choisir d'ignorer le cas où la réponse ne correspond pas à la valeur calculée
    private function derive_key($shared_key, $mac)
    {
        $msg_array = str_split(hex2bin(str_replace(array(':','-'),'',strtoupper($mac))));
        $key_array = str_split($shared_key);

        $mi = new MultipleIterator();
        if (sizeof($key_array) > sizeof($msg_array)) {
                    $mi->attachIterator(new ArrayIterator($key_array));
                    $mi->attachIterator(new InfiniteIterator(new ArrayIterator($msg_array)));
        } else {
                    $mi->attachIterator(new InfiniteIterator(new ArrayIterator($key_array)));
                    $mi->attachIterator(new ArrayIterator($msg_array));
        }

        $ciphered = array();
        foreach ( $mi as $value ) {
            list($value1, $value2) = $value;
            array_push($ciphered, chr(ord($value1) ^ ord($value2)));
        }

        return join($ciphered);
    }

    private function rc4($message, $key)
    {
        //$enc = @mcrypt_encrypt(MCRYPT_ARCFOUR, $key, $message, MCRYPT_MODE_STREAM);
        //$enc = openssl_encrypt($message, "RC4-40", $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);
        $enc = openssl_encrypt($message, "RC4", $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);
        return $enc;
    }

    // Envoie une méthode POST à l'API
	// $method = nom de la méthode API à appeler
	// $postdata = contenu de la requete dans le type indiqué par $content_type
	// $authenticated = indique si la requête doit être envoyée avec un access token (TRUE) ou s'il s'agit d'une requête non authentifiée (FALSE)
	// $verify_auth = indique s'il faut vérifier la validité de l'accès token avant d'appeler l'API
	// $special_token
	// $content_type = type de contenu de $postdata
    private function do_api_post($method, $postdata, $authenticated=TRUE, $verify_auth=TRUE, $special_token=NULL, $content_type="application/json")
    {
        $debugmsg = ">>>> API [POST : $method] - auth=$authenticated verifyauth=$verify_auth";
        if ($content_type == "application/json") {
            $debugmsg .= " - POST data : $postdata";
        } else {
            $debugmsg .= " - POST data : (binary data)";
        }

        $this->debug($debugmsg);

        $ch = curl_init($this->endpoint . "/" . $method);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($authenticated === TRUE) {
            if ($special_token === NULL)
            {
                if ($verify_auth) {
                    $this->check_token_or_auth();
                }
                $auth_token = $this->token["auth_token"];
            } else {
                $auth_token = $special_token;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: $content_type", "Content-Length: ".strlen($postdata), "X-Auth-Token: " . $auth_token));
        } else {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: $content_type", "Content-Length: ".strlen($postdata)));
        }
        $data = curl_exec($ch);
        $result = json_decode($data, true) or NULL;
        $this->debug("<<<< API RESULT : $data");
        $this->debug("");
        curl_close($ch);

        if (is_null($result)) {
            $this->debug("#### ERROR API [POST : $method] data=$result", TRUE);
            $this->debug("");
            throw new Exception("Twinkly API error [POST : $method] data=$data");
        }
        return $result;
    }

    // Envoie une methode GET à l'API
	// $method = nom de la méthode API à appeler
	// $authenticated = indique si la requête doit être envoyée avec un access token (TRUE) ou s'il s'agit d'une requête non authentifiée (FALSE)
    private function do_api_get($method, $authenticated=TRUE)
    {
        $this->debug(">>>> API [GET : $method] - auth=$authenticated");
        $ch = curl_init($this->endpoint . "/" . $method);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($authenticated === TRUE) {
            $this->check_token_or_auth();
            $auth_token = $this->token["auth_token"];
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-Auth-Token: " . $auth_token));
        }
        $data = curl_exec($ch);
        $result = json_decode($data, true) or NULL;
        $this->debug("<<<< API RESULT : $data");
        $this->debug("");
        curl_close($ch);

        if ($result) {
            return $result;
        } else {
            $this->debug("#### ERROR API [GET : $method] data=$data", TRUE);
            $this->debug("");
            throw new Exception("Twinkly API error [GET : $method] data=$data");
        }
    }

    // Envoie une methode DELETE à l'API
    private function do_api_delete($method, $authenticated=TRUE)
    {
        $this->debug(">>>> API [DELETE : $method] - auth=$authenticated");
        $ch = curl_init($this->endpoint . "/" . $method);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($authenticated === TRUE) {
            $this->check_token_or_auth();
            $auth_token = $this->token["auth_token"];
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-Auth-Token: " . $auth_token));
        }
        $data = curl_exec($ch);
        $result = json_decode($data, true) or NULL;
        $this->debug("<<<< API RESULT : $data");
        $this->debug("");
        curl_close($ch);

        if ($result) {
            return $result;
        } else {
            $this->debug("#### ERROR API [DELETE : $method] data=$data", TRUE);
            throw new Exception("Twinkly API error [DELETE : $method] data=$data");
        }
    }

    // Vérifie la validite du token, ou appelle l'API d'authentification pour en générer un nouveau
    private function check_token_or_auth()
    {
        if ($this->token !== NULL) {
            // Token exists
            //$this->debug("  # Check validity of current token");
            $expiry = $this->token["expiry"];
            if ($expiry - (new DateTime())->getTimestamp() > 60) {
                // Token not expired
                $postdata = '{ "m":"" }';
                $ch = curl_init($this->endpoint . "/echo");
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Content-Length: ".strlen($postdata), "X-Auth-Token: " . $this->token["auth_token"]));
                $data = curl_exec($ch);
                curl_close($ch);
                $result = json_decode($data, true) or NULL;
                if(!is_null($result) && $result["code"]=="1000") {
                    // Token valid
                    //$this->debug("    Token is still valid. No need to re-authenticate");
                    return TRUE;
                }
            } else {
                $this->debug("    Token expired - Authentication required");
            }
        } else {
            $this->debug("    No token found -  authentication required");
        }
        // Token missing, expired or invalid
        //$this->debug("    Performing new authentication");
        $this->authenticate();
        //$this->debug("  # Authentication successful - returning to calling API");
    }

    // Authentification sur l'API et récupération d'un access token
	// Attention : le contrôleur Twinkly est limité à un seul access token actif à la fois : toute authentification invalidera les précédents tokens
	// et déconnectera donc les autres clients déjà connectés (notamment l'application mobile)
    private function authenticate()
    {
        $this->debug('    + Authentication (get new auth token)');

        // Generate a random 32-byte challenge
        $challenge = random_bytes(32);
        $b64_challenge = base64_encode($challenge);
        $json = json_encode(array("challenge" => $b64_challenge));

        // Call /login API
        $ch = curl_init($this->endpoint . "/login");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Content-Length: ".strlen($json)));
        $data = curl_exec($ch);
        $result = json_decode($data, true) or NULL;
        curl_close($ch);

        if (is_null($result) || $result["code"] != "1000") {
            $this->debug("#### ERROR Call to /login failed : $data", TRUE);
            throw new Exception("Twinkly authentication error [POST /login] : $data"); 
        }

        // Get auth token and requested challenge-reponse from the controller response
        $auth_token = $result["authentication_token"];
        $auth_expiry = $result["authentication_token_expires_in"];
        $challenge_response = $result["challenge-response"];

        // Compute challenge response based on MAC address
        $dk = $this->derive_key($this::SHARED_KEY_CHALLENGE, $this->mac);
        $enc = $this->rc4($challenge, $dk);
        $rsp = sha1($enc);
        //$this->debug("    Requested challenge response : $challenge_response");
        //$this->debug("    Computed challenge response  : $rsp");

        // Authentication should normally fail when challenge-response does not match the computed value
        // As we are talking directly with the controller locally, this may be safely ignored
        if ($rsp != $challenge_response) {
            $this->debug("#### WARNING : incorrect challenge-response - IGNORING");
            //throw new Exception("Twinkly Authentication error. Incorrect challenge-response. [POST : login]");
        } else {
            //$this->debug("    Challenge response matches expected value");
        }
        $json = json_encode(array("challenge-response" => $challenge_response));

        // Call /verify API to activate the auth token
        $ch = curl_init($this->endpoint . "/verify");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Content-Length: ".strlen($json), "X-Auth-Token: " . $auth_token));
        $data = curl_exec($ch);
        $result = json_decode($data, true) or NULL;
        curl_close($ch);

        if (is_null($result) || $result["code"] != "1000") {
            $this->debug("#### ERROR Call to /verify failed : $data", TRUE);
            throw new Exception("Twinkly authentication error [POST /verify] : $data");
        }

        $expiry_timestamp = (new DateTime())->getTimestamp() + $auth_expiry;
        $this->debug("    + Authentication successful");
        $this->token = array("auth_token" => $auth_token, "expiry" => $expiry_timestamp);

        $this->save_token();
    } 


    public function get_stats()
    {
        $this->debug('TwinklyMusic::get_stats');
        $result = $this->do_api_get("music/stats");
        if (is_null($result) || $result["code"] != "1000") {
            $this->debug("  get_stats error : " . json_encode($result));
            throw new Exception("get_stats error [GET : music/stats] data=" . print_r($result,TRUE));
        }
        return $result["bpm"];
    }

    public function get_network_status()
    {
        $this->debug('TwinklyMusic::get_network_status');
        $result = $this->do_api_get("network/status");
        if (is_null($result) || $result["code"] != "1000") {
            $this->debug("  get_network_status error : " . json_encode($result));
            throw new Exception("get_network_status error [GET : network/status] data=" . print_r($result,TRUE));
        }
        return $result;
    }

    public function get_mode()
    {
        $this->debug('TwinklyMusic::get_mode');
        $result = $this->do_api_get("music/mode");
        if (is_null($result) || $result["code"] != "1000") {
            $this->debug("  get_mode : " . json_encode($result));
            throw new Exception("get_network_status error [GET : network/status] data=" . print_r($result,TRUE));
        }
        $result = array("mode" => $result["mode"], "mood_index" => $result["config"]["mood_index"]);
        return $result;
    }

    public function set_mode($mode)
    {
        $this->debug("TwinklyMusic::set_mode($enabled)");
        $json = json_encode(array("mode" => $mode));
        $result = $this->do_api_post("music/mode", $json);

        if (is_null($result) || $result["code"] != "1000") {
            $this->debug("  set_mode error : " . json_encode($result));
            throw new Exception("set_mode error [POST : music/mode] data=" . print_r($result,TRUE));
        }
        return TRUE;
    }

    public function get_mic_enabled()
    {
        $this->debug('TwinklyMusic::get_mic_enabled');
        $result = $this->do_api_get("music/mic_enabled");
        if (is_null($result) || $result["code"] != "1000") {
            $this->debug("  get_mic_enabled error : " . json_encode($result));
            throw new Exception("get_mib_enabled error [GET : music/mic_enabled] data=" . print_r($result,TRUE));
        }
        return ($result["mic_enabled"]==1?true:false);
    }

    public function set_mic_enabled($enabled)
    {
        $this->debug("TwinklyMusic::set_mic_enabled($enabled)");
        $json = json_encode(array("mic_enabled" => ($enabled?1:0)));
        $result = $this->do_api_post("music/mic_enabled", $json);

        if (is_null($result) || $result["code"] != "1000") {
            $this->debug("  set_mic_enabled error : " . json_encode($result));
            throw new Exception("set_mic_enabled error [POST : music/mic_enabled] data=" . print_r($result,TRUE));
        }
        return TRUE;
    }    
    
    // Renvoie les informations sur l'équipement
    public function get_details()
    {
        $this->debug('TwinklyMusic::get_details');
        return $result = $this->do_api_get("gestalt", FALSE);
    }

    // Renvoie la version actuelle du firmware
    public function firmware_version()
    {
        return TwinklyMusic::get_firmware_version($this->ip);
    }

    // METHODES STATIQUES

    // Renvoie l'URL de base pour les API
    public static function get_endpoint($ip)
    {
        return sprintf("http://%s/xmusic/v1", $ip);
    }

    // Renvoie la version actuelle du firmware (méthode statique)
    public static function get_firmware_version($ip)
    {
        $endpoint = TwinklyMusic::get_endpoint($ip);
        $ch = curl_init($endpoint . "/fw/version");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $result = json_decode(curl_exec($ch), true) or NULL;
        if ($result !== NULL) {
            return $result["version"];
        } else {
            throw new Exception("Cannot get firmware version. Check IP address.");
        }
    }

    // Effectue une découverte automatique des modules Twinkly Music sur le réseau
	// La détection se fait envoyant un paquet UDP broadcast
	// La réponse contient l'adresse IP sur 4 octets (ordre inverse) et le nom du devicename
	// La méthode appelle ensuite l'API "gestalt" pour avoir les informations sur la guirlande (IP, mac address, etc.)
    public static function discover($timeout=5)
    {
        $discovered = array();
        $devices = array();
        $broadcastip = "255.255.255.255";

        $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP); 
        socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
        socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, array("sec"=>$timeout, "usec"=>0));
        socket_sendto($sock, TwinklyMusic::DISCOVER_MESSAGE, strlen(TwinklyMusic::DISCOVER_MESSAGE), 0, $broadcastip, TwinklyMusic::DISCOVER_PORT);


        while(true) {
            $ret = @socket_recvfrom($sock, $buf, 20, 0, $ip, $port);
            if ($ret === false) break;
            $discovered[] = $buf;
        }
        socket_close($sock);

        foreach($discovered as $d)
        {
            $encodedip = substr($d,0,4);
            $decodedip = ord($encodedip[3]) . '.' . ord($encodedip[2]) . '.' . ord($encodedip[1]) . '.' . ord($encodedip[0]);
            $devicename = substr($d,6);
                        
            $ch = curl_init(TwinklyMusic::get_endpoint($decodedip) . "/gestalt");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $details = json_decode(curl_exec($ch),true) or NULL;
            curl_close($ch);
            $fw = TwinklyMusic::get_firmware_version($decodedip);
            $details["firmware_version"] = $fw;
            if ($details) {
                $devices[] = array("ip" => $decodedip, "name" => $devicename, "mac" => $details["mac"], "details" => $details);
            }
        }

        return $devices;
    }

}
