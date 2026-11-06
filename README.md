# mod_rollcall — Lista de clase

Actividad de Moodle 5.2 para pasar lista de alumnos. Muestra al profesorado los alumnos del curso diferenciados por color según su categoría (repetidor, Erasmus u otras que defina el centro) y permite clasificarlos. Cada lista de clase registra además sus sesiones (clases concretas).

## Qué hace (v1)

- **Sesiones** (portada de la actividad, `view.php`): cada sesión tiene nombre, fecha y hora, duración y grupo («Toda la actividad» o un grupo). Se listan las más recientes primero, con selector de grupo cuando la actividad usa grupos, y se crean, editan y borran desde la portada.
  - Al crear, el nombre propuesto es «Sesión» con la fecha de hoy en el formato corto del idioma del usuario (p. ej. «Sesión 6/11/26» en el sitio en español), la fecha es la hora actual y la duración 60 minutos. La duración se introduce en minutos (entero entre 1 y 1440).
  - Las sesiones de un grupo que ya no existe se conservan, y la columna «Grupo» muestra «Grupo eliminado». Solo las ven y gestionan quienes tienen `moodle/site:accessallgroups`.
- **Alumnos** (pestaña *Alumnos*, `students.php`): alumnos del curso con matrícula activa y el permiso `mod/rollcall:belisted` (rol *student*), con foto, nombre, color de fila, franja lateral y etiqueta. Leyenda con recuentos, filtro por categoría y selector de grupo. Los enlaces antiguos `view.php?…&filter=…` redirigen aquí con el mismo filtro.
- **Clasificar** (profesor con edición): desplegable en cada fila, que guarda al momento, y acción en bloque sobre los alumnos seleccionados. La clasificación es **por curso**: todas las instancias de un curso la comparten.
- **Catálogo** (*Administración del sitio › Extensiones › Módulos de actividad › Lista de clase › Categorías de alumno*): crear, editar, ordenar, ocultar y borrar categorías. El color se elige de una paleta fija de 8. Una categoría con alumnos no se puede borrar, solo ocultar. Si se oculta, sus alumnos se muestran como regulares, pero la clasificación se conserva.
- **Alumnos**: no ven la actividad ni ninguna categoría.
- **Estados de asistencia** (*Estados* en la navegación de la actividad, con el permiso `mod/rollcall:managestatuses`): cada lista de clase tiene sus propios estados (nombre, orden y visibilidad). Se crean Presente, Retraso y No asiste al añadir la actividad, en el idioma del curso, y se pueden editar, reordenar, ocultar y borrar. El botón *Restaurar estados por defecto* añade los que falten (por nombre) sin tocar los existentes. Los nombres son únicos por actividad, sin distinguir mayúsculas. Los estados viajan en la copia de seguridad y en el duplicado de la actividad, se borran con ella y el reinicio del curso no los toca. La tabla no guarda datos de usuario. Todavía no se asignan a alumnos.
- **Ciclo de vida**:
  - Borrar el curso borra sus clasificaciones.
  - El reinicio del curso borra las clasificaciones (opción marcada por defecto) y las sesiones (opción «Borrar todas las sesiones», también marcada por defecto).
  - Borrar la actividad borra sus sesiones.
  - Las copias de seguridad y de curso **nunca** incluyen las clasificaciones.
  - Las sesiones **siempre** se incluyen en la copia de seguridad y en el duplicado de la actividad, también sin datos de usuario. Al restaurar, el grupo se reasigna con el mapeo de grupos; si el grupo no está incluido en la copia o no tiene correspondencia en el mapeo (por ejemplo, una copia sin grupos), la sesión pasa a «Toda la actividad». Las fechas se desplazan con la fecha de inicio del curso, como el resto de fechas de Moodle.

Fuera de la v1: categorías ligadas a cohortes (el cálculo está centralizado en `\mod_rollcall\local\classification` para añadirlas), asignar un estado de asistencia a cada alumno en cada sesión (las sesiones existen, pero todavía no tienen asistencia), tipos de sesión y series de sesiones, peso de los estados para el porcentaje de faltas, calificaciones, app móvil.

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
| `mod/rollcall:managestatuses` | editingteacher, manager | gestionar los estados de asistencia |
| `mod/rollcall:belisted` | student | aparecer en el listado de alumnos |

## Privacidad y auditoría

La tabla `rollcall_session` no tiene `usermodified` (igual que `rollcall_status`), así que el proveedor de privacidad no cambia. Quién creó, modificó o borró cada sesión queda en el registro de eventos: `session_created`, `session_updated` y `session_deleted`, en contexto de módulo, con el id de la sesión como `objectid` y el grupo en `other['groupid']`.

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
