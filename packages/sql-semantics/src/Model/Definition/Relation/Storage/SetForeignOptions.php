<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Storage;

use SqlSemantics\Model\Definition\Foreign\AddForeignOption;
use SqlSemantics\Model\Definition\Foreign\DropForeignOption;
use SqlSemantics\Model\Definition\Foreign\SetForeignOption;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Adds, replaces, or removes foreign-data wrapper options of a foreign table.
 * @visibility public
 * @example Reading ordered option changes
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE ft(c TEXT)')))->bind("ALTER FOREIGN TABLE ft OPTIONS (ADD delimiter ',', SET header 'true', DROP quote)");
 *     $statement->actions[0]->changes[2] instanceof \SqlSemantics\Model\Definition\Foreign\DropForeignOption // => true
 */
final class SetForeignOptions implements RelationAction
{
    /**
     * @param non-empty-list<AddForeignOption|SetForeignOption|DropForeignOption> $changes
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $changes)
    {
        Collections::alternatives(Collections::nonEmpty($changes), [AddForeignOption::class, SetForeignOption::class, DropForeignOption::class]);
    }
}
