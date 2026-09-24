<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Identity;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Column\IdentityMode;

/**
 * Changes the generation mode or the sequence of an existing identity column.
 * @visibility public
 * @example Reading ordered identity changes
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id SET GENERATED ALWAYS RESTART SET NO CYCLE');
 *     $statement->actions[0]->changes[0] // => \SqlSemantics\Schema\Column\IdentityMode::Always
 *     $statement->actions[0]->changes[1]->value // => null
 *     $statement->actions[0]->changes[2] // => \SqlSemantics\Model\Definition\Relation\Identity\SequenceFlag::NoCycle
 * @example Rejecting an empty change list
 *     new \SqlSemantics\Model\Definition\Relation\Identity\SetColumnIdentity('id', []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class SetColumnIdentity implements RelationAction
{
    /**
     * @param non-empty-list<IdentityMode|RestartIdentity|SequenceValueChange|SequenceFlag|SetSequenceName> $changes
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column, public readonly array $changes)
    {
        CatalogInvariant::identifier($column);
        Collections::alternatives(Collections::nonEmpty($changes), [IdentityMode::class, RestartIdentity::class, SequenceValueChange::class, SequenceFlag::class, SetSequenceName::class]);
    }
}
