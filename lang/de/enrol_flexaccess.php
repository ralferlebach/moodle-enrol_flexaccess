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
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accesskeyinvalid'] = 'Der Zugangsschlüssel ist nicht gültig.';
$string['accesskeymode:course'] = 'Eigenen Kurs-Zugangsschlüssel verwenden';
$string['accesskeymode:inherit'] = 'Systemeinstellung erben';
$string['accessnotopen'] = 'FlexAccess-Zugang ist derzeit nicht verfügbar.';
$string['allowwidening'] = 'Erweiterung durch untergeordnete Ebenen erlauben';
$string['allowwidening_desc'] = 'Wenn deaktiviert, kann ein Kurs/Kursbereich eine auf höherer Ebene verbotene Zugangsmethode nicht wieder aktivieren.';
$string['availablefrom'] = 'Verfügbar ab';
$string['availablefrom_help'] = 'Frühester Zeitpunkt, zu dem diese Instanz FlexAccess-Zugang anbietet. Leer/deaktiviert bedeutet keine Untergrenze. Unabhängig von Account- und Einschreibelaufzeit und mit dem Zugangsschlüssel kombinierbar.';
$string['availableuntil'] = 'Verfügbar bis';
$string['availableuntil_help'] = 'Spätester Zeitpunkt, zu dem diese Instanz FlexAccess-Zugang anbietet (exklusiv). Leer/deaktiviert bedeutet keine Obergrenze.';
$string['coursefull'] = 'Die maximale Teilnehmerzahl für diese Zugangsart ist erreicht.';
$string['error:capacitylock'] = 'Die Kapazitätssperre konnte nicht gesetzt werden. Bitte erneut versuchen.';
$string['error:maxparticipants'] = 'Die maximale Teilnehmerzahl muss 0 (unbegrenzt) oder eine positive Zahl sein.';
$string['error:windowrange'] = 'Der Zeitpunkt „Verfügbar bis" muss nach „Verfügbar ab" liegen.';
$string['flexaccess:config'] = 'FlexAccess-Einschreibeinstanzen konfigurieren';
$string['flexaccess:manage'] = 'FlexAccess-Einschreibungen verwalten';
$string['flexaccess:unenrol'] = 'Nutzer aus einer FlexAccess-Instanz austragen';
$string['hide'] = 'Ausblenden';
$string['maxparticipants'] = 'Maximale Teilnehmerzahl';
$string['maxparticipants_help'] = 'Maximale Anzahl aktiver FlexAccess-Einschreibungen dieser Instanz. 0 bedeutet unbegrenzt. Abgelaufene Zugänge geben Plätze frei. Keine Warteliste.';
$string['participantvisibilitydefault'] = 'Standard-Sichtbarkeit temporärer Nutzer in Teilnehmerlisten';
$string['participantvisibilitydefault_desc'] = 'Systemstandard. Eine Kursinstanz kann erben, anzeigen oder ausblenden.';
$string['pluginname'] = 'FlexAccess-Einschreibung';
$string['privacy:metadata'] = 'FlexAccess-Einschreibung speichert nur Policy- und Einschreibekonfiguration; Nutzereinschreibungen liegen in Moodle-Core.';
$string['settings:access'] = 'Zugangszeitraum und Kapazität';
$string['settings:accesskey'] = 'Zugangsschlüssel für temporäre Nutzer';
$string['settings:defaults'] = 'Policy-Standards';
$string['show'] = 'Anzeigen';
$string['status'] = 'FlexAccess-Einschreibung aktivieren';
$string['task:expireenrolments'] = 'FlexAccess-Kurseinschreibungen ablaufen lassen';
$string['temporaryaccesskey'] = 'Systemweiter Zugangsschlüssel';
$string['temporaryaccesskey_desc'] = 'Neuen gemeinsamen Zugangsschlüssel eingeben. Gespeichert wird nur ein sicherer Hash. Leer lassen, um den vorhandenen Schlüssel unverändert zu lassen; die separate Option schaltet die Pflicht ein oder aus.';
$string['temporaryaccesskeyrequired'] = 'Systemweiten Zugangsschlüssel verlangen';
$string['temporaryaccesskeyrequired_desc'] = 'Verlangt für FlexAccess-Zugänge, die einen temporary user erzeugen, systemweit einen Zugangsschlüssel. Kurse dürfen ihn erben oder durch einen eigenen Schlüssel ersetzen.';
