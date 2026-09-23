<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Storage;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Validates named storage removal requests against their language and operand domains.
 * @visibility SqlSemantics
 */
final class RemovalInvariant
{
    /**
     * Requires a name and, when supplied, a nonempty engine identity.
     * @throws InvalidStructure
     */
    public static function target(Origin $origin, string $name, ?string $engine): void
    {
        if ($origin->dialect !== Dialect::MySql || $name === '' || $engine === '') {
            throw new InvalidStructure('Storage removal requires MySQL and nonempty object and engine names.');
        }
    }

    /**
     * Requires a selected release with explicit undo-tablespace removal.
     * @throws InvalidStructure
     */
    public static function undo(Origin $origin): void
    {
        if (in_array($origin->context?->schema()->grammarVersion, ['mysql-5.6.51', 'mysql-5.7.44'], true)) {
            throw new InvalidStructure('Undo-tablespace removal requires a MySQL 8+ grammar.');
        }
    }
}
