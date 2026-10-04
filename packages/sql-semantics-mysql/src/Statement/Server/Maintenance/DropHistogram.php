<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Maintenance;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Server\Histograms;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * `DROP HISTOGRAM ON column, …`: a request to remove the histograms of columns.
 *
 * The columns are those of the one table ANALYZE TABLE names; the
 * statement checks them (MYSQL-HISTOGRAM-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/analyze-table.html.
 *
 * @visibility public
 * @example Reading the histogram request
 *     $analyze = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("ANALYZE TABLE t DROP HISTOGRAM ON a");
 *     [$analyze->toString(), $analyze->statement->histogram->columns[0]->value === 'a'] // => ["ANALYZE TABLE t DROP HISTOGRAM ON a", true]
 */
final class DropHistogram implements Histogram
{
    use Snapshot;

    /**
     * @var list<Name> The column names in written order; at least one
     */
    public readonly array $columns;

    /**
     * @param list<Name> $columns The column names in written order; at least one
     * @throws InvalidConstruction When the column list is empty
     */
    public function __construct(array $columns)
    {
        $this->columns = Check::listOf($columns, Name::class, 'A histogram request names at least one column.', 1);
    }

    /**
     * Answers the column names in written order.
     *
     * @return list<Name>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', 'HISTOGRAM', 'ON');
        (new Histograms())->render($out, $this->columns);
    }
}
