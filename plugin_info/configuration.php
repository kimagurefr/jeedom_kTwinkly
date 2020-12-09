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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect('admin')) {
        throw new Exception('{{401 - Accès non autorisé}}');
}
?>
<form class="form-horizontal">
    <fieldset>
        <div class="form-group">
            <label class="col-lg-4 control-label">{{Fréquence de rafraichissement automatique (en secondes)}}</label>
            <div class="col-lg-4">
                <input id="ktrefreshfreq" class="configKey form-control" data-l1key="refreshFrequency" placeholder="10"/>
            </div>
        </div>
        <div class="form-group">
            <label class="col-lg-4 control-label">{{Port du proxy de capture des animations}}</label>
            <div class="col-lg-4">
                <input id="ktmitmport" class="configKey form-control" data-l1key="mitmPort" placeholder="14233"/>
            </div>
        </div>
        <div class="form-group">
            <label class="col-lg-4 control-label">{{Logs de debuggage additionelles}}</label>
            <div class="col-lg-4">
                <input type="checkbox" class="configKey" data-l1key="additionalDebugLogs"/>
            </div>
        </div>
    </fieldset>
</form>

