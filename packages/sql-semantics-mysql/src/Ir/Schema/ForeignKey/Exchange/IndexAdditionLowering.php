<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyKeyColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyNativeKey;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexPartsLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;

/**
 * Resolves checked whole-field secondary additions within a native table alteration.
 * @visibility SqlSemantics
 * @phpstan-type IndexFrame array{Identifier|null, non-empty-list<ForeignKeyKeyColumn>, IndexRole, TableSource}
 */
final class IndexAdditionLowering
{
    /**
     * Resolves the declaration's operands before the shared naming pass.
     * @return IndexFrame
     */
    public function request(Table $table, TableIdentity $owner, IndexDefinition $index, bool $legacy): array
    {
        Check::input(($index->kind === IndexKind::Index || $index->kind === IndexKind::Unique)
            && ($index->algorithm === null || $index->algorithm === IndexAlgorithm::Btree)
            && $index->options === [] && $index->constraint === null, 'A mixed key addition requires an ordinary or unique B-tree with default index options.');
        [$names, $types, $parts] = (new IndexPartsLowering())->resolve($table, $index->parts, $legacy);
        $heading = new TableSource($owner->name->schema, $owner->name->table, $names, $types, $owner->kind);
        $columns = [];
        foreach ($parts as $part) {
            $columns[] = new ForeignKeyKeyColumn($heading->columns[$part->slot], $part->descending);
        }
        Check::input($columns !== [] && count($columns) <= 16, 'An added secondary index requires one to 16 whole fields.');
        $role = $index->kind === IndexKind::Unique ? IndexRole::Unique : IndexRole::Ordinary;

        return [$index->name === null ? null : new Identifier($index->name->column->value), $columns, $role, $heading];
    }

    /**
     * Publishes the physically allocated key through its checked public constructor.
     * @param IndexFrame $frame
     */
    public function complete(TableIdentity $owner, Identifier $name, array $frame): IndexAddition
    {
        [$request, $columns, $role, $heading] = $frame;
        $key = new ForeignKeyNativeKey(new IndexIdentity($owner, $name, $role), $columns, true);

        return new IndexAddition($key, $heading, $request === null);
    }
}
