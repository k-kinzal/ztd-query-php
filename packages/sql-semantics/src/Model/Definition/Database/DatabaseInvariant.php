<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Database;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Validates database targets, option domains, and release-specific operations.
 * @visibility SqlSemantics
 */
final class DatabaseInvariant
{
    /**
     * Keeps named databases distinct from the session's unresolved current database.
     * @throws InvalidStructure
     */
    public static function target(Origin $origin, string|CurrentDatabase $name): void
    {
        if ($origin->dialect !== Dialect::MySql || $name === '') {
            throw new InvalidStructure('A database operation requires MySQL and a nonempty database identity.');
        }
    }

    /**
     * @param list<DatabaseCharacterSet|DatabaseCollation|DatabaseEncryption|DatabaseReadOnly> $options Ordered default changes
     * @throws InvalidStructure
     */
    public static function options(Origin $origin, array $options, bool $creating): void
    {
        Collections::alternatives($creating ? $options : Collections::nonEmpty($options), $creating ? [DatabaseCharacterSet::class, DatabaseCollation::class, DatabaseEncryption::class] : [DatabaseCharacterSet::class, DatabaseCollation::class, DatabaseEncryption::class, DatabaseReadOnly::class]);
        $version = $origin->context?->schema()->grammarVersion;
        $legacy = in_array($version, ['mysql-5.6.51', 'mysql-5.7.44'], true);
        $readOnly = null;
        foreach ($options as $option) {
            if (($option instanceof DatabaseEncryption || $option instanceof DatabaseReadOnly) && $legacy) {
                throw new InvalidStructure('Database encryption and READ ONLY require a MySQL 8+ grammar.');
            }
            if (($option instanceof DatabaseCharacterSet || $option instanceof DatabaseCollation) && $option->name instanceof ServerCharacterInheritance && $version !== null && !$legacy) {
                throw new InvalidStructure('Server character inheritance requires a legacy MySQL grammar.');
            }
            if ($option instanceof DatabaseReadOnly) {
                if ($readOnly !== null && $readOnly !== $option) {
                    throw new InvalidStructure('Repeated database READ ONLY options must agree.');
                }
                $readOnly = $option;
            }
        }
    }

    /**
     * Requires the legacy directory-name upgrade grammar when a release is supplied.
     * @throws InvalidStructure
     */
    public static function upgrade(Origin $origin, string $name): void
    {
        self::target($origin, $name);
        $version = $origin->context?->schema()->grammarVersion;
        if ($version !== null && !in_array($version, ['mysql-5.6.51', 'mysql-5.7.44'], true)) {
            throw new InvalidStructure('Database directory-name upgrades require a legacy MySQL grammar.');
        }
    }
}
