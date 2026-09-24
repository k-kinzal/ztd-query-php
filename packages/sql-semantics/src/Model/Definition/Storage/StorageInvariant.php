<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Storage;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Validates the names, quantities, and release-specific forms of MySQL storage definitions.
 * @visibility SqlSemantics
 */
final class StorageInvariant
{
    /**
     * Requires MySQL and nonempty names; an absent optional name is null.
     * @throws InvalidStructure
     */
    public static function names(Origin $origin, ?string ...$names): void
    {
        if ($origin->dialect !== Dialect::MySql || in_array('', $names, true)) {
            throw new InvalidStructure('Storage definitions require MySQL and nonempty object, file group, and engine names.');
        }
    }

    /**
     * Requires byte sizes and node group numbers to be unsigned.
     * @throws InvalidStructure
     */
    public static function quantities(?int ...$quantities): void
    {
        foreach ($quantities as $quantity) {
            if ($quantity !== null && $quantity < 0) {
                throw new InvalidStructure('Storage sizes and node groups cannot be negative.');
            }
        }
    }

    /**
     * Requires a nonempty engine name and an engine attribute that is empty or JSON text.
     * @throws InvalidStructure
     */
    public static function engine(?string $engine, ?string $attribute = null): void
    {
        if ($engine === '') {
            throw new InvalidStructure('A storage engine selection requires a nonempty name.');
        }
        if ($attribute !== null && $attribute !== '' && json_decode($attribute) === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidStructure('A tablespace engine attribute must be JSON text.');
        }
    }

    /**
     * Whether the statement was bound against a MySQL 5.x grammar.
     */
    public static function legacy(Origin $origin): bool
    {
        return str_starts_with($origin->context?->schema()->grammarVersion ?? '', 'mysql-5.');
    }

    /**
     * Requires a MySQL 8+ grammar once the binding context is known.
     * @throws InvalidStructure
     */
    public static function modern(Origin $origin, string $form): void
    {
        if (self::legacy($origin)) {
            throw new InvalidStructure($form . ' requires a MySQL 8+ grammar.');
        }
    }

    /**
     * Requires a MySQL 5.x grammar once the binding context is known.
     * @throws InvalidStructure
     */
    public static function older(Origin $origin, string $form): void
    {
        $version = $origin->context?->schema()->grammarVersion;
        if ($version !== null && !self::legacy($origin)) {
            throw new InvalidStructure($form . ' exists only in MySQL 5.x grammars.');
        }
    }

    /**
     * Rejects encryption and engine attributes before MySQL 8, and FILE_BLOCK_SIZE before MySQL 5.7.
     * @throws InvalidStructure
     */
    public static function options(Origin $origin, TablespaceOptions|TablespaceChanges $options): void
    {
        if ($options->encryption !== null || $options->engineAttribute !== null) {
            self::modern($origin, 'Tablespace encryption and engine attributes');
        }
        if ($options instanceof TablespaceOptions && $options->fileBlockSize !== null && str_starts_with($origin->context?->schema()->grammarVersion ?? '', 'mysql-5.6.')) {
            throw new InvalidStructure('FILE_BLOCK_SIZE requires a MySQL 5.7+ grammar.');
        }
    }
}
