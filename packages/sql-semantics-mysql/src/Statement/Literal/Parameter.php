<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Platform\MySql\Statement\Variable\ParameterBinding;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Reference\Missing\UnboundParameter;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A parameter marker: `?`, or `:name` when the language profile reads named parameters.
 *
 * Rule: MYSQL-PARAMETER-001. Facts: the type and the NULL fact depend on the
 * value bound at execution; a session that gives the type of the value bound
 * at the marker's position resolves it, otherwise the fact names the marker as
 * the missing input. Diagnostics: none.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/prepare.html. Status: Implemented.
 *
 * @visibility public
 * @example Naming the unbound parameter a fact depends on
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a = ?');
 *     $query->query->where->right->scalarInformation()->type->missing[0]->describe() // => 'the value bound to parameter ?'
 */
final class Parameter implements Scalar
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param string $marker The marker as written: `?` or `:name`
     */
    public function __construct(public readonly string $marker = '?', public readonly int $position = 0, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        Check::input(preg_match('/\A(?:\?|:[A-Za-z0-9_]+)\z/', $marker) === 1, 'A parameter marker is a question mark or a colon and a name.');
        Check::input($position >= 0, 'A parameter identifies a nonnegative input position.');
    }

    /**
     * Derives the type of the bound value from the session, or the dependence on it.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $bound = Settings::of($derivation->context)->parameters[$this->position] ?? null;
        $binding = new ParameterBinding($this->position);

        return $bound === null ? new ScalarFact(new Dependent([new UnboundParameter($this->marker)]), Nullability::Dependent, $binding) : new ScalarFact(new Known($bound), Nullability::Nullable, $binding);
    }


    /**
     * Writes the marker.
     */
    public function render(Output $out): void
    {
        $out->parameter($this, $this->marker);
    }
}
