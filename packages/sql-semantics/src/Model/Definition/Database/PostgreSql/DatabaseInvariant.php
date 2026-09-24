<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Database\PostgreSql;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Validates PostgreSQL database targets and requested property sets.
 * @visibility SqlSemantics
 */
final class DatabaseInvariant
{
    /**
     * Requires PostgreSQL and a nonempty database or tablespace name.
     * @throws InvalidStructure
     */
    public static function target(Origin $origin, string $name): void
    {
        if ($origin->dialect !== Dialect::PostgreSql || $name === '') {
            throw new InvalidStructure('This database operation requires PostgreSQL and a nonempty name.');
        }
    }

    /**
     * Requires unique properties, only alterable ones when altering, and no LOCALE together with LC_COLLATE or LC_CTYPE.
     * @param list<DatabaseOption> $options Requested properties in request order
     * @throws InvalidStructure
     */
    public static function options(array $options, bool $creating): void
    {
        Collections::objects($options, DatabaseOption::class);
        $seen = [];
        foreach ($options as $option) {
            if (isset($seen[$option->parameter->value]) || (!$creating && !$option->parameter->alterable())) {
                throw new InvalidStructure('A database property must be applicable to the operation and requested once.');
            }
            $seen[$option->parameter->value] = true;
        }
        if (isset($seen['LOCALE']) && (isset($seen['LC_COLLATE']) || isset($seen['LC_CTYPE']))) {
            throw new InvalidStructure('LOCALE cannot be requested together with LC_COLLATE or LC_CTYPE.');
        }
    }
}
