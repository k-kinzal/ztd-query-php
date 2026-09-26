<?php

declare(strict_types=1);

namespace Requirements\Config;

use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;

/**
 * Reads the extension class names a configuration registers by name.
 */
final class ExtensionClasses
{
    /**
     * Reads a mapping of names to class names.
     *
     * @param mixed $value The decoded extensions.sources or extensions.runners mapping
     *
     * @return array<string, string> Class names by extension name
     *
     * @throws InvalidInputException When the value is not a mapping of nonempty strings
     */
    public static function read(mixed $value): array
    {
        $result = [];
        $data = Fields::mapping($value, 'extensions');
        foreach (array_keys($data) as $name) {
            $result[$name] = Fields::text($data, $name);
        }
        return $result;
    }
}
