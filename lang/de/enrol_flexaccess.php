<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings for enrol_flexaccess.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'FlexAccess-Einschreibung';
$string['settings:defaults'] = 'Policy-Standards';
$string['participantvisibilitydefault'] = 'Standard-Sichtbarkeit temporärer Nutzer in Teilnehmerlisten';
$string['participantvisibilitydefault_desc'] = 'Systemstandard. Eine Kursinstanz kann erben, anzeigen oder ausblenden.';
$string['show'] = 'Anzeigen';
$string['hide'] = 'Ausblenden';
$string['allowwidening'] = 'Erweiterung durch untergeordnete Ebenen erlauben';
$string['allowwidening_desc'] = 'Wenn deaktiviert, kann ein Kurs/Kursbereich eine auf höherer Ebene verbotene Zugangsmethode nicht wieder aktivieren.';
$string['task:expireenrolments'] = 'FlexAccess-Kurseinschreibungen ablaufen lassen';
$string['privacy:metadata'] = 'FlexAccess-Einschreibung speichert nur Policy- und Einschreibekonfiguration; Nutzereinschreibungen liegen in Moodle-Core.';

$string['settings:accesskey'] = 'Zugangsschlüssel für temporäre Nutzer';
$string['temporaryaccesskeyrequired'] = 'Systemweiten Zugangsschlüssel verlangen';
$string['temporaryaccesskeyrequired_desc'] = 'Verlangt für FlexAccess-Zugänge, die einen temporary user erzeugen, systemweit einen Zugangsschlüssel. Kurse dürfen ihn erben oder durch einen eigenen Schlüssel ersetzen.';
$string['temporaryaccesskey'] = 'Systemweiter Zugangsschlüssel';
$string['temporaryaccesskey_desc'] = 'Neuen gemeinsamen Zugangsschlüssel eingeben. Gespeichert wird nur ein sicherer Hash. Leer lassen, um den vorhandenen Schlüssel unverändert zu lassen; die separate Option schaltet die Pflicht ein oder aus.';
$string['accesskeymode:inherit'] = 'Systemeinstellung erben';
$string['accesskeymode:course'] = 'Eigenen Kurs-Zugangsschlüssel verwenden';
$string['accesskeyinvalid'] = 'Der Zugangsschlüssel ist nicht gültig.';
