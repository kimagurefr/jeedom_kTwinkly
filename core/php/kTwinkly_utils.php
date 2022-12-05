<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */

require_once __DIR__  . '/../../../../core/php/core.inc.php';

// Renvoie les informations du produit depuis la table de configuration récupérée de l'appli mobile Android
function get_product_info($_product_code) {
    $allproducts = json_decode(file_get_contents(__DIR__ . '/../config/products.json'), TRUE);
    $result = NULL;
    foreach($allproducts as $p) {
        if ($p["product_code"] == $_product_code) {
            $result = $p;
            break;
        }
    }
    return (array)$result;
}

// Renvoie les informations du produit depuis la table de configuration du plugin
function get_custom_info($_product_code) {
    $allproducts = json_decode(file_get_contents(__DIR__ . '/../config/products_custom.json'), TRUE);
    $result = NULL;
    foreach($allproducts as $p) {
        if ($p["product_code"] == $_product_code) {
            $result = $p;
            break;
        }
    }
    return (array)$result;
}

// Renvoie l'image du produit, depuis products_custom.json (priorité 1) ou depuis products.json (priorité 2), ou une image par défaut
function get_product_image($_product_code) {
    $custominfo = get_custom_info($_product_code);
    $info = get_product_info($_product_code);

    if(array_key_exists("pack_preview", $custominfo)) {
        // L'image existe dans le fichier products_custom.json, on la prend en priorité
        return $custominfo["pack_preview"];
    } elseif(array_key_exists("pack_preview", $info)) {
        // L'image existe dans le fichier products.json récupéré de l'app Twinkly
        return $info["pack_preview"];
    } elseif (file_exists(__DIR__ . '/../config/images/' . $info["product_code"] . '.png')) {
        return $info["product_code"] . '.png';
    } else {
        // Image par défaut
        return "default.png";
    }
}

// Transforme un numéro de version de firmware de la forme x.x.x en entier pour faciliter la comparaison de versions
function versionToInt($_str) {
    $split = explode('.',$_str);
    $c = count($split);
    $i = -1;

    if ($c > 0) {
        $i = intval($split[0]) * 1000000;
    }
    if ($c > 1) {
        $i += intval($split[1]) * 1000;
    }
    if ($c > 2) {
        $i += intval($split[2]);
    }
    return $i;
}

// Indique si la version de firmware supporte la fonctionnalité de réglage de la luminosité
function version_supports_brightness($_fw_family, $_fw_version) {
    if (versionToInt($_fw_version) >= versionToInt("2.3.0")) {
        return TRUE;
    } else {
        return FALSE;
    }
}

// Indique si la version de firmware supporte la fonctionnalité couleur
function version_supports_color($_fw_family, $_fw_version) {
    if (versionToInt($_fw_version) >= versionToInt("2.7.1")) {
        return TRUE;
    } else {
        return FALSE;
    }
}

// Indique si la version de firmware supporte la liste des animations
function version_supports_getmovies($_fw_family, $_fw_version) {
    if (versionToInt($_fw_version) >= versionToInt("2.5.6")) {
        return TRUE;
    } else {
        return FALSE;
    }
}

// Indique le mode de chargement des animations en fonction de la version du firmware
function version_upload_type($_fw_family, $_fw_version) {
    if (versionToInt($_fw_version) >= versionToInt("2.5.5")) {
        return "v1";
    } else {
        return "v2";
    }
}


function convert_rgb_to_string_json($_json_color) {
    if(is_array($_json_color)) {        
        return "#" . sprintf('%02X', $_json_color["red"]) . sprintf('%02X', $_json_color["green"]) . sprintf('%02X', $_json_color["blue"]);
    } else {
        return "#000000";
    }
}

function convert_rgb_to_string($red, $green, $blue) {
    if(is_array($_json_color)) {        
        return "#" . sprintf('%02X', $red) . sprintf('%02X', $green) . sprintf('%02X', $blue);
    } else {
        return "#000000";
    }
}

// Génère un GUID utilisé pour stocker les animations capturées via le proxy
function generate_GUID() {
    $data = openssl_random_pseudo_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
    return strtoupper(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4)));
}

function convert_cache_to_listvalue($cache) {
    if(is_array($cache)) {
        $lv = "";
        foreach($cache as $m) {
            $lv .= ";" . $m["file"] . '|' . $m["name"];
        }
        $lv = substr($lv, 1);
        return $lv;
    } else {
        return "";
    }
}

function sanitize_filename($filename) {
    // Remplace les caractères accentués
    $newname = iconv(mb_detect_encoding($filename, mb_detect_order(), true),'ASCII//TRANSLIT',$filename);
    // Supprime les espaces et caractères spéciaux
    $newname = mb_ereg_replace("([^\w\d\-_~,;\[\]\(\).])", '', $newname);
    // Supprime les '..'
    $newname = mb_ereg_replace("([\.]{2,})", '', $newname);

    return $newname;
}

function create_playlist_item($zipfile, $duration=30) {
    $zip = new ZipArchive();
    if ($zip->open($zipfile) === TRUE) {
        for ($i=0; $i<$zip->numFiles; $i++) {
            $zfilename = $zip->statIndex($i)["name"];
            if (preg_match('/bin$/',strtolower($zfilename))) {
                $bin_data = $zip->getFromIndex($i);
            }
            if (preg_match('/json$/',strtolower($zfilename))) {
                $jsonstring = $zip->getFromIndex($i);
                $json = json_decode($jsonstring, TRUE);
            }
        }

        $item = [
            "unique_id" => $json["unique_id"],
            "json" => $jsonstring,
            "bin" => $bin_data,
            "duration" => $duration,
        ];

        return $item;
    } else {
        return NULL;
    }
}

function generate_device_id($mac) 
{
    return "Twinkly-" . str_replace(":","",$mac);
}

function get_movie_cache($id) {
    if($id !== "" && $id !== NULL) {
        $movieCacheFile = __DIR__ . '/../../data/moviecache_' . $id . '.json';
        if(file_exists($movieCacheFile)) {
            $movieCache = json_decode(file_get_contents($movieCacheFile), TRUE);
            return $movieCache;
        }
    } else {
        return array();
    }
}

function get_movie_from_cache($cache, $unique_id) {
    if(is_array($cache) && (count($cache)>0)) {
        $movieIndex = array_search(strtolower($unique_id), array_column($cache, 'unique_id'));
        if($movieIndex !== false) {
            return $cache[$movieIndex];
        }
    }
    return null;
}

?>
