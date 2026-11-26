<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\UnboundParameter;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;

/**
 * A parameter marker: `?`, or `:name` when the language profile reads named parameters.
 *
 * Rule: MYSQL-PARAMETER-001. Facts: the type and the NULL fact depend on the
 * value bound at execution, which no context holds; the fact names the
 * marker as the missing input. Diagnostics: none.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/prepare.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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
