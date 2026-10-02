<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Reference\Missing\UnboundParameter;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A parameter marker: `?`, or `:name` when the language profile reads named parameters.
 *
 * Rule: MYSQL-PARAMETER-001. Facts: the type and the NULL fact depend on the
 * value bound at execution, which no context holds; the fact names the
 * marker as the missing input. Diagnostics: none.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/prepare.html. Status: Implemented.
 *
 * @visibility public
 * @example Naming the unbound parameter a fact depends on
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a = ?');
 *     $query->facts->scalar($query->statement->where->right)->type->missing[0]->describe() // => 'the value bound to parameter ?'
 */
final class Parameter implements Scalar
{
    use Snapshot;

    /**
     * @param string $marker The marker as written: `?` or `:name`
     */
    public function __construct(public readonly string $marker = '?')
    {
        Check::input(preg_match('/\A(?:\?|:[A-Za-z0-9_]+)\z/', $marker) === 1, 'A parameter marker is a question mark or a colon and a name.');
    }

    /**
     * Derives the dependence on the bound value.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Dependent([new UnboundParameter($this->marker)]), Nullability::Dependent);
    }

    /**
     * Writes the marker.
     */
    public function render(Output $out): void
    {
        $out->spelled($this->marker);
    }
}
