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

if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}

require_once __DIR__ . '/../../core/class/TwinklyString.class.php';

$eqId = $_GET["id"];

$eqLogic = eqLogic::byId($eqId);

$ip = $eqLogic->getConfiguration('ipaddress');
$mac = $eqLogic->getConfiguration('macaddress');

$t = new TwinklyString($ip, $mac, FALSE);
$mqtt = $t->get_mqtt_configuration();

$broker_ip = $mqtt["broker_host"];
$broker_port = $mqtt["broker_port"];
$client_id = $mqtt["client_id"];
$default_id = str_replace(array(':','-'),'',strtoupper($mac));
$user = $mqtt["user"];
?>

<form id="mqttForm">
<fieldset>
	<legend><i class="fas fa-wrench"></i> {{Paramètres MQTT}}</legend>
	<div class="form-group">
		<label class="col-sm-3 control-label">{{Adresse du broker}}</label>
		<div class="col-xs-11 col-sm-7">
		<input type="text" name="mqttBroker" class="eqLogicAttr form-control" placeholder="192.168.0.100" value="<?php echo $broker_ip; ?>" id="mqtt_host"/>
		</div>
		<label class="col-sm-3 control-label">{{Port}}</label>
		<div class="col-xs-11 col-sm-7">
		<input type="text" name="mqttPort" class="eqLogicAttr form-control" placeholder="1883" value="<?php echo $broker_port; ?>" id="mqtt_port"/>
		</div>
		<label class="col-sm-3 control-label">{{ID client}}</label>
		<div class="col-xs-11 col-sm-7">
		<input type="text" name="mqttClientId" class="eqLogicAttr form-control" placeholder="<?php echo $default_id; ?>" value="<?php echo $client_id; ?>" id="mqtt_clientid"/>
		</div>
		<label class="col-sm-3 control-label">{{Utilisateur}}</label>
		<div class="col-xs-11 col-sm-7">
		<input type="text" name="mqttUser" class="eqLogicAttr form-control" placeholder="twinkly" value="<?php echo $user; ?>" id="mqtt_user"/>
		</div>
		<div  class="col-xs-11 col-sm-7">
		<span class="btn btn-default" id="bt_saveMqtt">{{Enregistrer}}</span> <span class="btn btn-default" id="bt_closeMqtt">{{Fermer}}</span>
		</div>
	</div>
	<input type="hidden" name="action" value="updateMqtt">
	<input type="hidden" name="id" value="<?php echo $eqId; ?>">
</fieldset>
</form>

<?php include_file('desktop', 'mqtt', 'js', 'kTwinkly');?>
