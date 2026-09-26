<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown\Profile;

use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;

/**
 * Evaluates the pattern, const and enum text constraints of the profile.
 */
final class TextConstraint
{
    /**
     * Tells whether text satisfies every given constraint.
     *
     * @param string $text The text of a heading or paragraph
     * @param array<string, mixed> $schema The constraint
     *
     * @return bool True when the text matches the pattern, equals the const and is in the enum, where given
     *
     * @throws InvalidInputException When the constraint has unknown keys or malformed values
     */
    public static function matches(string $text, array $schema): bool
    {
        Fields::keys($schema, ['pattern', 'const', 'enum'], 'text constraint');
        if (isset($schema['pattern']) && preg_match('~' . str_replace('~', '\\~', Fields::text($schema, 'pattern')) . '~u', $text) !== 1) {
            return false;
        }
        return (!isset($schema['const']) || $text === $schema['const']) && (!isset($schema['enum']) || in_array($text, Fields::strings($schema['enum'], 'enum'), true));
    }
}
