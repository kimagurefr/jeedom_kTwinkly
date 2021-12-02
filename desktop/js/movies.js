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

$("#moviesList").sortable({
    axis: "y",
    cursor: "move",
    items: ".movie",
    placeholder: "ui-state-highlight",
    tolerance: "intersect",
    forcePlaceholderSize: true,
    update: function( event, ui ) { moviesNotSaved = 1; }
});

$('#md_modal').on('dialogclose', function(event) {
    $('#md_modal').off('dialogclose');
    console.log('equipement id = ' + $('.eqLogicAttr[data-l1key=id]').value());

    $.ajax({
        type: "POST",
        url: 'plugins/kTwinkly/core/ajax/kTwinkly.ajax.php',
        data: {
            'action': 'stopProxy',
            'id': $('.eqLogicAttr[data-l1key=id]').value()
        },
        datatype: "json",
        error: function(request, status, error) { },
        success: function (data) { }
    });
});

$('#bt_uploadMovie').fileupload({
    replaceFileInput: false,
    url: 'plugins/kTwinkly/core/ajax/kTwinkly.ajax.php?action=uploadMovie&id=' + $('.eqLogicAttr[data-l1key=id]').value(),
    dataType: 'json',
    done: function (e, data) {
      if (data.result.state != 'ok') {
        $('#div_alert_movies').showAlert({message: data.result.result, level: 'danger'});
        return;
      }else{
        //$('#div_alert_movies').showAlert({message: '{{Fichier envoyé avec succès}}', level: 'success'});
        $('#md_modal').load('index.php?v=d&plugin=kTwinkly&modal=movies&id=' + $('.eqLogicAttr[data-l1key=id]').value() + '&reload=1');
        moviesNotSaved = 1;
      }
    }
});

$('#bt_deleteMovie').off('click').on('click', function() {
    var nbdeletions = $('.kTWinklyMovieItem:checked').length;
    if (nbdeletions > 0) {
        bootbox.confirm('{{Etes-vous sûr de vouloir supprimer les}} ' + nbdeletions + ' {{élément(s) sélectionné(s)}} ?', function (result) {
            if (result) {
                $('#moviesList #action').val('deleteMovie');
                $.ajax({
                    type: "POST",
                    url: 'plugins/kTwinkly/core/ajax/kTwinkly.ajax.php',
                    data: $("#moviesList").serialize(),
                    datatype: "json",
                    error: function(request, status, error) { },
                    success: function (data) {
                        if (data.state != 'ok') {
                            $('#div_alert_movies').showAlert({message: data.result, level: 'danger'});
                            return;
                        }
                        $('#md_modal').load('index.php?v=d&plugin=kTwinkly&modal=movies&id=' + $('.eqLogicAttr[data-l1key=id]').value() + '&reload=1');
                        moviesNotSaved = 1;
                    }
                });
            }
        });
    }
});

$('#bt_saveMovie').off('click').on('click', function() {
    $('#moviesList #action').val('saveMovie');
    $.ajax({
      type: "POST",
      url: 'plugins/kTwinkly/core/ajax/kTwinkly.ajax.php',
      data: $("#moviesList").serialize(),
      datatype: 'json',
      error: function(request, status, error) { },
      success: function (data) {
        if (data.state != 'ok') { 
          $('#div_alert_movies').showAlert({message: data.result, level: 'danger'});
          return;
        }
        //$('#md_modal').dialog('close');
        moviesNotSaved = 0;
      }
    });
});

$('.changeProxyState').off('click').on('click', function () {
    console.log('old proxy state = ' + $(this).attr('data-state'));
    $.ajax({
        type: "POST",
        url: 'plugins/kTwinkly/core/ajax/kTwinkly.ajax.php',
        data: {
            'id': $('.eqLogicAttr[data-l1key=id]').value(),
            'action': 'changeproxystate',
            'proxy_enabled': $(this).attr('data-state')
        },
        datatype: 'json',
        error: function(request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data) {
            console.log('ajax result = ' + data.state);
            if (data.state != 'ok') {
                 $('#div_alert_movies').showAlert({message: data.result, level: 'danger'});
            } else {
                newstate = data.result.proxy_enabled;
                newmovies = data.result.newmovies;
                console.log('new proxy state = ' + newstate);
                console.log('new movies found = ' + newmovies);
                if (newmovies > 0) {
                    moviesNotSaved = 1;
                }
                $('#md_modal').load('index.php?v=d&plugin=kTwinkly&modal=movies&id=' + $('.eqLogicAttr[data-l1key=id]').value() + '&proxy=' + newstate + '&newmovies=' + newmovies + '&reload=1');
            }
            return;
        }
    })
});
