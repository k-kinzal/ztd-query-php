<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Subquery;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\QueryWidth;
use SqlSemantics\Platform\MySql\Rules\Expression\SubqueryRows;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;

/**
 * A subquery whose explicit output contains several columns.
 *
 * It can participate in row comparisons, but cannot occupy a scalar position.
 * Its output width is fixed by structure; wildcard queries remain dependent
 * scalar subqueries until their declaration context supplies their width.
 *
 * @example Refusing a fixed scalar as a row subquery
 *     $item = new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'));
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\RowSubquery(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [$item])) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 *
 * @visibility public
 * @example Keeping a multi-column query outside the scalar domain
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')), new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'))]);
 *     (new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\RowSubquery($query)) instanceof \SqlSemantics\Statement\Scalar // => false
 */
final class RowSubquery implements RowOperand
{
    use Snapshot;

    /**
     * @param Query $query A query with an explicit multi-column projection
     */
    public function __construct(public readonly Query $query)
    {
        Check::input((new QueryWidth())->of($query) > 1, 'A row subquery has a fixed output of at least two columns.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\SubqueryOptions())->check($query);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the output fields in the enclosing environment.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $query = $derivation->query($this->query, $environment);
        $rows = new SubqueryRows();
        $value = $rows->value($query, $derivation->facts());
        return new ScalarFact($value->type, $rows->attribute($this->query, $query, $derivation->context->profile->grammar));
    }

    /**
     * Writes the query in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->query)->symbol(')');
    }
}
