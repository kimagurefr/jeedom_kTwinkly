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

$('#bt_closeMqtt').off('click').on('click', function() {
  $('#md_modal').dialog("close");
});

$('#bt_saveMqtt').off('click').on('click', function() {
  $.ajax({
    	type: "POST",
    	url: 'plugins/kTwinkly/core/ajax/kTwinkly.ajax.php',	    
    	data: $("#mqttForm").serialize(),
	datatype: "json",
	error: function(request, status, error) { },
      	success: function (data) {
        	if (data.state != 'ok') {
          		$('#div_alert_movies').showAlert({message: data.result, level: 'danger'});
          		return;
        	}
        	$('#md_modal').dialog('close');
      	}
    });
});

