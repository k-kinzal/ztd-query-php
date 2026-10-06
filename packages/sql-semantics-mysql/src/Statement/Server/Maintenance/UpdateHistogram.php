<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Maintenance;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Server\Histograms;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * `UPDATE HISTOGRAM ON column, … [WITH n BUCKETS] [MANUAL UPDATE | AUTO UPDATE]`: a request to build histograms from the column values.
 *
 * The columns are those of the one table ANALYZE TABLE names; the
 * statement checks them (MYSQL-HISTOGRAM-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/analyze-table.html.
 *
 * @visibility public
 * @example Reading the histogram request
 *     $analyze = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("ANALYZE TABLE t UPDATE HISTOGRAM ON a WITH 8 BUCKETS");
 *     [$analyze->toString(), $analyze->statement->histogram->buckets?->text === '8'] // => ["ANALYZE TABLE t UPDATE HISTOGRAM ON a WITH 8 BUCKETS", true]
 */
final class UpdateHistogram implements Histogram
{
    use Snapshot;

    /**
     * @var list<Name> The column names in written order; at least one
     */
    public readonly array $columns;

    /**
     * @param list<Name> $columns The column names in written order; at least one
     * @param Numeral|null $buckets The number of buckets, when written; the server allows 1 to 1024 and uses 100 by default
     * @param HistogramUpdate|null $update MANUAL UPDATE or AUTO UPDATE, when written (MySQL 8.4 and later)
     * @throws InvalidConstruction When the column list is empty or the bucket count is not a decimal number
     */
    public function __construct(array $columns, public readonly ?Numeral $buckets = null, public readonly ?HistogramUpdate $update = null)
    {
        $this->columns = Check::listOf($columns, Name::class, 'A histogram request names at least one column.', 1);
        Check::input($buckets === null || preg_match('/\A[0-9]+\z/', $buckets->text) === 1, 'The bucket count is a decimal integer.');
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
        $out->keyword('UPDATE', 'HISTOGRAM', 'ON');
        (new Histograms())->render($out, $this->columns);
        if ($this->buckets !== null) {
            $out->keyword('WITH')->node($this->buckets)->keyword('BUCKETS');
        }
        if ($this->update !== null) {
            $out->keyword(...explode(' ', $this->update->value));
        }
    }
}
