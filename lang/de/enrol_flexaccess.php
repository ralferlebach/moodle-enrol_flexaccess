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

$string['access:enter'] = 'Kurs betreten';
$string['access:enterintro'] = 'Dieser Kurs nutzt FlexAccess. Betreten Sie ihn mit Ihrem bestehenden Konto:';
$string['accesskeyinvalid'] = 'Der Zugangsschlüssel ist nicht gültig.';
$string['accesskeymode:course'] = 'Eigenen Kurs-Zugangsschlüssel verwenden';
$string['accesskeymode:inherit'] = 'Systemeinstellung erben';
$string['accesslists'] = 'Anonyme Zugangslisten';
$string['accessnotopen'] = 'FlexAccess-Zugang ist derzeit nicht verfügbar.';
$string['allowguest'] = 'Normalen Gastzugang erlauben';
$string['allowguest_help'] = 'Bietet den Moodle-Gastzugang als eine der FlexAccess-Zugangsoptionen an.';
$string['allownormallogin'] = 'Normalen Login erlauben';
$string['allownormallogin_help'] = 'Hält den Standard-Moodle-Login neben den FlexAccess-Methoden verfügbar.';
$string['allowquick'] = 'Schnellregistrierung erlauben';
$string['allowquick_help'] = 'Bietet eine leichtgewichtige Registrierung, die ein dauerhaftes Konto mit minimalen Angaben anlegt.';
$string['allowtemporary'] = 'Temporäre Nutzer erlauben';
$string['allowtemporary_help'] = 'Bietet niedrigschwelligen temporären Zugang, der ein kurzlebiges Konto anlegt und einschreibt.';
$string['allowwidening'] = 'Erweiterung durch untergeordnete Ebenen erlauben';
$string['allowwidening_desc'] = 'Wenn deaktiviert, kann ein Kurs/Kursbereich eine auf höherer Ebene verbotene Zugangsmethode nicht wieder aktivieren.';
$string['availablefrom'] = 'Verfügbar ab';
$string['availablefrom_help'] = 'Frühester Zeitpunkt, zu dem diese Instanz FlexAccess-Zugang anbietet. Leer/deaktiviert bedeutet keine Untergrenze. Unabhängig von Account- und Einschreibelaufzeit und mit dem Zugangsschlüssel kombinierbar.';
$string['availableuntil'] = 'Verfügbar bis';
$string['availableuntil_help'] = 'Spätester Zeitpunkt, zu dem diese Instanz FlexAccess-Zugang anbietet (exklusiv). Leer/deaktiviert bedeutet keine Obergrenze.';
$string['check:coupling'] = 'FlexAccess-Kopplung Auth/Enrol';
$string['check:coupling:action'] = 'Einschreibe-Plugins verwalten';
$string['check:coupling:authonly'] = 'auth_flexaccess ist aktiv, enrol_flexaccess jedoch nicht';
$string['check:coupling:authonly_detail'] = 'Das Authentifizierungs-Plugin ist aktiv, aber kein Kurs kann einen FlexAccess-Einstieg anbieten, weil das Einschreibe-Plugin deaktiviert ist. Aktivieren Sie enrol_flexaccess oder deaktivieren Sie auth_flexaccess, falls FlexAccess nicht genutzt wird.';
$string['check:coupling:bothoff'] = 'FlexAccess ist nicht aktiviert.';
$string['check:coupling:enrolonly'] = 'enrol_flexaccess ist aktiv, auth_flexaccess jedoch nicht';
$string['check:coupling:enrolonly_detail'] = 'Das Einschreibe-Plugin provisioniert temporäre und schnellregistrierte Konten, die sich über auth_flexaccess anmelden. Ist dieses Authentifizierungs-Plugin deaktiviert, können sich diese Konten nicht anmelden. Aktivieren Sie auth_flexaccess.';
$string['check:coupling:ok'] = 'auth_flexaccess und enrol_flexaccess sind beide aktiviert.';
$string['coursefull'] = 'Die maximale Teilnehmerzahl für diese Zugangsart ist erreicht.';
$string['enrolperiod'] = 'Einschreibungsdauer';
$string['enrolperiod_help'] = 'Wie lange eine neue Einschreibung aktiv bleibt, unabhängig von der Lebensdauer des temporären Kontos. Wenn gesetzt, endet die Einschreibung nach diesem Zeitraum und die Ablaufaktion wird angewendet. Bei null bleibt die Einschreibung bestehen, bis das Konto abläuft oder sie manuell entfernt wird.';
$string['error:capacitylock'] = 'Die Kapazitätssperre konnte nicht gesetzt werden. Bitte erneut versuchen.';
$string['error:maxparticipants'] = 'Die maximale Teilnehmerzahl muss 0 (unbegrenzt) oder eine positive Zahl sein.';
$string['error:windowrange'] = 'Der Zeitpunkt „Verfügbar bis" muss nach „Verfügbar ab" liegen.';
$string['expiryaction'] = 'Ablaufaktion';
$string['expiryaction:suspend'] = 'Einschreibung aussetzen';
$string['expiryaction:unenrol'] = 'Nutzer austragen';
$string['expiryaction_help'] = 'Was mit einer FlexAccess-Einschreibung geschieht, wenn sie abläuft.';
$string['flexaccess:config'] = 'FlexAccess-Einschreibeinstanzen konfigurieren';
$string['flexaccess:unenrol'] = 'Nutzer aus einer FlexAccess-Instanz austragen';
$string['gate:domain'] = 'Erlaubte E-Mail-Domains';
$string['gate:inherit'] = 'Systemvorgabe übernehmen';
$string['gate:none'] = 'Kein zusätzliches Gate';
$string['gate:password'] = 'Gemeinsames Passwort';
$string['hide'] = 'Ausblenden';
$string['instance:quickreggatedomains'] = 'Erlaubte E-Mail-Domains';
$string['instance:quickreggatemode'] = 'Gate der Schnellregistrierung';
$string['instance:quickreggatepassword'] = 'Gate-Passwort';
$string['maxparticipants'] = 'Maximale Teilnehmerzahl';
$string['maxparticipants_help'] = 'Maximale Anzahl aktiver FlexAccess-Einschreibungen dieser Instanz. 0 bedeutet unbegrenzt. Abgelaufene Zugänge geben Plätze frei. Keine Warteliste.';
$string['methodneutralised'] = 'Diese Zugangsmethoden sind durch eine System- oder Kursbereichs-Vorgabe abgeschaltet und können hier nicht aktiviert werden, solange „Aufweiten durch untere Ebenen erlauben“ aus ist: {$a}.';
$string['mode:allowguest'] = 'Gast';
$string['mode:allownormallogin'] = 'Login';
$string['mode:allowquick'] = 'Schnellregistrierung';
$string['mode:allowtemporary'] = 'temporär';
$string['mode:label'] = 'Aktive Zugangswege';
$string['mode:none'] = 'kein anonymer Zugang';
$string['participantrole'] = 'FlexAccess-Teilnehmer';
$string['participantrole_desc'] = 'Dedizierte Rolle für temporäre und schnellregistrierte FlexAccess-Besucher. Sie entspricht der Teilnehmerrolle, erlaubt einem Kurs aber, die Teilnehmerliste vor diesen Besuchern zu verbergen.';
$string['participantvisibility'] = 'Teilnehmerlisten-Zugriff für temporäre Besucher';
$string['participantvisibility:inherit'] = 'Systemvorgabe erben';
$string['participantvisibility_help'] = 'Legt fest, ob temporäre und schnellregistrierte Besucher dieses Kurses die Teilnehmerliste öffnen dürfen. „Verweigern“ verhindert den Zugriff auf die Teilnehmerseite; „Erben“ verwendet die Systemvorgabe. Hinweis: temporäre Besucher werden dadurch NICHT aus der für andere sichtbaren Teilnehmerliste ausgeblendet — dafür bietet Moodle keinen stabilen Extension-Point, diese Funktion ist daher nicht enthalten.';
$string['participantvisibilitydefault'] = 'Standard-Teilnehmerlisten-Zugriff für temporäre Besucher';
$string['participantvisibilitydefault_desc'] = 'Systemvorgabe, ob temporäre Besucher die Teilnehmerliste sehen dürfen. Jede FlexAccess-Einschreibeinstanz kann erben, erlauben oder verweigern.';
$string['pluginname'] = 'FlexAccess-Einschreibung';
$string['privacy:metadata'] = 'FlexAccess-Einschreibung speichert nur Policy- und Einschreibekonfiguration; Nutzereinschreibungen liegen in Moodle-Core.';
$string['restrictionrole'] = 'FlexAccess eingeschränkter Besucher';
$string['restrictionrole_desc'] = 'Systemweite Rolle für temporäre FlexAccess-Besucher, die ausschließlich Messaging und Profilbearbeitung entzieht. Sie gewährt nichts und wird bei der Umwandlung in ein vollwertiges Konto entfernt.';
$string['setting:allowguest'] = 'Standard: normalen Gastzugang erlauben';
$string['setting:allowguest_desc'] = 'Systemweite Obergrenze für den Moodle-Gastzugang als FlexAccess-Einstieg. Instanzen können dies nur einschränken.';
$string['setting:allownormallogin'] = 'Standard: normalen Login erlauben';
$string['setting:allownormallogin_desc'] = 'Systemweite Obergrenze dafür, den Standard-Moodle-Login neben den FlexAccess-Methoden verfügbar zu halten.';
$string['setting:allowquick'] = 'Standard: Schnellregistrierung erlauben';
$string['setting:allowquick_desc'] = 'Systemweite Obergrenze für die Schnellregistrierung. Muss hier aktiv sein (oder Aufweiten erlaubt), damit ein Instanz-Häkchen wirkt.';
$string['setting:allowtemporary'] = 'Standard: temporäre Nutzer erlauben';
$string['setting:allowtemporary_desc'] = 'Systemweite Obergrenze für den temporären anonymen Zugang. Muss hier aktiv sein (oder Aufweiten erlaubt), damit ein Instanz-Häkchen wirkt.';
$string['setting:quickreggatedomains'] = 'Erlaubte E-Mail-Domains';
$string['setting:quickreggatedomains_desc'] = 'Eine Domain pro Zeile (oder kommagetrennt), z. B. university.edu. Subdomains werden akzeptiert.';
$string['setting:quickreggatemode'] = 'Gate-Typ';
$string['setting:quickreggatemode_desc'] = 'Schnellregistrierung durch ein gemeinsames Passwort oder erlaubte E-Mail-Domains beschränken.';
$string['setting:quickreggatepassword'] = 'Gemeinsames Passwort';
$string['setting:quickreggatepassword_desc'] = 'Antragsteller müssen dieses Passwort eingeben. Wird nur als Hash gespeichert; leer lassen, um das aktuelle Passwort zu behalten.';
$string['setting:quickregmaxperip'] = 'Schnellregistrierungen pro Adresse';
$string['setting:quickregmaxperip_desc'] = 'Maximale Anzahl an Schnellregistrierungen einer Client-Adresse innerhalb des Zeitfensters.';
$string['setting:quickregwindow'] = 'Zeitfenster der Schnellregistrierung (Sekunden)';
$string['setting:quickregwindow_desc'] = 'Länge des gleitenden Zeitfensters für die Ratenbegrenzung der Schnellregistrierung.';
$string['setting:tempmaxperip'] = 'Temporäre Erzeugungen pro Adresse';
$string['setting:tempmaxperip_desc'] = 'Maximale Anzahl anonymer temporärer Konten, die eine Client-Adresse innerhalb des Zeitfensters erzeugen darf.';
$string['setting:tempsitemax'] = 'Seitenweites Limit temporärer Erzeugung';
$string['setting:tempsitemax_desc'] = 'Maximale Anzahl anonymer temporärer Konten seitenweit innerhalb des Site-Fensters (Circuit-Breaker). 0 = deaktiviert.';
$string['setting:tempsitewindow'] = 'Seitenweites Zeitfenster (Sekunden)';
$string['setting:tempsitewindow_desc'] = 'Länge des gleitenden Zeitfensters für den seitenweiten Circuit-Breaker der temporären Erzeugung.';
$string['setting:tempwindow'] = 'Zeitfenster temporäre Erzeugung (Sekunden)';
$string['setting:tempwindow_desc'] = 'Länge des gleitenden Zeitfensters für die Begrenzung der temporären Kontoerzeugung.';
$string['settings:access'] = 'Zugangszeitraum und Kapazität';
$string['settings:accesskey'] = 'Zugangsschlüssel für temporäre Nutzer';
$string['settings:accesskeygate'] = 'Zugangsschlüssel';
$string['settings:defaults'] = 'Policy-Standards';
$string['settings:lifecycle'] = 'Laufzeiten und Ablauf';
$string['settings:methods'] = 'Zugangsmethoden';
$string['settings:quickreggate'] = 'Zugangs-Gate der Schnellregistrierung';
$string['settings:quickreggate_desc'] = 'Optionale zusätzliche Einschränkung der öffentlichen Schnellregistrierung, zusätzlich zur E-Mail-Freischaltung. Kurseinstellungen überschreiben diese Vorgaben.';
$string['settings:ratelimit'] = 'Ratenbegrenzung';
$string['settings:ratelimit_desc'] = 'Missbrauchsschutz für die öffentliche Schnellregistrierung. Die Vorgaben sind NAT-freundlich, damit eine geteilte Klassenadresse nicht blockiert wird.';
$string['show'] = 'Anzeigen';
$string['status'] = 'FlexAccess-Einschreibung aktivieren';
$string['task:expireenrolments'] = 'FlexAccess-Kurseinschreibungen ablaufen lassen';
$string['temporaryaccesskey'] = 'Systemweiter Zugangsschlüssel';
$string['temporaryaccesskey_desc'] = 'Neuen gemeinsamen Zugangsschlüssel eingeben. Gespeichert wird nur ein sicherer Hash. Leer lassen, um den vorhandenen Schlüssel unverändert zu lassen; die separate Option schaltet die Pflicht ein oder aus.';
$string['temporaryaccesskey_help'] = 'Der gemeinsame Schlüssel, den ein Besucher für temporären Zugang eingeben muss. Leer lassen, um den aktuellen Schlüssel zu behalten.';
$string['temporaryaccesskeymode'] = 'Zugangsschlüssel-Modus';
$string['temporaryaccesskeymode:course'] = 'Kurs-Zugangsschlüssel verwenden';
$string['temporaryaccesskeymode:inherit'] = 'Site-Einstellung erben';
$string['temporaryaccesskeymode_help'] = 'Ob der temporäre Zugang keinen Kursschlüssel nutzt (Site-Einstellung erben) oder einen kursspezifischen Schlüssel.';
$string['temporaryaccesskeyrequired'] = 'Systemweiten Zugangsschlüssel verlangen';
$string['temporaryaccesskeyrequired_desc'] = 'Verlangt für FlexAccess-Zugänge, die einen temporary user erzeugen, systemweit einen Zugangsschlüssel. Kurse dürfen ihn erben oder durch einen eigenen Schlüssel ersetzen.';
$string['temporarylifetime'] = 'Laufzeit des temporären Kontos';
$string['temporarylifetime_help'] = 'Wie lange ein temporäres Konto aktiv bleibt, bevor es abläuft.';
