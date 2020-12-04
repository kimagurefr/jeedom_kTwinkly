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

// Fonction exécutée automatiquement après l'installation du plugin
  function kTwinkly_install() {
      config::save('refreshFrequency','10','kTwinkly');
      config::save('mitmPort','14233','kTwinkly');

      log::add('kTwinkly','debug','Install cron refreshstate');
      $cron = cron::byClassAndFunction('kTwinkly', 'refreshstate');
      if (!is_object($cron)) {
          $cron = new cron();
          $cron->setClass('kTwinkly');
          $cron->setFunction('refreshstate');
          $cron->setEnable(1);
          $cron->setDeamon(1);
          $cron->setDeamonSleepTime(10);
          $cron->setSchedule('* * * * *');
          $cron->setTimeout(1440);
          $cron->save();
      }

      kTwinkly::deamon_start();
      foreach (kTwinkly::byType('kTwinkly') as $t) {
          $t->save();
      }

      // Rend mitmproxy executable
      chmod(__DIR__ . '/../resources/mitmproxy/`dpkg --print-architecture`/mitmdump', 0755);
  }

// Fonction exécutée automatiquement après la mise à jour du plugin
  function kTwinkly_update() {
      foreach (kTwinkly::byType('kTwinkly') as $t) {
          $t->save();
      }

      $refreshFrequency = config::byKey('refreshFrequency','kTwinkly');
      if ($refreshFrequency == '') {
        config::save('refreshFrequency','10','kTwinkly');
      }

      $mitmPort = config::byKey('mitmPort','kTwinkly');
      if ($mitmPort == '') {
        config::save('mitmPort','14233','kTwinkly');
      }

      log::add('kTwinkly','debug','Update cron refreshstate');

      $cron = cron::byClassAndFunction('kTwinkly', 'refreshstate');
      if (!is_object($cron)) {
          $cron = new cron();
      }
      $cron->setClass('kTwinkly');
      $cron->setFunction('refreshstate');
      $cron->setEnable(1);
      $cron->setDeamon(1);
      $cron->setDeamonSleepTime(intval($refreshFrequency));
      $cron->setSchedule('* * * * *');
      $cron->setTimeout(1440);
      $cron->save();

      kTwinkly::deamon_start();

      // Rend mitmproxy executable
      chmod(__DIR__ . '/../resources/mitmproxy/`dpkg --print-architecture`/mitmdump', 0755);
  }

// Fonction exécutée automatiquement après la suppression du plugin
  function kTwinkly_remove() {
      log::add('kTwinkly','debug','Remove cron refreshstate');
      $cron = cron::byClassAndFunction('kTwinkly', 'refreshstate');
      if (is_object($cron)) {
          $cron->remove();
      }
  }

?>
