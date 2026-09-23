<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection;

use SqlSemantics\Type\Nullability;

/**
 * Plugin identity and loading metadata exposed by SHOW PLUGINS.
 * @visibility public
 * @example Inspecting a result role
 *     \SqlSemantics\Model\Query\Inspection\PluginField::Name->value // => 'Name'
 */
enum PluginField: string
{
    case Name = 'Name';
    case Status = 'Status';
    case Type = 'Type';
    case Library = 'Library';
    case License = 'License';

    /**
     * Returns the NULL fact declared for this metadata field.
     */
    public function nullability(): Nullability
    {
        return match ($this) {
            self::Library, self::License => Nullability::MaybeNull,
            self::Name, self::Status, self::Type => Nullability::NotNull,
        };
    }
}
