<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

use Override;

/**
 * A supplied value, with optional insertion default and update expression.
 *
 * @visibility public
  * @example Inspecting SuppliedColumn
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('create table t (id integer generated always as identity, value text constraint required not null, optional text collate "C")');
 *     $schema->tables[0]->columns[2]->generation instanceof \SqlSemantics\Schema\Column\SuppliedColumn // => true
 */
final class SuppliedColumn implements Generation
{
    /**
     * Constructs a valid declaration.

     */
    public function __construct(
        public readonly ?\SqlSemantics\Model\Expression $default = null,
        public readonly ?\SqlSemantics\Model\Expression $onUpdate = null,
    ) {
    }

    /**
     * Returns the optional declared DEFAULT expression for an ordinary stored column.
     */
    #[Override]
    public function expressions(): array
    {
        return array_values(array_filter([$this->default, $this->onUpdate], static fn (?\SqlSemantics\Model\Expression $value): bool => $value !== null));
    }
}
