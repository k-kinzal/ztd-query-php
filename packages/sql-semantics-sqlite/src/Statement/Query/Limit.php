<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * The LIMIT clause: the largest number of rows to return and the number of rows to skip first.
 *
 * SQLite has two spellings for a limit with an offset, `LIMIT count OFFSET
 * offset` and `LIMIT offset, count`; the model keeps which one is written.
 * Source: https://sqlite.org/lang_select.html#the_limit_clause.
 *
 * @visibility public
 * @example Reading a limit written with a comma
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM t LIMIT 5, 10');
 *     [$query->statement->limit->offset->digits, $query->statement->limit->count->digits, $query->statement->limit->comma] // => ['5', '10', true]
 * @example Refusing the comma spelling without an offset
 *     new \SqlSemantics\Platform\Sqlite\Statement\Query\Limit(new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1'), null, true) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Limit implements Node
{
    use Snapshot;

    /**
     * @param Scalar $count The largest number of rows
     * @param Scalar|null $offset The number of rows to skip
     * @param bool $comma Whether the offset is written first, before a comma
     */
    public function __construct(public readonly Scalar $count, public readonly ?Scalar $offset = null, public readonly bool $comma = false)
    {
        Check::input(!$comma || $offset !== null, 'The comma spelling of LIMIT has an offset.');
    }

    /**
     * Writes the clause in the spelling kept.
     */
    public function render(Output $out): void
    {
        $out->keyword('LIMIT');
        if ($this->comma) {
            $out->node($this->offset)->symbol(',')->node($this->count);
        } else {
            $out->node($this->count);
            if ($this->offset !== null) {
                $out->keyword('OFFSET')->node($this->offset);
            }
        }
    }
}
