
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


$('#bt_discover').off('click').on('click',function(){
  $.ajax({
    type: "POST",
    url: "plugins/kTwinkly/core/ajax/kTwinkly.ajax.php",
    data: {
      action: "discoverDevices",
    },
    dataType: 'json',
    error: function (request, status, error) {
      handleAjaxError(request, status, error);
    },
    success: function (data) {
      if (data.state != 'ok') {
        $('#div_alert').showAlert({message: data.result, level: 'danger'});
        return;
      }
      $('#div_alert').showAlert({message: '{{Synchronisation réussie}}', level: 'success'});
      setTimeout(window.location.reload(), 5000);
    }
  });
})


/*
* Fonction permettant l'affichage des commandes dans l'équipement
*/
function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
     var _cmd = {configuration: {}};
   }
   if (!isset(_cmd.configuration)) {
     _cmd.configuration = {};
   }
	//console.log(_cmd);
   var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
   tr += '<td style="min-width:50px;width:70px;">';
   tr += '<span class="cmdAttr" data-l1key="id"></span>';
   tr += '</td>';
   tr += '<td style="min-width:300px;width:350px;">';
   tr += '<div class="row">';
   tr += '<div class="col-xs-7">';
   tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" placeholder="{{Nom de la commande}}">';
   tr += '</div>';
   tr += '<div class="col-xs-5">';
   tr += '<a class="cmdAction btn btn-default btn-sm" data-l1key="chooseIcon"><i class="fas fa-flag"></i> {{Icône}}</a>';
   tr += '<span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left : 10px;"></span>';
   tr += '</div>';
   tr += '</div>';
   tr += '</td>';
   tr += '<td style="min-width:120px;width:140px;">';
   tr += '<div><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></div> ';
   tr += '<td>';
   if (is_numeric(_cmd.id)) {
     tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
     tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> Tester</a>';
   }
   //tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i></td>';
   tr += '</tr>';
   $('#table_cmd tbody').append(tr);
   var tr = $('#table_cmd tbody tr').last();
   jeedom.eqLogic.builSelectCmd({
     id:  $('.eqLogicAttr[data-l1key=id]').value(),
     filter: {type: 'info'},
     error: function (error) {
       $('#div_alert').showAlert({message: error.message, level: 'danger'});
     },
     success: function (result) {
       tr.find('.cmdAttr[data-l1key=value]').append(result);
       tr.setValues(_cmd, '.cmdAttr');
       jeedom.cmd.changeType(tr, init(_cmd.subType));
     }
   });
 }


function printEqLogic(_eqLogic) {
  if (_eqLogic.id != '') {
    $('#img_device').attr("src", $('.eqLogicDisplayCard[data-eqLogic_id=' + _eqLogic.id + '] img').attr('src'));
    if (_eqLogic.configuration['devicetype'] == "leds") { 
      $('#bt_movies').css('visibility', 'visible');
      $('#info_leds_1').css('visibility', 'visible');
      $('#info_leds_2').css('visibility', 'visible');
      if (_eqLogic.configuration['hwgen'] !== "1") { 
          $('#bt_playlists').css('visibility', 'visible');
          $('#cb_clearmemory').css('visibility', 'visible');
      } else {        
          $('#bt_playlists').css('visibility', 'hidden');
          $('#cb_clearmemory').css('visibility', 'hidden');
      }
    } else {
      $('#bt_movies').css('visibility', 'hidden');
      $('#info_leds_1').css('visibility', 'hidden');
      $('#info_leds_2').css('visibility', 'hidden');
      $('#bt_playlists').css('visibility', 'hidden');
      $('#cb_clearmemory').css('visibility', 'hidden');
    }

    /*
    * Permet la réorganisation des commandes dans l'équipement
    */
    $("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});

    $("#bt_movies").off('click').on('click', function() {
      $('#md_modal').dialog({
            title: "{{Gestion des animations}}",
            beforeClose: function() {
                if (moviesNotSaved == 1) {
                    bootbox.confirm('{{Etes-vous sûr de vouloir quitter sans sauvegarder les modifications}} ?', function (result) {
                        if(result) {
                            $('#md_modal').dialog('option', 'beforeClose', function() {})
                            $('#md_modal').dialog("close");
                        }
                    });
                    return false;
                } else {
                    return true;
                }
            }
        }).load('index.php?v=d&plugin=kTwinkly&modal=movies&id=' + $('.eqLogicAttr[data-l1key=id]').value()).dialog('open');
    });

    $("#bt_playlists").off('click').on('click', function() {
      $('#md_modal').dialog({
            title: "{{Gestion de la playlist}}",
            beforeClose: function() {
                if (playlistNotSaved == 1) {
                    bootbox.confirm('{{Etes-vous sûr de vouloir quitter sans sauvegarder les modifications}} ?', function (result) {
                        if(result) {
                            $('#md_modal').dialog('option', 'beforeClose', function() {})
                            $('#md_modal').dialog("close");
                        }
                    });
                    return false;
                } else {
                    return true;
                }
            } 
        }).load('index.php?v=d&plugin=kTwinkly&modal=playlist&id=' + $('.eqLogicAttr[data-l1key=id]').value()).dialog('open');
    });

    $("#bt_mqtt").off('click').on('click', function() {
      $('#md_modal')
            .dialog({ title: "{{Configuration MQTT}}" })
          .load('index.php?v=d&plugin=kTwinkly&modal=mqtt&id=' + $('.eqLogicAttr[data-l1key=id]').value()).dialog('open');
    });

    $("#bt_import").fileupload({
      replaceFileInput: false,
      url: 'plugins/kTwinkly/core/ajax/kTwinkly.ajax.php?action=importAll&id=' + _eqLogic.id,
      dataType: 'json',
      done: function (e, data) {
        if (data.result.state != 'ok') {
          $('#div_alert').showAlert({message: data.result.result, level: 'danger'});
          return;
        }else{
          $('#div_alert').showAlert({message: '{{Fichier envoyé avec succès}}', level: 'success'});
        }
      }
    });

    $("#bt_export").off('click').on('click', function() {
      $.ajax({
        type: "POST",
        url: 'plugins/kTwinkly/core/ajax/kTwinkly.ajax.php',
        data: {
            'action': 'exportAll',
            'id': $('.eqLogicAttr[data-l1key=id]').value()
              },
        datatype: 'json',
        error: function(request, status, error) { },
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result.result, level: 'danger'});
                return;
            } else {
              $('#div_alert').showAlert({message: '{{Export réussi}}', level: 'success'});
              window.open('core/php/downloadFile.php?pathfile='+data.result.exportFile, "_blank", null)
            }
        }
      });
    });
  } 
}
