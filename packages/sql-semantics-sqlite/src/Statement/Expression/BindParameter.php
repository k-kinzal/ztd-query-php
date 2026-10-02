<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

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
 * A bind parameter: a placeholder for a value supplied when the statement runs.
 *
 * Rule: SQLITE-PARAMETER-001. The parameter is identified by its prefix and
 * its label: no label for an anonymous `?`, digits for `?NNN`, a name for the
 * other prefixes. Its type and NULL fact depend on the bound value.
 * Source: https://sqlite.org/lang_expr.html#parameters. Status: Implemented.
 *
 * @visibility public
 * @example Reading a numbered parameter
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT ?2');
 *     [$query->statement->columns[0]->expression->label, $query->field(0)->type->missing[0]->marker] // => ['2', '?2']
 * @example Refusing a label that would not be read back as one parameter
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\BindParameter(\SqlSemantics\Platform\Sqlite\Statement\Expression\ParameterPrefix::Colon, 'a b') // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class BindParameter implements Scalar
{
    use Snapshot;

    /**
     * @param ParameterPrefix $prefix The prefix character
     * @param string $label The text after the prefix: empty or digits after a question mark, a name otherwise
     */
    public function __construct(public readonly ParameterPrefix $prefix, public readonly string $label = '')
    {
        $pattern = $prefix === ParameterPrefix::Question ? '/\A[0-9]*\z/' : '/\A(?:[A-Za-z0-9_$\x80-\xff]|::)+(?:\([^\s()]*\))?\z/';
        Check::input(preg_match($pattern, $label) === 1, 'The label does not spell one bind parameter.');
    }

    /**
     * Answers the parameter as written: the prefix and the label.
     */
    public function marker(): string
    {
        return $this->prefix->value . $this->label;
    }

    /**
     * Derives facts that depend on the bound value.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Dependent([new UnboundParameter($this->marker())]), Nullability::Dependent);
    }

    /**
     * Writes the parameter.
     */
    public function render(Output $out): void
    {
        $out->spelled($this->marker());
    }
}
