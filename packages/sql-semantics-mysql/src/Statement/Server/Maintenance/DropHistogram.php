<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Maintenance;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\Server\Histograms;

/**
 * `DROP HISTOGRAM ON column, …`: a request to remove the histograms of columns.
 *
 * The columns are those of the one table ANALYZE TABLE names; the
 * statement checks them (MYSQL-HISTOGRAM-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/analyze-table.html.
 *
 * @visibility SqlSemantics
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
