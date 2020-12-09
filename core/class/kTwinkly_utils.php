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
    $allproducts = json_decode(file_get_contents(__DIR__ . '/../config/products.json'));
    $result = NULL;
    foreach($allproducts as $p) {
        if ($p->product_code == $_product_code) {
            $result = $p;
            break;
        }
    }
    return (array)$result;
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
function version_supports_brigthness($_fw_version) {
    if (versionToInt($_fw_version) >= versionToInt("2.3.0")) {
        return TRUE;
    } else {
        return FALSE;
    }
}

// Indique le mode de chargement des animations en fonction de la version du firmware
function version_upload_type($_fw_version) {
    if (versionToInt($_fw_version) >= versionToInt("2.5.5")) {
        return "v1";
    } else {
        return "v2";
    }
}

// Génère un GUI utilisé pour stocker les animations capturées via le proxy
function generate_GUID() {
    $data = openssl_random_pseudo_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
    return strtoupper(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4)));
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

?>
