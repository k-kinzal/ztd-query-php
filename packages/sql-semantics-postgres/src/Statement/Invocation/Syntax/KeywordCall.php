<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\CallChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\CallTyping;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Argument;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\NamedArgument;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A call of `substring`, `overlay` or `json_object` spelled with the keyword and plain arguments.
 *
 * The server builds a plain `FuncCall`. Rule: PG-KEYWORD-CALL-001. Facts:
 * those of the call (PG-CALL-RESULT-001): `substring` and `overlay` are
 * searched along the path, `json_object` in `pg_catalog` alone; named
 * arguments are left to the routine. JSON_OBJECT takes at least one argument.
 * Source: https://www.postgresql.org/docs/17/functions-string.html, https://www.postgresql.org/docs/17/functions-json.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a keyword call
 *     $call = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\KeywordCall(\SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\KeywordFunction::Substring, []);
 *     [$call->function->value, $call->outputName()->value] // => ['SUBSTRING', 'substring']
 * @example Rejecting JSON_OBJECT without arguments in this form
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\KeywordCall(\SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\KeywordFunction::JsonObject, []) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class KeywordCall implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @var list<Argument> The arguments in order
     */
    public readonly array $arguments;

    /**
     * @param KeywordFunction $function The function
     * @param list<Argument> $arguments The arguments in order
     */
    public function __construct(public readonly KeywordFunction $function, array $arguments)
    {
        $this->arguments = Check::listOf($arguments, Argument::class, 'Call arguments are arguments.', $function === KeywordFunction::JsonObject ? 1 : 0);
    }

    /**
     * Names an unaliased result column after the function.
     */
    public function outputName(): Name
    {
        return new Name(strtolower($this->function->value));
    }

    /**
     * Derives the arguments and the result of the call.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $facts = [];
        $named = false;
        foreach ($this->arguments as $argument) {
            $facts[] = $derivation->scalar($argument->value(), $environment);
            $named = $named || $argument instanceof NamedArgument;
        }
        (new CallChecks())->arguments($derivation, $this->arguments);
        $name = strtolower($this->function->value);
        if ($named) {
            $schema = $this->function->catalogOnly() ? new Name('pg_catalog') : null;

            return new ScalarFact(new Dependent([new UndeclaredRoutine(new QualifiedName(new Name($name), $schema))]), Nullability::Dependent);
        }

        return (new CallTyping())->catalog($derivation->context, $name, $facts, $this->function->catalogOnly());
    }

    /**
     * Writes the keyword and the arguments in parentheses.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->function->value)->glue()->symbol('(')->list($this->arguments)->symbol(')');
    }
}
