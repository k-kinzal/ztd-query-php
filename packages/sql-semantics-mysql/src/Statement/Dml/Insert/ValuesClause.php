<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Insert;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Expression;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Source\Span;

/**
 * VALUES rows whose expressions carry their own semantic information.
 *
 * @visibility public
 * @example Reaching values without a facts lookup
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("INSERT t VALUES ('42'), ('7')");
 *     count($insert->values->rows) // => 2
 */
final class ValuesClause implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<list<Expression>> The rows in written order
     */
    public readonly array $rows;

    /**
     * @param array<array-key, list<Expression>> $rows The rows, with equal widths
     */
    public function __construct(array $rows, public readonly ?Span $source = null, public readonly ValuesKeyword $keyword = ValuesKeyword::Values)
    {
        Check::input($rows !== [] && array_is_list($rows), 'VALUES contains at least one row.');
        $width = null;
        $values = [];
        foreach ($rows as $row) {
            $values[] = Check::listOf($row, Expression::class, 'A VALUES row contains semantic expressions.');
            $width ??= count($row);
            Check::input(count($row) === $width, 'VALUES rows have equal widths.');
        }
        $this->rows = $values;
    }

    /**
     * Writes VALUES and its parenthesized expression rows.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->keyword->value);
        foreach ($this->rows as $position => $row) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->symbol('(')->list($row)->symbol(')');
        }
    }
}
