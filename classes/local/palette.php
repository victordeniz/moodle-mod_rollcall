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

namespace mod_rollcall\local;

/**
 * Fixed colour palette for categories.
 *
 * Each strong colour reaches WCAG AA contrast with white badge text. Keep in sync with styles.css.
 *
 * @package    mod_rollcall
 * @copyright  2026 Victor Deniz <fitodeniz@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class palette {
    /** @var array<string, string[]> Colour key => [strong colour, light row background]. */
    public const COLORS = [
        'amber'  => ['#b45309', '#fef3c7'],
        'blue'   => ['#1d4ed8', '#dbeafe'],
        'green'  => ['#15803d', '#dcfce7'],
        'purple' => ['#7e22ce', '#f3e8ff'],
        'red'    => ['#b91c1c', '#fee2e2'],
        'teal'   => ['#0f766e', '#ccfbf1'],
        'pink'   => ['#be185d', '#fce7f3'],
        'slate'  => ['#475569', '#f1f5f9'],
    ];

    /**
     * Returns the palette keys.
     *
     * @return string[]
     */
    public static function keys(): array {
        return array_keys(self::COLORS);
    }

    /**
     * Whether the key is a palette colour.
     *
     * @param string $key
     * @return bool
     */
    public static function is_valid(string $key): bool {
        return array_key_exists($key, self::COLORS);
    }

    /**
     * Returns the palette as options for a select element.
     *
     * @return array<string, string> Colour key => localised colour name.
     */
    public static function options(): array {
        $options = [];
        foreach (self::keys() as $key) {
            $options[$key] = get_string('color_' . $key, 'mod_rollcall');
        }
        return $options;
    }
}
