<?php
class TwinklyString {
    const SHARED_KEY_CHALLENGE = "evenmoresecret!!";
    const DISCOVER_MESSAGE = "\x01discover";
    const DISCOVER_PORT = 5555;

    private $ip;
    private $mac;
    private $endpoint;
    private $token;
    private $debug;
    private $debuglog;

    function __construct($ip, $mac, $debug=FALSE, $debuglog="/tmp/kTwinkly.log")
    {
        $this->ip = $ip;
        $this->mac = $mac;
        $this->endpoint = TwinklyString::get_endpoint($ip);
        $this->debug = $debug;
        $this->debuglog = $debuglog;
        $this->debug("TwinklyString::new($mac, $ip)");
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
            $expiry = $this->token["expiry"];
            if ((new DateTime())->getTimestamp() - $expiry > 60) {
                // Token not expired
                $result = $this->do_api_post('echo','{ "m":"" }', TRUE, FALSE);
                if ($result["code"]=="1000") {
                    // Token valid
                    return TRUE;
                }
            }
        }
        // Token missing, expired or invalid
        $this->authenticate();
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
        $this->debug("## CALL TWINKLY API [POST : $method] - auth=$authenticated verifyauth=$verify_auth");
        /*
        if ($content_type == "application/json") {
            $this->debug('POST data :\n' . $this->json_print($postdata));
        } else {
            $this->debug('POST data : ' . $postdata);
        }
        */
        $this->debug('POST data : ' . $postdata);

        $ch = curl_init($this->endpoint . "/" . $method);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
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
        //$this->debug("API RESULT:\n" . $this->json_print($data) . "\n");
        $this->debug("API RESULT : $data");
        curl_close($ch);

        if (is_null($result)) {
            $this->debug("Twinkly API error [POST : $method] data=" . print_r($data,TRUE), TRUE);
            throw new Exception("Twinkly API error [POST : $method] data=" . print_r($data,TRUE));
        }
        return $result;
    }

    // Envoie une methode GET à l'API
	// $method = nom de la méthode API à appeler
	// $authenticated = indique si la requête doit être envoyée avec un access token (TRUE) ou s'il s'agit d'une requête non authentifiée (FALSE)
    private function do_api_get($method, $authenticated=TRUE)
    {
        $this->debug("## CALL TWINKLY API [GET : $method] - auth=$authenticated");
        $ch = curl_init($this->endpoint . "/" . $method);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($authenticated === TRUE) {
            $this->check_token_or_auth();
            $auth_token = $this->token["auth_token"];
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-Auth-Token: " . $auth_token));
        }
        $data = curl_exec($ch);
        $result = json_decode($data, true) or NULL;
        //$this->debug("API RESULT:\n" . $this->json_print($data) . "\n");
        $this->debug("API RESULT : $data");
        curl_close($ch);

        if ($result) {
            return $result;
        } else {
            $this->debug("Twinkly API error [GET : $method] data=" . print_r($data,TRUE), TRUE);
            throw new Exception("Twinkly API error [GET : $method]");
        }
    }

    // Envoie une methode DELETE à l'API
    private function do_api_delete($method, $authenticated=TRUE)
    {
        $this->debug("## CALL TWINKLY API [DELETE : $method] - auth=$authenticated");
        $ch = curl_init($this->endpoint . "/" . $method);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($authenticated === TRUE) {
            $this->check_token_or_auth();
            $auth_token = $this->token["auth_token"];
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-Auth-Token: " . $auth_token));
        }
        $data = curl_exec($ch);
        $result = json_decode($data, true) or NULL;
        //$this->debug("API RESULT:\n" . $this->json_print($data) . "\n");
        $this->debug("API RESULT : $data");
        curl_close($ch);

        if ($result) {
            return $result;
        } else {
            $this->debug("Twinkly API error [DELETE : $method] data=" . print_r($data,TRUE), TRUE);
            throw new Exception("Twinkly API error [DELETE : $method]");
        }
    }

    // Authentification sur l'API et récupération d'un access token
	// Attention : le contrôleur Twinkly est limité à un seul access token actif à la fois : toute authentification invalidera les précédents tokens
	// et déconnectera donc les autres clients déjà connectés (notamment l'application mobile)
    private function authenticate()
    {
        $this->debug('#### Get new Auth token');
        $challenge = random_bytes(32);
        $b64_challenge = base64_encode($challenge);

        $json = json_encode(array("challenge" => $b64_challenge));
        $result = $this->do_api_post("login", $json, FALSE);

        $auth_token = $result["authentication_token"];
        $auth_expiry = $result["authentication_token_expires_in"];
        $challenge_response = $result["challenge-response"];

        $dk = $this->derive_key($this::SHARED_KEY_CHALLENGE, $this->mac);
        $enc = $this->rc4($challenge, $dk);
        $rsp = sha1($enc);
        $this->debug("Computed challenge response = " . $rsp);

        if ($rsp != $challenge_response) {
            $this->debug("Authentication WARNING : incorrect challenge-response!!!");
            //throw new Exception("Twinkly Authentication error. Incorrect challenge-response. [POST : login]");
        }

        $json = json_encode(array("challenge-response" => $challenge_response));
        $result = $this->do_api_post("verify", $json, TRUE, FALSE, $auth_token);

        if ($result["code"] != "1000") {
            $this->debug("Authentication error...");
            throw new Exception("Twinkly Authentication error. [POST : verify]");
        }
        $expiry_timestamp = (new DateTime())->getTimestamp() + $auth_expiry;
        $this->token = array("auth_token" => $auth_token, "expiry" => $expiry_timestamp);
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
        $result = json_decode(curl_exec($ch), true) or NULL;
        if ($result !== NULL) {
            return $result["version"];
        } else {
            throw new Exception("Cannot get firmware version. Check IP address.");
        }
    }

    // Renvoie le mode actif : off, movie, playlist
    public function get_mode()
    {
        $this->debug('TwinklyString::get_mode');
        $result = $this->do_api_get("led/mode");
        if ($result["code"] != "1000") {
            $this->debug("get_mode error...");
            throw new Exception("get_mode error [GET : led/mode] data=" . print_r($result,TRUE));
        }
        return $result["mode"];
    }

    // Active le mode indiqué : off, movie, playlist
    public function set_mode($mode)
    {
        $this->debug("TwinklyString::set_mode($mode)");
        $json = json_encode(array("mode" => $mode));
        $result = $this->do_api_post("led/mode", $json);

        if ($result["code"] != "1000") {
            $this->debug("set_mode error...");
            throw new Exception("set_mode error [POST : led/mode] data=" . print_r($result,TRUE));
        }
        return TRUE;
    }

    // Renvoie le niveau de luminosité de la guirlande (de 0 à 100)
    public function get_brightness()
    {
        $this->debug("TwinklyString::get_brightness");
        $result = $this->do_api_get("led/out/brightness");
        if ($result["code"] != "1000") {
            $this->debug("get_brightness error...");
            throw new Exception("get_brightness error [GET : led/out/brightness] data=" . print_r($result,TRUE));
        }
        return $result["value"];
    }

    // Choisit le niveau de luminosité de la guirlande (de 0 à 100)
	public function set_brightness($value)
    {
        $this->debug("TwinklyString::set_brightness($value)");
        $current_mode = $this->get_mode();
        if ($current_mode == "movie")
        {
            $json = json_encode(array("type" => "A","value" => intval($value)));
            $result = $this->do_api_post("led/out/brightness", $json);
            if ($result["code"] != "1000") {
                $this->debug("set_brightness error...");
                throw new Exception("set_brightness error [POST : led/out/brightness] data=" . print_r($result,TRUE));
            }
            return TRUE;
        } else {
            $this->debug("brigthness can be set while in movie mode only");
            return FALSE;
        }
    }

    // Renvoie les informations sur la guirlande
    public function get_details()
    {
        $this->debug('TwinklyString::get_details');
        return $result = $this->do_api_get("gestalt", FALSE);
    }

    // Charge une animation dans le contrôleur (mode GEN1)
    public function upload_movie($movie, $leds_number, $frames_number, $frame_delay)
    {
        if (ctype_print($movie)) {
            $this->debug("TwinklyString::upload_movie($movie, $leds_number, $frames_number, $frame_delay)");
            $movie_data = file_get_contents($movie);
            if ($movie_data === false) {
                $this->debug("upload_movie : file not found", TRUE);
                throw new Exception("upload_movie error : file not found");
            }
        } else {
            $this->debug("TwinklyString::upload_movie(bindata, $leds_number, $frames_number, $frame_delay)");
            $movie_data = $movie;
        }

        $this->set_mode("off");

        $this->debug("upload stage 1 (reset)");

        $json = json_encode(array());
        $result = $this->do_api_post("led/reset", $json);
        if ($result["code"] != "1000") {
            $this->debug("upload_movie step 1 error...", TRUE);
            throw new Exception("upload_movie step 1 error [POST : led/reset] data=" . print_r($result,TRUE));
        }

        $this->debug("upload stage 2 (upload data)");
        $result = $this->do_api_post("led/movie/full", $movie_data, TRUE, FALSE, NULL, "application/octet-stream");
        if ($result["code"] != "1000" || $result["frames_number"] != $frames_number) {
            $this->debug("upload_movie step 2 error...", TRUE);
            throw new Exception("upload_movie step 2 error [POST : led/movie/full] data=" . print_r($result,TRUE));
        }

        $this->debug("upload stage 3 (config)");
        $json = json_encode(array("frame_delay" => $frame_delay, "leds_number" => $leds_number, "frames_number" => $frames_number));
        $result = $this->do_api_post("led/movie/config", $json, TRUE, FALSE);
        if ($result["code"] != "1000") {
            $this->debug("upload_movie step 3 error...", TRUE);
            throw new Exception("upload_movie step 3 error [POST : led/movie/config] data=" . print_r($result,TRUE));
        }

        $this->debug("upload stage 4 (reset)");
        $json = json_encode(array());
        $result = $this->do_api_post("led/reset", $json, TRUE, FALSE);
        if ($result["code"] != "1000") {
            $this->debug("upload_movie step 4 error...", TRUE);
            throw new Exception("upload_movie step 4 error [POST : led/reset] data=" . print_r($result,TRUE));
        }

        $this->debug("upload stage 5 (set movie mode)");
        $this->set_mode("movie");

        return TRUE;
    }

    public function upload_movie2($movie_data, $jsonstrparameters) {

        // Si un nom de fichier est passé, on le charge
        if(ctype_print($movie_data)) {
            $this->debug("TwinklyString::upload_movie2($movie_data, $jsonstrparameters)");
            $movie_data = file_get_contents($movie_data);
            if ($movie_data === false) {
                $this->debug("movie file not found");
                throw new Exception("upload_movie error : file not found");
            }
        } else {
            $this->debug("TwinklyString::upload_movie2(bindata, $jsonstrparameters)");
        }

        $jsonparameters = json_decode($jsonstrparameters, TRUE);
        if ($jsonparameters === false) {
            $this->debug('upload_movie2 : invalid movie parameters', TRUE);
            throw new Exception("upload_movie2 error : invalid movie parameters");
        }

        $this->set_mode('off');
        $all_movies = $this->get_movies();
        $unique_id = $jsonparameters["unique_id"];

        $found = FALSE;
        foreach ($all_movies["movies"] as $m) {
            if ($m["unique_id"] == $unique_id) {
                $found = TRUE;
            }
        }

        if ($found == FALSE) {
            $this->debug("upload : add movie to device");
            if($this->add_movie($movie_data, $jsonstrparameters) !== TRUE) {
                $this->debug("upload_movie2 add movie  error...", TRUE);
                throw new Exception("upload_movie2 add movie error [POST : movies/new] data=" . print_r($result,TRUE));
            }
        }    

        $this->debug("upload : set current movie");
        $result = $this->set_current_movie($jsonparameters["unique_id"]);
        if ($result["code"] != "1000") {
            $this->debug("upload_movie2 set movie error...", TRUE);
            throw new Exception("upload_movie2 set movie error [POST : movies/current] data=" . print_r($result,TRUE));
        }

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

    public function add_movie($movie_data, $jsondata)
    {
        $this->debug("TwinklyString::add_movie(bindata, $jsondata)");
        $jsonparameters = json_decode($jsondata, TRUE);

        // Vérifie la place disponible
        $result = $this->do_api_get("movies");
        if ($result["code"] != "1000") {
            $this->debug("TwinklyString::add_movie check available memory error...", TRUE);
            throw new Exception("add_movie check available memory error [GET : movies] data=" . print_r($result,TRUE));
        }

        $capacity = intval($result["available_frames"]);
        $size = intval($jsonparameters["frames_number"]);

        if($size > $capacity) {
            $this->debug("TwinklyString::add_movie : not enough memory left on controler (size = " . $size . " / remaining = " . $capacity . ")", TRUE);
            throw new Exception("add_movie : not enough memory left on controler (size = " . $size . " / remaining = " . $capacity . ")");
        }

        $result = $this->do_api_post("movies/new", $jsondata);
        if ($result["code"] != "1000") {
            $this->debug("add_movie step 1 error...", TRUE);
            throw new Exception("add_movie step 1 error [POST : movies/new] data=" . print_r($result,TRUE));
        }

        $result = $this->do_api_post("movies/full", $movie_data, TRUE, FALSE, NULL, "application/octet-stream");
        if ($result["code"] != "1000" || $result["frames_number"] != $jsonparameters["frames_number"]) {
            $this->debug("add_movie step 2 error..." . print_r($result, TRUE), TRUE);
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
            throw new Exception("delete_playlist error [DELETE : playlist] data=" . print_r($result,TRUE));
        }
        return $result;
    }

    // Créer une nouvelle playlist avec la liste d'animation fournies
    // Le format est un tableau de ["unique_id","json","bin","duration"]
    public function create_new_playlist($movies) 
    {
        $this->set_mode('off');
        //$this->delete_movies();
        $this->delete_playlist();
        return $this->add_to_playlist($movies, TRUE);
    }

    // Ajoute des animations à la playlist courante
    // Le format d'entrée est un tableau de ["unique_id","json","bin","duration"]
    public function add_to_playlist($movies, $newplaylist = FALSE)
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
            if ($newplaylist != TRUE) {
                foreach ($current_playlist as $e) {
                    $pldata["entries"][] = [
                        "duration" => $e["duration"],
                        "unique_id" => $e["unique_id"],
                    ];
                }
            } 

            $this->delete_playlist();
            $this->set_mode('off');

            foreach($movies as $movie) {
                $unique_id = $movie["unique_id"];
                $jsonstr = $movie["json"];
                $json = json_decode($jsonstr, TRUE);
                $bindata = $movie["bin"];
                $duration = 30;
                if (isset($movie["duration"])) {
                    $duration = intval($movie["duration"]);
                }

                $this->debug("Ajout playlist : uid=$unique_id - duration=$duration - json=$jsonstr");

                $found = FALSE;
                foreach ($all_movies["movies"] as $m) {
                    if ($m["unique_id"] == $unique_id) {
                        $found = TRUE;
                    }
                }
                if ($found == FALSE) {
                    $this->debug('Loading movie ' . $json["name"] . ' to the controller');
                    $this->add_movie($bindata, $jsonstr);
                } else {
                    $this->debug('Movie ' . $json["name"] . ' already loaded on controller');
                }

                $pldata["entries"][] = [
                    "duration" => $duration,
                    "unique_id" => $unique_id,
                ];
            }

            $this->update_playlist(json_encode($pldata));
            if ($current_movie !== NULL) {
                $this->set_current_movie($current_movie["unique_id"]);
            } else {
                $firstitem = reset($movies);
                $this->set_current_movie($firstitem["unique_id"]);
            }
            $this->set_mode('playlist');

            return TRUE;
        } else {
            $this->debug('add_to_playlist : wrong parameter format', TRUE);
            throw new Exception("add_to_playlist error - wrong parameter format");
        }
    }

    public function set_token($jsontoken) {
        if ($jsontoken !== NULL) {
            $tokendata = json_decode($jsontoken, TRUE);
            if (isset($tokendata['auth_token'][0])) {
                if (intval($tokendata['expiry']) > 0) {
                    $this->token = $tokendata;
                    return TRUE;
                }
            }
            throw new Exception('set_token error - invalid token json string');
        } else {
            return FALSE;
        }
    }


    public function get_token() {
        return json_encode($this->token);
    }
}
?>
