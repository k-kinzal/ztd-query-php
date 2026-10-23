<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Subquery;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A test for rows: `EXISTS (SELECT …)` (`Item_exists_subselect`).
 *
 * Rule: MYSQL-EXISTS-001. Facts: 1 when the subquery returns a row and 0
 * otherwise, an integer whose value is never NULL. Modern releases nevertheless
 * publish the combined NULL attribute of the projected columns; legacy releases
 * report NOT NULL. Wildcards denote the constant 1 (MYSQL-EXISTS-001). Terminates: the query is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/exists-and-not-exists-subqueries.html.
 * Status: Implemented.
 *
 * @example Refusing intrinsically empty VALUES in an ordinary existence predicate
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([])]);
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists($query) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 *
 * @visibility public
 * @example Testing for rows
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE EXISTS (SELECT 1)');
 *     $query->facts->scalar($query->statement->query->where)->nullability // => \SqlSemantics\Statement\Type\Nullability::NotNull
 */
final class Exists implements Scalar
{
    use Snapshot;

    /**
     * @param Query $query The query
     */
    public function __construct(public readonly Query $query)
    {
        \SqlSemantics\Diagnostic\Check::input((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns())->problem($query, true) === null, 'An ordinary subquery predicate has coherent fixed read columns.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\SubqueryOptions())->check($query);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the tested blocks and the release-specific result metadata.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        (new \SqlSemantics\Platform\MySql\Rules\Query\Existence())->mark($this->query, $derivation);
        $query = $derivation->query($this->query, $environment);
        $attribute = Nullability::NotNull;
        if (!in_array($derivation->context->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
            foreach ($query->shape->slots as $slot) {
                $attribute = $attribute->propagate($slot->nullability);
            }
        }

        return (new Operands())->truth($attribute);
    }

    /**
     * Writes EXISTS and the query in parentheses.
     */
    public function render(Output $out): void
    {
        $out->keyword('EXISTS')->symbol('(')->node($this->query)->symbol(')');
    }
}
