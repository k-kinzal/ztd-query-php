<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Partition;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;

/**
 * One item of a hash partition bound: MODULUS or REMAINDER and its number.
 *
 * The grammar reads any word; the server accepts `modulus` and `remainder`
 * once each and reports any other word.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility SqlSemantics
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
        $out->name($this->name, \SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse::Qualifier)->node($this->value);
    }
}
