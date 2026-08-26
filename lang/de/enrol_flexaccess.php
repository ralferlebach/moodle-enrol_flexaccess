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

$string['accessenter'] = 'Kurs betreten';
$string['accessenterintro'] = 'Dieser Kurs nutzt FlexAccess. Betreten Sie ihn mit Ihrem bestehenden Konto:';
$string['accesskeyinvalid'] = 'Der Zugangsschlüssel ist nicht gültig.';
$string['accesskeymodecourse'] = 'Eigenen Kurs-Zugangsschlüssel verwenden';
$string['accesskeymodeinherit'] = 'Systemeinstellung erben';
$string['accesslists'] = 'Anonyme Zugangslisten';
$string['accessnotopen'] = 'FlexAccess-Zugang ist derzeit nicht verfügbar.';
$string['allowguest'] = 'Normalen Gastzugang erlauben';
$string['allowguest_help'] = 'Bietet den Moodle-Gastzugang als eine der FlexAccess-Zugangsoptionen an.';
$string['allowmagiclogin'] = 'E-Mail-Link-Login erlauben';
$string['allowmagiclogin_help'] = 'Wenn aktiviert, können Besucher statt eines Passworts einen einmaligen Login-Link per E-Mail anfordern. Setzt voraus, dass der E-Mail-Link-Login systemweit in den FlexAccess-Authentifizierungseinstellungen aktiviert ist.';
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
$string['checkcoupling'] = 'FlexAccess-Kopplung Auth/Enrol';
$string['checkcouplingaction'] = 'Einschreibe-Plugins verwalten';
$string['checkcouplingauthonly'] = 'auth_flexaccess ist aktiv, enrol_flexaccess jedoch nicht';
$string['checkcouplingauthonly_detail'] = 'Das Authentifizierungs-Plugin ist aktiv, aber kein Kurs kann einen FlexAccess-Einstieg anbieten, weil das Einschreibe-Plugin deaktiviert ist. Aktivieren Sie enrol_flexaccess oder deaktivieren Sie auth_flexaccess, falls FlexAccess nicht genutzt wird.';
$string['checkcouplingbothoff'] = 'FlexAccess ist nicht aktiviert.';
$string['checkcouplingenrolonly'] = 'enrol_flexaccess ist aktiv, auth_flexaccess jedoch nicht';
$string['checkcouplingenrolonly_detail'] = 'Das Einschreibe-Plugin provisioniert temporäre und schnellregistrierte Konten, die sich über auth_flexaccess anmelden. Ist dieses Authentifizierungs-Plugin deaktiviert, können sich diese Konten nicht anmelden. Aktivieren Sie auth_flexaccess.';
$string['checkcouplingok'] = 'auth_flexaccess und enrol_flexaccess sind beide aktiviert.';
$string['coursefull'] = 'Die maximale Teilnehmerzahl für diese Zugangsart ist erreicht.';
$string['enrolperiod'] = 'Einschreibungsdauer';
$string['enrolperiod_help'] = 'Wie lange eine neue Einschreibung aktiv bleibt, unabhängig von der Lebensdauer des temporären Kontos. Wenn gesetzt, endet die Einschreibung nach diesem Zeitraum und die Ablaufaktion wird angewendet. Bei null bleibt die Einschreibung bestehen, bis das Konto abläuft oder sie manuell entfernt wird.';
$string['errorcapacitylock'] = 'Die Kapazitätssperre konnte nicht gesetzt werden. Bitte erneut versuchen.';
$string['errormaxparticipants'] = 'Die maximale Teilnehmerzahl muss 0 (unbegrenzt) oder eine positive Zahl sein.';
$string['errorwindowrange'] = 'Der Zeitpunkt „Verfügbar bis" muss nach „Verfügbar ab" liegen.';
$string['expiryaction'] = 'Ablaufaktion';
$string['expiryaction_help'] = 'Was mit einer FlexAccess-Einschreibung geschieht, wenn sie abläuft.';
$string['expiryactionsuspend'] = 'Einschreibung aussetzen';
$string['expiryactionunenrol'] = 'Nutzer austragen';
$string['flexaccess:config'] = 'FlexAccess-Einschreibeinstanzen konfigurieren';
$string['flexaccess:unenrol'] = 'Nutzer aus einer FlexAccess-Instanz austragen';
$string['gatedomain'] = 'Erlaubte E-Mail-Domains';
$string['gateinherit'] = 'Systemvorgabe übernehmen';
$string['gatenone'] = 'Kein zusätzliches Gate';
$string['gatepassword'] = 'Gemeinsames Passwort';
$string['hide'] = 'Ausblenden';
$string['instancequickreggatedomains'] = 'Erlaubte E-Mail-Domains';
$string['instancequickreggatemode'] = 'Gate der Schnellregistrierung';
$string['instancequickreggatepassword'] = 'Gate-Passwort';
$string['maxparticipants'] = 'Maximale Teilnehmerzahl';
$string['maxparticipants_help'] = 'Maximale Anzahl aktiver FlexAccess-Einschreibungen dieser Instanz. 0 bedeutet unbegrenzt. Abgelaufene Zugänge geben Plätze frei. Keine Warteliste.';
$string['methodneutralised'] = 'Ihre Auswahl wird hier nicht wirksam: Die folgenden Zugangsmethoden sind auf einer höheren Ebene abgeschaltet (systemweite oder Kursbereichs-Vorgabe) und lassen sich hier nicht einschalten, solange dort „Aufweiten durch untere Ebenen erlauben“ aus ist: {$a}. Zum Freischalten muss eine Administratorin/ein Administrator die Methode – oder „Aufweiten durch untere Ebenen erlauben“ – unter Website-Administration → Plugins → Einschreibung → FlexAccess aktivieren, bzw. in der Kursbereichs-Richtlinie.';
$string['modeallowguest'] = 'Gast';
$string['modeallowmagiclogin'] = 'E-Mail-Link';
$string['modeallownormallogin'] = 'Login';
$string['modeallowquick'] = 'Schnellregistrierung';
$string['modeallowtemporary'] = 'temporär';
$string['modelabel'] = 'Aktive Zugangswege';
$string['modenone'] = 'kein anonymer Zugang';
$string['participantlistaccess'] = 'Teilnehmerlisten-Zugriff für temporäre Besucher';
$string['participantlistaccess_help'] = 'Legt fest, ob temporäre und schnellregistrierte Besucher dieses Kurses die Teilnehmerliste öffnen dürfen. „Verweigern“ verhindert den Zugriff auf die Teilnehmerseite; „Erben“ verwendet die Systemvorgabe. Hinweis: temporäre Besucher werden dadurch NICHT aus der für andere sichtbaren Teilnehmerliste ausgeblendet — dafür bietet Moodle keinen stabilen Extension-Point, diese Funktion ist daher nicht enthalten.';
$string['participantlistaccessdefault'] = 'Standard-Teilnehmerlisten-Zugriff für temporäre Besucher';
$string['participantlistaccessdefault_desc'] = 'Systemvorgabe, ob temporäre Besucher die Teilnehmerliste sehen dürfen. Jede FlexAccess-Einschreibeinstanz kann erben, erlauben oder verweigern.';
$string['participantlistaccessinherit'] = 'Systemvorgabe erben';
$string['participantrole'] = 'FlexAccess-Teilnehmer';
$string['participantrole_desc'] = 'Dedizierte Rolle für temporäre und schnellregistrierte FlexAccess-Besucher. Sie entspricht der Teilnehmerrolle, erlaubt einem Kurs aber, die Teilnehmerliste vor diesen Besuchern zu verbergen.';
$string['pluginname'] = 'FlexAccess-Einschreibung';
$string['privacy:metadata'] = 'FlexAccess-Einschreibung speichert nur Policy- und Einschreibekonfiguration; Nutzereinschreibungen liegen in Moodle-Core.';
$string['restrictionrole'] = 'FlexAccess eingeschränkter Besucher';
$string['restrictionrole_desc'] = 'Systemweite Rolle für temporäre FlexAccess-Besucher, die ausschließlich Messaging und Profilbearbeitung entzieht. Sie gewährt nichts und wird bei der Umwandlung in ein vollwertiges Konto entfernt.';
$string['restrictionsadd'] = 'Beschränkung hinzufügen';
$string['restrictionsadded'] = 'Beschränkung hinzugefügt.';
$string['restrictionscohorthint'] = 'Cohort (wird verwendet, wenn der Typ „Cohort" ist)';
$string['restrictionsdeleted'] = 'Beschränkung gelöscht.';
$string['restrictionseffect'] = 'Wirkung';
$string['restrictionseffectallow'] = 'Erlauben';
$string['restrictionseffectdeny'] = 'Verbieten';
$string['restrictionsintro'] = 'Legen Sie fest, wer FlexAccess in diesem Kurs nutzen darf. Ohne Regel dürfen alle. Eine Verbotsregel hat immer Vorrang; sobald mindestens eine Erlaubnisregel existiert, dürfen nur noch Nutzer/innen mit passender Erlaubnisregel. Regeln auf Site- oder Kursbereichsebene gelten hier ebenfalls.';
$string['restrictionsinvalid'] = 'Die Beschränkung konnte nicht hinzugefügt werden: Bitte eine gültige Rolle oder ein gültiges Cohort wählen.';
$string['restrictionskind'] = 'Typ';
$string['restrictionskindcohort'] = 'Cohort';
$string['restrictionskindrole'] = 'Rolle';
$string['restrictionsmanage'] = 'Rollen-/Cohort-Beschränkungen verwalten';
$string['restrictionsmissingref'] = '(gelöscht)';
$string['restrictionsnone'] = 'Für diesen Kurs sind keine Beschränkungen definiert; FlexAccess steht damit allen offen (sofern keine Site- oder Kursbereichsregel greift).';
$string['restrictionsreference'] = 'Rolle oder Cohort';
$string['restrictionsrole'] = 'Rolle';
$string['restrictionstitle'] = 'FlexAccess-Beschränkungen nach Rolle und Cohort';
$string['settingallowguest'] = 'Standard: normalen Gastzugang erlauben';
$string['settingallowguest_desc'] = 'Systemweite Obergrenze für den Moodle-Gastzugang als FlexAccess-Einstieg. Instanzen können dies nur einschränken.';
$string['settingallowmagiclogin'] = 'Standard: E-Mail-Link-Login erlauben';
$string['settingallowmagiclogin_desc'] = 'Systemweite Obergrenze für den E-Mail-Link-Login (Magic). Instanzen können dies nur einschränken.';
$string['settingallownormallogin'] = 'Standard: normalen Login erlauben';
$string['settingallownormallogin_desc'] = 'Systemweite Obergrenze dafür, den Standard-Moodle-Login neben den FlexAccess-Methoden verfügbar zu halten.';
$string['settingallowquick'] = 'Standard: Schnellregistrierung erlauben';
$string['settingallowquick_desc'] = 'Systemweite Obergrenze für die Schnellregistrierung. Muss hier aktiv sein (oder Aufweiten erlaubt), damit ein Instanz-Häkchen wirkt.';
$string['settingallowtemporary'] = 'Standard: temporäre Nutzer erlauben';
$string['settingallowtemporary_desc'] = 'Systemweite Obergrenze für den temporären anonymen Zugang. Muss hier aktiv sein (oder Aufweiten erlaubt), damit ein Instanz-Häkchen wirkt.';
$string['settingquickreggatedomains'] = 'Erlaubte E-Mail-Domains';
$string['settingquickreggatedomains_desc'] = 'Eine Domain pro Zeile (oder kommagetrennt), z. B. university.edu. Subdomains werden akzeptiert.';
$string['settingquickreggatemode'] = 'Gate-Typ';
$string['settingquickreggatemode_desc'] = 'Schnellregistrierung durch ein gemeinsames Passwort oder erlaubte E-Mail-Domains beschränken.';
$string['settingquickreggatepassword'] = 'Gemeinsames Passwort';
$string['settingquickreggatepassword_desc'] = 'Antragsteller müssen dieses Passwort eingeben. Wird nur als Hash gespeichert; leer lassen, um das aktuelle Passwort zu behalten.';
$string['settingquickregmaxperip'] = 'Schnellregistrierungen pro Adresse';
$string['settingquickregmaxperip_desc'] = 'Maximale Anzahl an Schnellregistrierungen einer Client-Adresse innerhalb des Zeitfensters.';
$string['settingquickregwindow'] = 'Zeitfenster der Schnellregistrierung (Sekunden)';
$string['settingquickregwindow_desc'] = 'Länge des gleitenden Zeitfensters für die Ratenbegrenzung der Schnellregistrierung.';
$string['settingsaccess'] = 'Zugangszeitraum und Kapazität';
$string['settingsaccesskey'] = 'Zugangsschlüssel für temporäre Nutzer';
$string['settingsaccesskeygate'] = 'Zugangsschlüssel';
$string['settingsdefaults'] = 'Policy-Standards';
$string['settingslifecycle'] = 'Laufzeiten und Ablauf';
$string['settingsmethods'] = 'Zugangsmethoden';
$string['settingsquickreggate'] = 'Zugangs-Gate der Schnellregistrierung';
$string['settingsquickreggate_desc'] = 'Optionale zusätzliche Einschränkung der öffentlichen Schnellregistrierung, zusätzlich zur E-Mail-Freischaltung. Kurseinstellungen überschreiben diese Vorgaben.';
$string['settingsratelimit'] = 'Ratenbegrenzung';
$string['settingsratelimit_desc'] = 'Missbrauchsschutz für die öffentliche Schnellregistrierung. Die Vorgaben sind NAT-freundlich, damit eine geteilte Klassenadresse nicht blockiert wird.';
$string['settingtempmaxperip'] = 'Temporäre Erzeugungen pro Adresse';
$string['settingtempmaxperip_desc'] = 'Maximale Anzahl anonymer temporärer Konten, die eine Client-Adresse innerhalb des Zeitfensters erzeugen darf.';
$string['settingtempsitemax'] = 'Seitenweites Limit temporärer Erzeugung';
$string['settingtempsitemax_desc'] = 'Maximale Anzahl anonymer temporärer Konten seitenweit innerhalb des Site-Fensters (Circuit-Breaker). 0 = deaktiviert.';
$string['settingtempsitewindow'] = 'Seitenweites Zeitfenster (Sekunden)';
$string['settingtempsitewindow_desc'] = 'Länge des gleitenden Zeitfensters für den seitenweiten Circuit-Breaker der temporären Erzeugung.';
$string['settingtempwindow'] = 'Zeitfenster temporäre Erzeugung (Sekunden)';
$string['settingtempwindow_desc'] = 'Länge des gleitenden Zeitfensters für die Begrenzung der temporären Kontoerzeugung.';
$string['show'] = 'Anzeigen';
$string['status'] = 'FlexAccess-Einschreibung aktivieren';
$string['taskexpireenrolments'] = 'FlexAccess-Kurseinschreibungen ablaufen lassen';
$string['temporaryaccesskey'] = 'Systemweiter Zugangsschlüssel';
$string['temporaryaccesskey_desc'] = 'Neuen gemeinsamen Zugangsschlüssel eingeben. Gespeichert wird nur ein sicherer Hash. Leer lassen, um den vorhandenen Schlüssel unverändert zu lassen; die separate Option schaltet die Pflicht ein oder aus.';
$string['temporaryaccesskey_help'] = 'Der gemeinsame Schlüssel, den ein Besucher für temporären Zugang eingeben muss. Leer lassen, um den aktuellen Schlüssel zu behalten.';
$string['temporaryaccesskeymode'] = 'Zugangsschlüssel-Modus';
$string['temporaryaccesskeymode_help'] = 'Ob der temporäre Zugang keinen Kursschlüssel nutzt (Site-Einstellung erben) oder einen kursspezifischen Schlüssel.';
$string['temporaryaccesskeymodecourse'] = 'Kurs-Zugangsschlüssel verwenden';
$string['temporaryaccesskeymodeinherit'] = 'Site-Einstellung erben';
$string['temporaryaccesskeyrequired'] = 'Systemweiten Zugangsschlüssel verlangen';
$string['temporaryaccesskeyrequired_desc'] = 'Verlangt für FlexAccess-Zugänge, die einen temporary user erzeugen, systemweit einen Zugangsschlüssel. Kurse dürfen ihn erben oder durch einen eigenen Schlüssel ersetzen.';
$string['temporarylifetime'] = 'Laufzeit des temporären Kontos';
$string['temporarylifetime_help'] = 'Wie lange ein temporäres Konto aktiv bleibt, bevor es abläuft.';
