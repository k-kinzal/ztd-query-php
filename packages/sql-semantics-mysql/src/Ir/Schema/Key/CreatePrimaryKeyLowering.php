<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Key;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;

/**
 * Resolves primary key references against the owning column order.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CreatePrimaryKeyLowering
{
    /**
     * Binds the one complete key and its release-specific directions.
     *
     * @param array<int|string, int> $positions Column names folded with the table's comparison
     */
    public function lower(?IndexDefinition $index, ?int $inline, array $positions, GrammarRelease $release): ?PrimaryKey
    {
        Check::input($index === null || $inline === null, 'CREATE TABLE requires at most one primary key.');
        if ($index === null) {
            return $inline === null ? null : new PrimaryKey([new PrimaryKeyColumn($inline)]);
        }
        Check::input(
            $index->kind === IndexKind::Primary && $index->options === [] && $index->algorithm === null,
            'CREATE TABLE requires an ordinary primary key without index options.'
        );
        $legacy = $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;
        $terms = [];
        foreach ($index->parts as $part) {
            Check::input($part instanceof ColumnPart && $part->length === null, 'A primary key requires full named column parts.');
            $key = new Identifier($part->column->value);
            $slot = $positions[$key->key()] ?? null;
            Check::input($slot !== null, 'A primary key column must exist in the owning table.');
            $terms[] = new PrimaryKeyColumn($slot, !$legacy && $part->direction === Direction::Descending);
        }

        return new PrimaryKey($terms);
    }
}
