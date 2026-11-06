# mod_rollcall — Lista de clase

Actividad de Moodle 5.2 para pasar lista de alumnos. Muestra al profesorado los alumnos del curso diferenciados por color según su categoría (repetidor, Erasmus u otras que defina el centro) y permite clasificarlos.

## Qué hace (v1)

- **Listado** (`view.php`): alumnos del curso con matrícula activa y el permiso `mod/rollcall:belisted` (rol *student*), con foto, nombre, color de fila, franja lateral y etiqueta. Leyenda con recuentos, filtro por categoría y selector de grupo.
- **Clasificar** (profesor con edición): desplegable en cada fila, que guarda al momento, y acción en bloque sobre los alumnos seleccionados. La clasificación es **por curso**: todas las instancias de un curso la comparten.
- **Catálogo** (*Administración del sitio › Extensiones › Módulos de actividad › Lista de clase › Categorías de alumno*): crear, editar, ordenar, ocultar y borrar categorías. El color se elige de una paleta fija de 8. Una categoría con alumnos no se puede borrar, solo ocultar. Si se oculta, sus alumnos se muestran como regulares, pero la clasificación se conserva.
- **Alumnos**: no ven la actividad ni ninguna categoría.
- **Estados de asistencia** (*Estados* en la navegación de la actividad, con el permiso `mod/rollcall:managestatuses`): cada lista de clase tiene sus propios estados (nombre, orden y visibilidad). Se crean Presente, Retraso y No asiste al añadir la actividad, en el idioma del curso, y se pueden editar, reordenar, ocultar y borrar. El botón *Restaurar estados por defecto* añade los que falten (por nombre) sin tocar los existentes. Los nombres son únicos por actividad, sin distinguir mayúsculas. Los estados viajan en la copia de seguridad y en el duplicado de la actividad, se borran con ella y el reinicio del curso no los toca. La tabla no guarda datos de usuario. Todavía no se asignan a alumnos.
- **Ciclo de vida**:
  - Borrar el curso borra sus clasificaciones.
  - El reinicio del curso las borra (opción marcada por defecto).
  - Las copias de seguridad y de curso **nunca** las incluyen.

Fuera de la v1: categorías ligadas a cohortes (el cálculo está centralizado en `\mod_rollcall\local\classification` para añadirlas), sesiones de asistencia (donde se asignará un estado a cada alumno), peso de los estados para el porcentaje de faltas, calificaciones, app móvil.

## Permisos

| Capacidad | Por defecto | Para |
|---|---|---|
| `mod/rollcall:addinstance` | editingteacher, manager | añadir la actividad |
| `mod/rollcall:view` | teacher, editingteacher, manager | ver el listado |
| `mod/rollcall:classify` | editingteacher, manager | clasificar |
| `mod/rollcall:managestatuses` | editingteacher, manager | gestionar los estados de asistencia |
| `mod/rollcall:belisted` | student | aparecer en el listado |

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
