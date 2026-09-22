<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

/**
 * A table name as the report reads it: the schema it is qualified with, and how much of it is known.
 *
 * A statement whose table was assembled from a value the analyzer could not
 * follow names its table with a gap in it. The report still groups by that
 * name — every statement on `{$}posts` belongs together — but it says which
 * part of the name is known and which is not, and it gives a table that is
 * nothing but a gap a label a reader can understand.
 *
 * @visibility root
 */
final class TableName
{
    /**
     * The marker a gap in a name is written as.
     */
    public const GAP = '{$}';

    /**
     * Reads a table name as the catalog wrote it.
     */
    public function __construct(public readonly string $name)
    {
    }

    /**
     * The schema the name is qualified with, or null when it is not.
     *
     * A qualifier that is itself a gap is no schema a reader can browse to,
     * so it is read as no qualifier at all.
     */
    public function schema(): ?string
    {
        $dot = strpos($this->name, '.');
        if ($dot === false) {
            return null;
        }
        $schema = substr($this->name, 0, $dot);

        return $schema === '' || str_contains($schema, self::GAP) ? null : $schema;
    }

    /**
     * The name without its schema.
     */
    public function local(): string
    {
        $dot = strrpos($this->name, '.');

        return $dot === false ? $this->name : substr($this->name, $dot + 1);
    }

    /**
     * Whether any part of the name is a value the analyzer could not pin down.
     */
    public function hasGap(): bool
    {
        return str_contains($this->name, self::GAP);
    }

    /**
     * Whether nothing of the name is known.
     */
    public function isUnknown(): bool
    {
        return $this->name === self::GAP;
    }

    /**
     * The name written for a reader.
     */
    public function label(): string
    {
        return $this->isUnknown() ? 'table not pinned down' : $this->name;
    }
}
