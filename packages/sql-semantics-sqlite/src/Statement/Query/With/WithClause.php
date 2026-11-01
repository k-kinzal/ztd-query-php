<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\With;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * A WITH clause: the common table expressions of one statement.
 *
 * Source: https://sqlite.org/lang_with.html.
 *
 * @visibility public
 * @example Reading a WITH clause
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('WITH RECURSIVE c(x) AS (SELECT 1 UNION ALL SELECT x + 1 FROM c) SELECT x FROM c');
 *     [$query->with->recursive, count($query->with->tables)] // => [true, 1]
 */
final class WithClause implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<CommonTable> The common table expressions in written order
     */
    public readonly array $tables;

    /**
     * @param list<CommonTable> $tables The common table expressions in written order; at least one
     * @param bool $recursive Whether RECURSIVE is written
     */
    public function __construct(array $tables, public readonly bool $recursive = false, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        $this->tables = Check::listOf($tables, CommonTable::class, 'A WITH clause has at least one common table expression.', 1);
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('WITH');
        if ($this->recursive) {
            $out->keyword('RECURSIVE');
        }
        $out->list($this->tables);
    }
}
