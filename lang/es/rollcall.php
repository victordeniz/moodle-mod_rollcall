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
 * Spanish strings for mod_rollcall.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addcategory'] = 'Añadir categoría';
$string['addsession'] = 'Nueva sesión';
$string['addstatus'] = 'Añadir estado';
$string['apply'] = 'Aplicar';
$string['category'] = 'Categoría';
$string['categorydeleted'] = 'Categoría borrada.';
$string['categoryfor'] = 'Categoría de {$a}';
$string['categoryinuse'] = 'Esta categoría está asignada a alumnos; ocúltala en lugar de borrarla.';
$string['choosegroup'] = 'Elige un grupo…';
$string['color'] = 'Color';
$string['color_amber'] = 'Ámbar';
$string['color_blue'] = 'Azul';
$string['color_green'] = 'Verde';
$string['color_pink'] = 'Rosa';
$string['color_purple'] = 'Violeta';
$string['color_red'] = 'Rojo';
$string['color_slate'] = 'Gris pizarra';
$string['color_teal'] = 'Verde azulado';
$string['confirmdeletecategory'] = '¿Borrar la categoría «{$a}»?';
$string['confirmdeletesession'] = '¿Borrar la sesión "{$a}"?';
$string['confirmdeletestatus'] = '¿Borrar el estado "{$a}"?';
$string['confirmrestorestatuses'] = '¿Añadir los estados por defecto que falten? Los estados existentes no se modifican.';
$string['defaultcat_erasmus'] = 'Erasmus';
$string['defaultcat_repeater'] = 'Repetidor';
$string['defaultsessionname'] = 'Sesión {$a}';
$string['defaultsrestored'] = 'Estados por defecto añadidos: {$a}.';
$string['defaultstatus_absent'] = 'No asiste';
$string['defaultstatus_late'] = 'Retraso';
$string['defaultstatus_present'] = 'Presente';
$string['deletedgroup'] = 'Grupo eliminado';
$string['duration'] = 'Duración';
$string['durationminutes'] = 'Duración (minutos)';
$string['editcategory'] = 'Editar categoría';
$string['editsession'] = 'Editar sesión';
$string['editstatus'] = 'Editar estado';
$string['eventcategorycreated'] = 'Categoría de alumno creada';
$string['eventcategorydeleted'] = 'Categoría de alumno borrada';
$string['eventcategoryupdated'] = 'Categoría de alumno modificada';
$string['eventcoursemoduleviewed'] = 'Lista consultada';
$string['eventsessioncreated'] = 'Sesión creada';
$string['eventsessiondeleted'] = 'Sesión eliminada';
$string['eventsessionupdated'] = 'Sesión actualizada';
$string['eventstatuscreated'] = 'Estado de asistencia creado';
$string['eventstatusdeleted'] = 'Estado de asistencia eliminado';
$string['eventstatusupdated'] = 'Estado de asistencia actualizado';
$string['eventusercategoryupdated'] = 'Categoría de alumno cambiada';
$string['filter'] = 'Mostrar';
$string['filterall'] = 'Todos los alumnos';
$string['invalidcategory'] = 'Categoría no válida.';
$string['invalidcolor'] = 'El color no está en la paleta.';
$string['invalidduration'] = 'La duración debe estar entre 1 y 1440 minutos.';
$string['invalidsession'] = 'Sesión no válida.';
$string['invalidstatus'] = 'Estado de asistencia no válido.';
$string['legend'] = 'Alumnos por categoría';
$string['managecategories'] = 'Categorías de alumno';
$string['modulename'] = 'Lista de clase';
$string['modulename_help'] = 'La actividad Lista de clase muestra los alumnos del curso y permite al profesorado clasificarlos en categorías (por ejemplo, repetidor o Erasmus), cada una con su propio color. También registra sesiones (clases concretas) con su fecha, duración y grupo.';
$string['modulenameplural'] = 'Listas de clase';
$string['nocategories'] = 'Todavía no hay categorías.';
$string['noselection'] = 'Selecciona antes al menos un alumno.';
$string['nosessions'] = 'Todavía no hay sesiones.';
$string['nostatuses'] = 'Todavía no hay estados.';
$string['nostudents'] = 'No hay alumnos que mostrar.';
$string['notingroup'] = 'No perteneces a ningún grupo, así que no hay alumnos que mostrar.';
$string['notingroupsessions'] = 'No pertenece a ningún grupo, así que no hay sesiones que mostrar.';
$string['order'] = 'Orden';
$string['pluginadministration'] = 'Administración de Lista de clase';
$string['pluginname'] = 'Lista de clase';
$string['privacy:metadata:rollcall_category'] = 'Catálogo de categorías de alumno de todo el sitio.';
$string['privacy:metadata:rollcall_category:timemodified'] = 'Cuándo se modificó la categoría por última vez.';
$string['privacy:metadata:rollcall_category:usermodified'] = 'El usuario que modificó la categoría por última vez.';
$string['privacy:metadata:rollcall_usercat'] = 'La categoría de un alumno en un curso.';
$string['privacy:metadata:rollcall_usercat:categoryid'] = 'La categoría asignada al alumno.';
$string['privacy:metadata:rollcall_usercat:timemodified'] = 'Cuándo se modificó la clasificación por última vez.';
$string['privacy:metadata:rollcall_usercat:userid'] = 'El alumno clasificado.';
$string['privacy:metadata:rollcall_usercat:usermodified'] = 'El usuario que clasificó al alumno.';
$string['privacy:path'] = 'Clasificación de alumnos';
$string['regular'] = 'Regular';
$string['resetclassifications'] = 'Borrar las clasificaciones de alumnos';
$string['resetsessions'] = 'Borrar todas las sesiones';
$string['restoredefaultstatuses'] = 'Restaurar estados por defecto';
$string['rollcall:addinstance'] = 'Añadir una nueva Lista de clase';
$string['rollcall:belisted'] = 'Aparecer como alumno en la lista';
$string['rollcall:classify'] = 'Clasificar alumnos';
$string['rollcall:managesessions'] = 'Gestionar las sesiones';
$string['rollcall:managestatuses'] = 'Gestionar los estados de asistencia';
$string['rollcall:view'] = 'Ver la lista';
$string['saved'] = 'Guardado';
$string['selectall'] = 'Seleccionar a todos los alumnos';
$string['selectuser'] = 'Seleccionar a {$a}';
$string['sessioncreated'] = 'Sesión creada.';
$string['sessiondate'] = 'Fecha y hora';
$string['sessiondeleted'] = 'Sesión borrada.';
$string['sessions'] = 'Sesiones';
$string['sessionupdated'] = 'Sesión actualizada.';
$string['statusdeleted'] = 'Estado borrado.';
$string['statuses'] = 'Estados';
$string['statusinuse'] = 'Este estado se ha usado para registrar asistencia; ocúltalo en lugar de borrarlo.';
$string['statusnameexists'] = 'Ya existe un estado con este nombre en esta lista.';
$string['student'] = 'Alumno';
$string['students'] = 'Alumnos';
$string['studentsclassified'] = 'Alumnos clasificados';
$string['usernotlisted'] = 'El usuario no es un alumno de esta lista.';
$string['visible'] = 'Visible';
$string['wholeactivity'] = 'Toda la actividad';
$string['withselected'] = 'Con los alumnos seleccionados:';
