<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Partition;

use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One item of a hash partition bound: MODULUS or REMAINDER and its number.
 *
 * The grammar reads any word; the server accepts `modulus` and `remainder`
 * once each and reports any other word.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Reading a hash bound item
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE p1 PARTITION OF p FOR VALUES WITH (MODULUS 4, REMAINDER 1)');
 *     [$create->statement->definition->bound->items[1]->name->value, $create->statement->definition->bound->items[1]->value->digits] // => ['remainder', '1']
 */
final class HashModulus implements Node
{
    use Snapshot;

    /**
     * @param Name $name The word: modulus or remainder
     * @param IntegerConstant $value The number
     */
    public function __construct(public readonly Name $name, public readonly IntegerConstant $value)
    {
    }

    /**
     * Writes the word and the number.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, \SqlSemantics\Contract\NameUse::Qualifier)->node($this->value);
    }
}
