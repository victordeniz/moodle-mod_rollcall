# mod_rollcall — Lista de clase

Actividad de Moodle 5.2 para pasar lista de alumnos. Muestra al profesorado los alumnos del curso diferenciados por color según su categoría (repetidor, Erasmus u otras que defina el centro) y permite clasificarlos. Cada lista de clase registra además sus sesiones (clases concretas).

Manual de uso para el profesorado, con capturas: [manual/index.html](manual/index.html) (también enlazado desde «Más ayuda» en el selector de actividades).

## Qué hace (v1)

- **Sesiones** (portada de la actividad, `view.php`): cada sesión tiene nombre, fecha y hora, duración y grupo («Toda la actividad» o un grupo). Se listan las más recientes primero, con selector de grupo cuando la actividad usa grupos, y se crean, editan y borran desde la portada.
  - Al crear, el nombre propuesto es «Sesión» con la fecha de hoy en el formato corto del idioma del usuario (p. ej. «Sesión 6/11/26» en el sitio en español), la fecha es la hora actual y la duración 60 minutos. La duración se introduce en minutos (entero entre 1 y 1440).
  - Las sesiones de un grupo que ya no existe se conservan, y la columna «Grupo» muestra «Grupo eliminado». Solo las ven y gestionan quienes tienen `moodle/site:accessallgroups`.
- **Alumnos** (pestaña *Alumnos*, `students.php`): alumnos del curso con matrícula activa y el permiso `mod/rollcall:belisted` (rol *student*), con foto, nombre, color de fila, franja lateral y etiqueta. Leyenda con recuentos, filtro por categoría y selector de grupo. Los enlaces antiguos `view.php?…&filter=…` redirigen aquí con el mismo filtro.
- **Clasificar** (profesor con edición): desplegable en cada fila, que guarda al momento, y acción en bloque sobre los alumnos seleccionados. La clasificación es **por curso**: todas las instancias de un curso la comparten.
- **Catálogo** (*Administración del sitio › Extensiones › Módulos de actividad › Lista de clase › Categorías de alumno*): crear, editar, ordenar, ocultar y borrar categorías. El color se elige de una paleta fija de 8. Una categoría con alumnos no se puede borrar, solo ocultar. Si se oculta, sus alumnos se muestran como regulares, pero la clasificación se conserva.
- **Alumnos**: no ven la actividad ni ninguna categoría.
- **Estados de asistencia** (*Estados* en la navegación de la actividad, con el permiso `mod/rollcall:managestatuses`): cada lista de clase tiene sus propios estados (nombre, peso, orden y visibilidad). El **peso** (entero de 0 a 100, obligatorio) es lo que vale una sesión con ese estado si la sesión completa vale 100. Se crean Presente (100), Retraso (50) y No asiste (0) al añadir la actividad, en el idioma del curso, y se pueden editar, reordenar, ocultar y borrar (un estado que ya está en uso solo se puede ocultar). El botón *Restaurar estados por defecto* añade los que falten (por nombre) sin tocar los existentes. Los nombres son únicos por actividad, sin distinguir mayúsculas. Los estados viajan en la copia de seguridad y en el duplicado de la actividad, se borran con ella y el reinicio del curso no los toca.
- **Pasar lista** (`take.php`): se abre desde el nombre de la sesión en la portada, con el permiso `mod/rollcall:takeattendance`. Lista a los alumnos con un desplegable de estado por alumno (incluye «Sin estado»), que guarda al momento por AJAX. También hay una acción en bloque que aplica un estado a los alumnos marcados con la casilla, filtro por categoría y orden por nombre o apellidos. La lista muestra **todos** los alumnos por defecto, de modo que «Seleccionar todos» y la acción en bloque registran la asistencia de toda la clase (o de la categoría filtrada) de una vez. La paginación es opcional: si hay más alumnos que el tamaño de página, bajo la tabla aparece el enlace «Paginar (N por página)», y al paginar, «Mostrar todos (N)» vuelve a la lista entera; paginando, «Seleccionar todos» marca solo las filas de la página actual. La elección va en la URL (`paged=1`) y no se recuerda entre visitas. El tamaño de página es el ajuste del sitio `mod_rollcall/perpage` (por defecto 25, entre 1 y 500), en *Administración del sitio › Extensiones › Módulos de actividad › Lista de clase*. Si la sesión es de un grupo, se aplica la misma regla de grupo que para gestionar sesiones. En una sesión de grupo, la lista muestra solo los alumnos de ese grupo; si el grupo se ha eliminado, muestra todos los alumnos de la actividad (solo pueden abrir esa sesión quienes tienen acceso a todos los grupos).
- **Informe de asistencia** (*Informe de asistencia* en la navegación de la actividad, `report.php`, con el permiso `mod/rollcall:viewreport`): una fila por alumno con cuántas sesiones pasadas tuvo cada estado, cuántas están sin marcar y su porcentaje de asistencia. Solo lectura, con selector de grupo, filtro por categoría, orden por nombre, apellidos o porcentaje y paginación con el mismo tamaño de página (`mod_rollcall/perpage`); a diferencia de pasar lista, el informe siempre se pagina.
  - Cuentan las sesiones que ya han empezado (fecha de inicio anterior o igual a ahora) de toda la actividad, de los grupos a los que el alumno pertenece ahora y de los grupos eliminados. No se tiene en cuenta la fecha de matriculación.
  - Porcentaje = suma de los pesos de los estados del alumno / número de esas sesiones. Una sesión sin marcar cuenta como 0 y se señala con un aviso. Sin sesiones pasadas se muestra «—».
  - Se calcula al abrir el informe con los pesos actuales: cambiar el peso de un estado cambia todos los porcentajes. Los estados ocultos siguen contando con su peso y aparecen como columna si algún alumno los tiene.
- **Calificación** (opcional; sección *Calificación* de los ajustes de la actividad): tipo «Ninguna» (por defecto) o «Puntuación» con su máximo; no se admiten escalas. Con puntuación, la actividad tiene un elemento en el libro de calificaciones.
  - Nota del alumno = máximo × porcentaje del informe de asistencia / 100, con las mismas reglas que el informe (`\mod_rollcall\local\grades` usa `report::summarise()`). Un alumno sin sesiones pasadas queda sin nota, no con un 0.
  - Las notas **no se actualizan solas**. Las envía al libro quien tenga `mod/rollcall:updategrades`, con el botón «Actualizar calificaciones» de la portada de sesiones, que recalcula a todos los alumnos de la lista de la actividad (sin regla de grupos) y muestra la fecha del último envío (`rollcall.gradessynced`).
  - Excepciones: al cambiar el máximo en los ajustes (eligiendo reescalar) las notas se reenvían sobre el máximo nuevo, y el propio Moodle pide las notas al módulo (`rollcall_update_grades()`) cuando se renombra o duplica la actividad, se fuerza un recálculo desde el libro, se reinicia el libro de calificaciones del curso o se recuperan las notas de un alumno que vuelve a matricularse (solo ese alumno); el resultado es el mismo que el del botón.
  - Si el elemento de calificación, o la nota de algún alumno, está **bloqueado** en el libro, el botón avisa de que no se han podido actualizar las calificaciones y no cambia la fecha del último envío.
  - Los alumnos que ya no están en la lista (p. ej. matrícula suspendida) conservan la nota que tuvieran. Si se les **desmatricula**, es Moodle quien borra sus calificaciones. Una nota anulada a mano en el libro no se pisa. Tampoco se reescala al cambiar el máximo: conserva su valor (un 9 sobre 10 pasa a ser un 9 sobre 20).
  - Si el profesor activa la calificación, cada alumno ve su nota de asistencia en su informe de calificaciones (salvo que el elemento se oculte en el libro). Dentro de la actividad los alumnos siguen sin ver nada.
- **Ciclo de vida**:
  - El reinicio del curso, si borra la asistencia o las sesiones, vacía también las notas de la actividad en el libro y la fecha del último envío. Si además se reinicia el libro de calificaciones (opción de Moodle marcada por defecto), Moodle vuelve a pedir las notas a la actividad: quedan sin nota si se borraron las sesiones, y con un 0 los alumnos que sigan matriculados si se conservaron sesiones pasadas sin asistencia, igual que en el informe.
  - La copia de seguridad incluye el ajuste de calificación. Las notas van con los datos de usuario (las guarda Moodle); sin datos de usuario, la copia queda sin notas enviadas. Una copia anterior a esta versión se restaura sin calificación.
  - Borrar el curso borra sus clasificaciones.
  - El reinicio del curso borra las clasificaciones (opción marcada por defecto), la asistencia registrada (opción «Borrar la asistencia registrada (conservar las sesiones)», marcada por defecto) y las sesiones (opción «Borrar todas las sesiones», también marcada por defecto; al borrar las sesiones se borra también su asistencia).
  - Borrar una sesión avisa de cuántos alumnos tienen asistencia registrada y, al confirmar, la borra junto con la sesión.
  - Borrar la actividad borra sus sesiones y su asistencia.
  - Las copias de seguridad y de curso **nunca** incluyen las clasificaciones.
  - La asistencia solo se incluye en la copia (y en el duplicado) si se incluyen datos de usuario.
  - Las sesiones **siempre** se incluyen en la copia de seguridad y en el duplicado de la actividad, también sin datos de usuario. Al restaurar, el grupo se reasigna con el mapeo de grupos; si el grupo no está incluido en la copia o no tiene correspondencia en el mapeo (por ejemplo, una copia sin grupos), la sesión pasa a «Toda la actividad». Las fechas se desplazan con la fecha de inicio del curso, como el resto de fechas de Moodle.

Fuera de la v1: categorías ligadas a cohortes (el cálculo está centralizado en `\mod_rollcall\local\classification` para añadirlas), tipos de sesión y series de sesiones, detalle por sesiones y exportación del informe, actualización automática de las calificaciones, app móvil.

## Sesiones y grupos

Quién puede crear, editar o borrar una sesión de grupo `g` (`g = 0` es «Toda la actividad»):

- Hace falta `mod/rollcall:managesessions`.
- Si la actividad no usa grupos, basta con esa capacidad.
- Si la actividad usa grupos (separados o visibles):
  - `g = 0`: solo con `moodle/site:accessallgroups`. La ven quienes pueden ver la actividad (`mod/rollcall:view`), profesorado incluido. En grupos separados, un usuario sin `accessallgroups` la ve junto a las sesiones del grupo que ha elegido en el selector, que tiene que ser uno de sus grupos.
  - `g > 0`: con `moodle/site:accessallgroups`, o, sin ese permiso, si el usuario es miembro de `g`. Si la actividad tiene agrupamiento, el grupo tiene que pertenecer a él (esta comprobación solo aplica a quien gestiona por pertenencia al grupo).
  - En grupos visibles, un profesor ve las sesiones de otros grupos, pero no puede gestionarlas.

La regla está en un solo sitio (`\mod_rollcall\local\session_manager::can_manage()`). `can_create()` usa la capacidad y las opciones de grupo para decidir si la portada muestra el botón «Nueva sesión» y si se puede abrir el formulario de alta. La portada usa `can_manage()` para mostrar editar y borrar en cada sesión, y el formulario de edición y la página de borrado (`view.php`) lo comprueban antes de guardar o borrar.

## Permisos

| Capacidad | Por defecto | Para |
|---|---|---|
| `mod/rollcall:addinstance` | editingteacher, manager | añadir la actividad |
| `mod/rollcall:view` | teacher, editingteacher, manager | ver la portada de sesiones y la pestaña Alumnos |
| `mod/rollcall:classify` | editingteacher, manager | clasificar |
| `mod/rollcall:managesessions` | teacher, editingteacher, manager | crear, editar y borrar sesiones (con las reglas de grupo de arriba) |
| `mod/rollcall:takeattendance` | teacher, editingteacher, manager | pasar lista en una sesión (con la misma regla de grupo que `managesessions`; es independiente de ella) |
| `mod/rollcall:managestatuses` | editingteacher, manager | gestionar los estados de asistencia y sus pesos |
| `mod/rollcall:viewreport` | teacher, editingteacher, manager | ver el informe de asistencia |
| `mod/rollcall:updategrades` | teacher, editingteacher, manager | enviar las notas de asistencia al libro de calificaciones (sin regla de grupo) |
| `mod/rollcall:belisted` | student | aparecer en el listado de alumnos |

## Privacidad y auditoría

La asistencia (`rollcall_attendance`) es dato de usuario: el proveedor de privacidad la exporta y la borra por actividad. La tabla no guarda quién marcó a cada alumno; eso queda solo en el registro de eventos (evento `attendance_updated`).

La tabla `rollcall_session` no tiene `usermodified` (igual que `rollcall_status`), así que el proveedor de privacidad no cambia. Quién creó, modificó o borró cada sesión queda en el registro de eventos: `session_created`, `session_updated` y `session_deleted`, en contexto de módulo, con el id de la sesión como `objectid` y el grupo en `other['groupid']`.

Las notas de asistencia las guarda el libro de calificaciones: el proveedor de privacidad declara el enlace con `core_grades`, que es quien las exporta y las borra. Cada nota enviada queda en el registro como `user_graded` (evento del core).

El informe no guarda nada. Abrirlo dispara el evento `report_viewed` en contexto de módulo.

## Requisitos

Moodle 5.2 (`$plugin->requires = 2026042000`), PHP ≥ 8.3, MySQL 8.0+ o cualquier base de datos soportada (solo API `$DB`). No envía correo ni usa LDAP.

## Instalación

El plugin es un repo git propio que vive en `public/mod/rollcall/` dentro del checkout de Moodle, excluido del repo de Moodle con `.git/info/exclude`.

```sh
podman exec -u www-data moodle52-dev_web_1 php admin/cli/upgrade.php --non-interactive
```

Las categorías por defecto (Repetidor, Erasmus) se crean al instalar, con su nombre en el idioma del sitio.

## Desarrollo

Desde la raíz del checkout de Moodle:

```sh
# Tests (tras subir $plugin->version, reinicializar con init.php --disable-composer)
podman exec -u www-data moodle52-dev_web_1 vendor/bin/phpunit --testsuite mod_rollcall_testsuite

# JavaScript: amd/src -> amd/build (en el host, Node lts/jod)
npx grunt amd --root=public/mod/rollcall
```

`styles.css` y `\mod_rollcall\local\palette::COLORS` definen los mismos colores: si cambias uno, cambia el otro.
