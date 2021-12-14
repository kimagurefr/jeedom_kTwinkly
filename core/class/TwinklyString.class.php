<?php
class TwinklyString {
    const SHARED_KEY_CHALLENGE = "evenmoresecret!!";
    const DISCOVER_MESSAGE = "\x01discover";
    const DISCOVER_PORT = 5555;
    const XLED_MODES = array('off','movie','playlist','effect','color','demo');

    private $ip;
    private $mac;
    private $endpoint;
    private $token;
    private $debug;
    private $debuglog;

    function __construct($ip, $mac, $debug=FALSE, $debuglog="/tmp/kTwinkly.log", $cachepath="/tmp")
    {
        $this->ip = $ip;
        $this->mac = $mac;
        $this->endpoint = TwinklyString::get_endpoint($ip);
        $this->debug = $debug;
        $this->debuglog = $debuglog;
        $this->debug("TwinklyString::new($mac, $ip)");

        // Read token from cache
        $stringid = str_replace(":", "", $mac);
        $this->cache = $cachepath . '/twinkly_' . $stringid . '_auth.txt';
        $token_data = @file_get_contents($this->cache);
        if ($token_data !== FALSE) {
            $this->debug('    + Read auth data from cache : ' . $token_data);
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
        $this->debug("    + Storing current auth token in cache (" . $this->cache . ")");
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

    // Renvoie l'URL de base pour les API
    public static function get_endpoint($ip)
    {
        return sprintf("http://%s/xled/v1", $ip);
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
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: " . $content_type, "Content-Length: ".strlen($postdata), "X-Auth-Token: " . $auth_token));
        } else {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: " . $content_type, "Content-Length: ".strlen($postdata)));
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

    // Renvoie la version actuelle du firmware
    public function firmware_version()
    {
        return TwinklyString::get_firmware_version($this->ip);
    }

    // Renvoie la version actuelle du firmware (méthode statique)
    public static function get_firmware_version($ip)
    {
        $endpoint = TwinklyString::get_endpoint($ip);
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

    // Renvoie le mode actif : off, movie, playlist, color, demo, effect, rt
    public function get_mode()
    {
        $this->debug('TwinklyString::get_mode');
        $result = $this->do_api_get("led/mode");
        if (is_null($result) || $result["code"] != "1000") {
            $this->debug("  get_mode error : " . json_encode($result));
            throw new Exception("get_mode error [GET : led/mode] data=" . print_r($result,TRUE));
        }
        return $result["mode"];
    }

    // Renvoie le mode (version detaillee, comprenant l'animation dans la playlist pour les GEN2)
    public function get_mode_full()
    {
        $this->debug('TwinklyString::get_mode_full');
        $result = $this->do_api_get("led/mode");
        if (is_null($result) || $result["code"] != "1000") {
            $this->debug("  get_mode_full error : " . json_encode($result));
            throw new Exception("get_mode_full error [GET : led/mode] data=" . print_r($result,TRUE));
        }
        return $result;
    }    

    // Active le mode indiqué : off, movie, playlist, effect, color, demo
    public function set_mode($mode)
    {
        $this->debug("TwinklyString::set_mode($mode)");
        if(in_array($mode, TwinklyString::XLED_MODES)) {
            $json = json_encode(array("mode" => $mode));
            $result = $this->do_api_post("led/mode", $json);
    
            if (is_null($result) || $result["code"] != "1000") {
                $this->debug("  set_mode error : " . json_encode($result));
                throw new Exception("set_mode error [POST : led/mode] data=" . print_r($result,TRUE));
            }
        } else {
            $this->debug("  set_mode error : invalid mode specified");
            throw new Exception("set_mode error : invalid mode specified");
        }

        return TRUE;
    }

    // Retourne l'effet en cours (unique_id/effect_id)
    public function get_effect()
    {
        $this->debug('TwinklyString::get_effect');
        $result = $this->do_api_get("led/effects/current");
        if (is_null($result) || $result["code"] != "1000") {
            $this->debug("  get_effect error : " . json_encode($result));
            throw new Exception("get_effect error [GET : led/effects/current] data=" . print_r($result,TRUE));
        }
        return $result;
    }

    // Choisit l'effet en cours
    public function set_effect($effect_id)
    {
        $this->debug("TwinklyString::set_effect($effect_id)");
        $json = json_encode(array("effect_id" => $effect_id));
        $result = $this->do_api_post("led/effects/current", $json);

        if (is_null($result) || $result["code"] != "1000") {
            $this->debug("  set_effect error : " . json_encode($result));
            throw new Exception("set_effect error [POST : led/effects/current] data=" . print_r($result,TRUE));
        }
        return TRUE;
    }

    // Renvoie le niveau de luminosité de la guirlande (de 0 à 100)
    public function get_brightness()
    {
        $this->debug("TwinklyString::get_brightness");
        $result = $this->do_api_get("led/out/brightness");
        if ($result["code"] != "1000") {
            $this->debug("  get_brightness error : " . json_encode($result), TRUE);
            throw new Exception("get_brightness error [GET : led/out/brightness] data=" . print_r($result,TRUE));
        }
        return $result["value"];
    }

    // Choisit le niveau de luminosité de la guirlande (de 0 à 100)
	public function set_brightness($value)
    {
        $this->debug("TwinklyString::set_brightness($value)");
        $this->debug("  get current mode");
        $current_mode = $this->get_mode();
        if ($current_mode == "movie" || $current_mode == "playlist")
        {
            $json = json_encode(array("type" => "A","value" => intval($value)));
            $result = $this->do_api_post("led/out/brightness", $json);
            if ($result["code"] != "1000") {
                $this->debug("  set_brightness error : " . json_encode($result), TRUE);
                throw new Exception("set_brightness error [POST : led/out/brightness] data=" . print_r($result,TRUE));
            }
            return TRUE;
        } else {
            $this->debug("  brigthness can be set while in movie or playlist mode only", TRUE);
            return FALSE;
        }
    }

    // Renvoie la couleur courante en mode color
    public function get_color()
    {
        $this->debug('TwinklyString::get_color');
        return $result = $this->do_api_get("led/color");
    }

    // Choisit la couleur a utiliser pour le mode color (hue/saturation/value)
    public function set_color_hsv($hue, $saturation, $value) 
    {
        $this->debug("TwinklyString::set_color_hsv($hue, $saturation, $value)");
        if($hue>=0 && $hue<=359 && $saturation>=0 && $saturation<=255 && $value>=0 && $value<=255) {
            $json = json_encode(array("hue" => $hue, "saturation" => $saturation, "value" => $value));
            $result = $this->do_api_post("led/color", $json);

            if (is_null($result) || $result["code"] != "1000") {
                $this->debug("  set_color_hsv error : " . json_encode($result));
                throw new Exception("set_color_hsv error [POST : led/color] data=" . print_r($result,TRUE));
            }
        } else {
            $this->debug("  set_color_hsv error : invalid arguments - hue must be in the 0..359 range and saturation/value must be in the 0..255 range");
            throw new Exception("set_color_hsv error : invalid arguments - hue must be in the 0..359 range and saturation/value must be in the 0..255 range");
        }
        return TRUE;
    }

    // Choisit la couleur a utiliser pour le mode color (red/green/blue)
    public function set_color_rgb($red, $green, $blue) 
    {
        $this->debug("TwinklyString::set_color_rgb($red, $green, $blue)");
        if($red>=0 && $red<=255 && $green>=0 && $green<=255 && $blue>=0 && $blue<=255) {
            $json = json_encode(array("red" => $red, "green" => $green, "blue" => $blue));
            $result = $this->do_api_post("led/color", $json);
    
            if (is_null($result) || $result["code"] != "1000") {
                $this->debug("  set_color_rgb error : " . json_encode($result));
                throw new Exception("set_color_rgb error [POST : led/color] data=" . print_r($result,TRUE));
            }
        } else {
            $this->debug("  set_color_rgb error : invalid arguments - colors must be in the 0..255 range");
            throw new Exception("set_color_rgb error : invalid arguments - colors must be in the 0..255 range");
        }
        return TRUE;
    }

    // Renvoie le niveau de saturation (0..100)
    public function get_saturation()
    {
        $this->debug("TwinklyString::get_saturation");
        $result = $this->do_api_get("led/out/saturation");
        if ($result["code"] != "1000") {
            $this->debug("  get_saturation error : " . json_encode($result), TRUE);
            throw new Exception("get_saturation error [GET : led/out/saturation] data=" . print_r($result,TRUE));
        }
        return $result["value"];
    }

    // Definit le niveau de saturation
    public function set_saturation()
    {
        $this->debug("TwinklyString::set_saturation($enabled, $type, $value)");
        if(($type==="A" && $value>=0 && $value<=100) || ($type==="R" && $value>=-100 && $value<=100)) {
            $json = json_encode(array("mode" => ($enabled===$true?"enabled":"disabled"), "type" => $type, "value" => $value));
            $result = $this->do_api_post("led/out/saturation", $json);
    
            if (is_null($result) || $result["code"] != "1000") {
                $this->debug("  set_saturation error : " . json_encode($result));
                throw new Exception("set_saturation error [POST : led/out/saturation] data=" . print_r($result,TRUE));
            }
        } else {
            $this->debug("  set_saturation error : invalid arguments - for type R, value must be in the -100..100 range. For type A, value must be in the 0..100 range.");
            throw new Exception("set_saturation error : invalid arguments - for type R, value must be in the -100..100 range. For type A, value must be in the 0..100 range");
        }
        return TRUE;
    }    
    
    // Renvoie les informations sur la guirlande
    public function get_details()
    {
        $this->debug('TwinklyString::get_details');
        return $result = $this->do_api_get("gestalt", FALSE);
    }

    // Renvoie les informations sur la guirlande (infos detaillees)
    public function get_details_full()
    {
        $this->debug('TwinklyString::get_details_full');
        return $result = $this->do_api_get("gestalt?filter=prod_info&filter2=group", FALSE);
    }

    // Renvoie le statut courant de la guirlande (uniquement un code retour 1000 si ok)
    public function get_status()
    {
        $this->debug('TwinklyString::get_status');
        return $result = $this->do_api_get("status");
    }

    // Charge une animation dans le contrôleur (mode GEN1)
    public function upload_movie($movie, $leds_number, $frames_number, $frame_delay)
    {
        if (ctype_print($movie)) {
            $this->debug("TwinklyString::upload_movie($movie, $leds_number, $frames_number, $frame_delay)");
            $movie_data = file_get_contents($movie);
            if ($movie_data === false) {
                $this->debug("  upload_movie : file not found", TRUE);
                throw new Exception("upload_movie error : file not found");
            }
        } else {
            $this->debug("TwinklyString::upload_movie(bindata, $leds_number, $frames_number, $frame_delay)");
            $movie_data = $movie;
        }

        $this->debug("  switch device off");
        $this->set_mode("off");

        $this->debug("  upload stage 1 (reset)");

        $json = json_encode(array());
        $result = $this->do_api_post("led/reset", $json);
        if ($result["code"] != "1000") {
            $this->debug("  upload_movie step 1 error...", TRUE);
            throw new Exception("upload_movie step 1 error [POST : led/reset] data=" . print_r($result,TRUE));
        }

        $this->debug("  upload stage 2 (upload movie data)");
        $result = $this->do_api_post("led/movie/full", $movie_data, TRUE, FALSE, NULL, "application/octet-stream");
        if ($result["code"] != "1000" || $result["frames_number"] != $frames_number) {
            $this->debug("  upload_movie step 2 error...", TRUE);
            throw new Exception("upload_movie step 2 error [POST : led/movie/full] data=" . print_r($result,TRUE));
        }

        $this->debug("  upload stage 3 (upload movie configuration)");
        $json = json_encode(array("frame_delay" => $frame_delay, "leds_number" => $leds_number, "frames_number" => $frames_number));
        $result = $this->do_api_post("led/movie/config", $json, TRUE, FALSE);
        if ($result["code"] != "1000") {
            $this->debug("  upload_movie step 3 error...", TRUE);
            throw new Exception("upload_movie step 3 error [POST : led/movie/config] data=" . print_r($result,TRUE));
        }

        $this->debug("  upload stage 4 (reset)");
        $json = json_encode(array());
        $result = $this->do_api_post("led/reset", $json, TRUE, FALSE);
        if ($result["code"] != "1000") {
            $this->debug("upload_movie step 4 error...", TRUE);
            throw new Exception("upload_movie step 4 error [POST : led/reset] data=" . print_r($result,TRUE));
        }

        $this->debug("  change current mode to 'movie'");
        $this->set_mode("movie");

        return TRUE;
    }

    public function upload_movie2($movie_data, $jsonstrparameters, $delete_all=FALSE) {

        // Si un nom de fichier est passé, on le charge
        if(ctype_print($movie_data)) {
            $this->debug("TwinklyString::upload_movie2($movie_data, $jsonstrparameters, $delete_all)");
            $movie_data = file_get_contents($movie_data);
            if ($movie_data === false) {
                $this->debug("#### ERROR : movie file not found", TRUE);
                throw new Exception("upload_movie error : file not found");
            }
        } else {
            $this->debug("TwinklyString::upload_movie2(bindata, $jsonstrparameters)");
        }

        $jsonparameters = json_decode($jsonstrparameters, TRUE);
        if ($jsonparameters === false) {
            $this->debug('####ERROR : upload_movie2 : invalid movie parameters', TRUE);
            throw new Exception("upload_movie2 error : invalid movie parameters");
        }

        $this->debug("  * Switch device off");
        $this->set_mode('off');

        $found = FALSE;
        if($delete_all) {
            $this->debug("  * Delete all movies from controller");
            $this->delete_movies();
        } else {
            $this->debug("  * Get existing movies list from controller");
            $all_movies = $this->get_movies();
            $unique_id = $jsonparameters["unique_id"];
    
            foreach ($all_movies["movies"] as $m) {
                if (strtolower($m["unique_id"]) == strtolower($unique_id)) {
                    $found = TRUE;
                }
            }
        }

        if ($found == FALSE) {
            $this->debug("  * Requested movie does not exist on controller : uploading movie to device");
            if($this->add_movie($movie_data, $jsonstrparameters) !== TRUE) {
                $this->debug("upload_movie2 add movie  error...", TRUE);
                throw new Exception("upload_movie2 add movie error [POST : movies/new] data=" . print_r($result,TRUE));
            }
        } else {
            $this->debug('  * Movie already exists on controller. Upload not required');
        }

        $this->debug("  * set current movie to uid = " . $jsonparameters["unique_id"]);
        $result = $this->set_current_movie($jsonparameters["unique_id"]);
        if ($result["code"] != "1000") {
            $this->debug("upload_movie2 set movie error...", TRUE);
            throw new Exception("upload_movie2 set movie error [POST : movies/current] data=" . print_r($result,TRUE));
        }
        $this->debug("  * change current mode to 'movie'");
        $this->set_mode("movie");
    
        return TRUE;
    }

    // Effectue une découverte automatique des guirlandes Twinkly sur le réseau
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
        socket_sendto($sock, TwinklyString::DISCOVER_MESSAGE, strlen(TwinklyString::DISCOVER_MESSAGE), 0, $broadcastip, TwinklyString::DISCOVER_PORT);


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

            $ch = curl_init("http://" . $decodedip . "/xled/v1/gestalt");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $details = json_decode(curl_exec($ch),true) or NULL;
            curl_close($ch);
            $fw = TwinklyString::get_firmware_version($decodedip);
            $details["firmware_version"] = $fw;
            if ($details) {
                $devices[] = array("ip" => $decodedip, "name" => $devicename, "mac" => $details["mac"], "details" => $details);
            }
        }

        return $devices;
    }

    // Renvoie la configuration MQTT actuelle
    public function get_mqtt_configuration()
    {
        return $this->do_api_get("mqtt/config", TRUE);
    }

    // Définit la configuration MQTT du contrôleur
    public function set_mqtt_configuration($broker_ip, $broker_port, $client_id, $mqtt_user)
    {
        $this->debug("TwinklyString::set_mqtt_configuration($broker_ip, $broker_port, $client_id, $mqtt_user)");

        $json = json_encode(array(
            "broker_host" => $broker_ip,
            "broker_port" => $broker_port,
            "client_id" => $client_id,
            "user" => $mqtt_user
        ));

        $result = $this->do_api_post('mqtt/config', $json, TRUE);

        if ($result["code"] != "1000") {
                $this->debug("set_mqtt_configuration error...");
                throw new Exception("set_mqtt_configuration error [POST : $method] data=" . print_r($result, TRUE));
        }
        return TRUE;
    }

    public function get_movies()
    {
        $this->debug("TwinklyString::get_movies");
        $result = $this->do_api_get("movies");
        if ($result["code"] != "1000") {
            $this->debug("get_movies error...");
            throw new Exception("get_movies error [GET : movies] data=" . print_r($result,TRUE));
        }
        return $result;
    }

    public function delete_movies()
    {
        $this->debug("TwinklyString::delete_movies");
        $result = $this->do_api_delete("movies");
        if ($result["code"] != "1000") {
            $this->debug("delete_movies error...");
            throw new Exception("delete_movies error [DELETE : movies] data=" . print_r($result,TRUE));
        }
        return $result;
    }

    public function get_current_movie()
    {
        $this->debug("TwinklyString::get_current_movie");
        $result = $this->do_api_get("movies/current");
        if ($result["code"] != "1000") {
            $this->debug("get_current_movie error...");
            throw new Exception("get_current_movie error [GET : movies/current] data=" . print_r($result,TRUE));
        }
        return $result;
    }

    public function set_current_movie($movieId)
    {
        $this->debug('TwinklyString::set_current_movie');
        $json = json_encode(array("unique_id" => $movieId));
        $result = $this->do_api_post("movies/current", $json);
        if ($result["code"] != "1000") {
            $this->debug("set_current_movie error...");
            throw new Exception("set_current_movie error [POST : movies/current] data=" . print_r($result,TRUE));
        }
        return $result;
    }

    public function get_current_playlist()
    {
        $this->debug("TwinklyString::get_current_playlist");
        $result = $this->do_api_get("playlist");
        if ($result["code"] != "1000") {
            $this->debug("get_current_playlist error...");
            throw new Exception("get_current_playlist error [GET : playlist] data=" . print_r($result,TRUE));
        }
        return $result["entries"];
    }

    public function update_playlist($json)
    {
        $this->debug("TwinklyString::update_playlist($json)");
        $result = $this->do_api_post("playlist", $json);
        if ($result["code"] != "1000") {
            $this->debug("update_playlist error...");
            throw new Exception("update_playlist error [POST : playlist] data=" . print_r($result,TRUE));
        }

    }

    public function get_current_playlist_item()
    {
        $this->debug("TwinklyString::get_current_playlist_item");
        $result = $this->do_api_get("playlist/current");
        if ($result["code"] != "1000") {
            $this->debug("get_current_playlist_item error...");
            throw new Exception("get_current_playlist_item error [GET : playlist/current] data=" . print_r($result,TRUE));
        }
        return $result;
    }

    public function add_movie($movie_data, $jsondata)
    {
        $this->debug("TwinklyString::add_movie(bindata, $jsondata)");
        $jsonparameters = json_decode($jsondata, TRUE);

        // Vérifie la place disponible
        $this->debug("  Call /movies to get list of movies in controller and check available memory");
        $result = $this->do_api_get("movies");
        if ($result["code"] != "1000") {
            $this->debug("  add_movie check available memory error...", TRUE);
            throw new Exception("add_movie check available memory error [GET : movies] data=" . print_r($result,TRUE));
        }

        $capacity = intval($result["available_frames"]);
        $size = intval($jsonparameters["frames_number"]);

        if($size > $capacity) {
            $this->debug("  add_movie : not enough memory left on controler (size = " . $size . " / remaining = " . $capacity . ")", TRUE);
            throw new Exception("add_movie : not enough memory left on controler (size = " . $size . " / remaining = " . $capacity . ")");
        }

        $this->debug("  Send new movie metadata to controller");
        $result = $this->do_api_post("movies/new", $jsondata);
        if ($result["code"] != "1000") {
            $this->debug("  add_movie step 1 (movies/new) error : " . json_encode($result), TRUE);
            throw new Exception("add_movie step 1 error [POST : movies/new] data=" . print_r($result,TRUE));
        }

        $this->debug("  Send binary data to controller");
        $result = $this->do_api_post("movies/full", $movie_data, TRUE, FALSE, NULL, "application/octet-stream");
        if ($result["code"] != "1000" || $result["frames_number"] != $jsonparameters["frames_number"]) {
            $this->debug("add_movie step 2 (movies/full) error : " . json_encode($result), TRUE);
            throw new Exception("add_movie step 2 error [POST : movies/full] data=" . print_r($result,TRUE));
        }

        return TRUE;
    }

    public function get_network_status()
    {
        $this->debug("TwinklyString::get_network_status");
        $result = $this->do_api_get("network/status");
        if ($result["code"] != "1000") {
            $this->debug("get_network_status error...");
            throw new Exception("get_network_status error [GET : network/status] data=" . print_r($result,TRUE));
        }
        return $result;
    }

    public function delete_playlist()
    {
        $this->debug("TwinklyString::delete_playlist");
        $result = $this->do_api_delete("playlist");
        if ($result["code"] != "1000") {
            $this->debug("delete_playlist error...");
            throw new Exception("#### ERROR delete_playlist [DELETE : playlist] data=" . print_r($result,TRUE));
        }
        return $result;
    }

    // Créer une nouvelle playlist avec la liste d'animation fournies
    // Le format est un tableau de ["unique_id","json","bin","duration"]
    public function create_new_playlist($movies) 
    {
        $this->set_mode('off');
        //$this->delete_movies();
        //$this->delete_playlist(); // Dejà fait dans add_to_playlist
        return $this->add_to_playlist($movies, TRUE);
    }

    // Ajoute des animations à la playlist courante
    // Le format d'entrée est un tableau de ["unique_id","json","bin","duration"]
    // Le paramètre newplaylist indique s'il faut ajouter à la playlist courante ou repartir d'une playlist vide
    public function add_to_playlist($movies, $newplaylist = TRUE)
    {
        $this->debug('TwinklyString::add_to_playlist');
        if (is_array($movies) == TRUE) {
            try {
                $current_movie = $this->get_current_movie();
            } catch (Exception $e) {
                $current_movie = NULL;
            }

            $all_movies = $this->get_movies();
            $current_playlist = $this->get_current_playlist();

            $pldata = [ "entries" => [] ];  
            // On garde la playlist courante ?          
            if ($newplaylist !== TRUE) {
                foreach ($current_playlist as $e) {
                    $pldata["entries"][] = [
                        "duration" => $e["duration"],
                        "unique_id" => $e["unique_id"],
                    ];
                }
            } 

            // On supprime la playlist courante
            $this->delete_playlist();
            
            $this->set_mode('off');

            // Chargemente des animations sur le controleur si nécessaire
            foreach($movies as $movie) {
                $unique_id = $movie["unique_id"];
                $jsonstr = $movie["json"];
                $json = json_decode($jsonstr, TRUE);
                $bindata = $movie["bin"];
                $duration = 30;
                if (isset($movie["duration"])) {
                    $duration = intval($movie["duration"]);
                }

                $this->debug("  Add to playlist : uid=$unique_id - duration=$duration - json=$jsonstr");

                $found = FALSE;
                foreach ($all_movies["movies"] as $m) {
                    if (strtolower($m["unique_id"]) == strtolower($unique_id)) {
                        $found = TRUE;
                    }
                }
                if ($found == FALSE) {
                    $this->debug('  > Adding new movie ' . $json["name"] . ' to the controller');
                    $this->add_movie($bindata, $jsonstr);
                } else {
                    $this->debug('  > Skip existing movie ' . $json["name"]);
                }

                $pldata["entries"][] = [
                    "duration" => $duration,
                    "unique_id" => $unique_id,
                ];
            }
            $this->debug('');

            // Mise à jour de la playlist
            $this->update_playlist(json_encode($pldata));
            if ($current_movie !== NULL) {
                $this->set_current_movie($current_movie["unique_id"]);
            } else {
                $firstitem = reset($movies);
                $this->set_current_movie($firstitem["unique_id"]);
            }

            // Bascule en mode playlist
            $this->set_mode('playlist');

            return TRUE;
        } else {
            $this->debug('#### ERROR add_to_playlist : wrong parameter format', TRUE);
            throw new Exception("add_to_playlist error - wrong parameter format");
        }
    }
}
?>
