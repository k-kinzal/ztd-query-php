<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\UnboundParameter;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A named placeholder, written `:name`, which a profile with the named parameter style reads as a parameter.
 *
 * The server has no such marker; a client library replaces it before the
 * statement is sent. Rule: PG-PARAMETER-002. Facts: the type and NULL fact
 * depend on the bound value. Status: Implemented.
 *
 * @visibility public
 * @example Reading a named placeholder
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, null, null, \SqlSemantics\Contract\ParameterStyle::Named);
 *     $semantics->analyze('SELECT :id')->statement->targets[0]->expression->name // => 'id'
 */
final class NamedParameter implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param string $name The placeholder name without the colon
     */
    public function __construct(public readonly string $name)
    {
        Check::input(preg_match('/\A[A-Za-z0-9_]+\z/', $name) === 1, 'A placeholder name is letters, digits and underscores.');
    }

    /**
     * Gives a result column no name.
     */
    public function outputName(): ?Name
    {
        return null;
    }

    /**
     * Derives facts that depend on the bound value.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Dependent([new UnboundParameter(':' . $this->name)]), Nullability::Dependent);
    }

    /**
     * Writes the placeholder.
     */
    public function render(Output $out): void
    {
        $out->spelled(':' . $this->name);
    }
}
