<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\RoutineCalls;

/**
 * A call written as `name(...)` or `db.name(...)`: a native function reached by name, a loadable function or a stored function.
 *
 * The server mirrors this as PTI_function_call_generic_ident_sys and
 * PTI_function_call_generic_2d. Only the unqualified form takes argument
 * aliases. The facts follow MYSQL-ROUTINE-CALL-001. The name is written
 * against its parenthesis, because a name followed by a space is a column
 * unless IGNORE_SPACE is set.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/function-resolution.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing an alias on a qualified call
 *     new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('f'), [new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('x'))], new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('db')) // throws \SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction
 */
final class FunctionCall implements Scalar
{
    use Snapshot;

    /**
     * @var list<CallArgument> The arguments in order
     */
    public readonly array $arguments;

    /**
     * @param Name $name The function name
     * @param list<CallArgument> $arguments The arguments in order
     * @param Name|null $schema The database of a qualified call
     */
    public function __construct(public readonly Name $name, array $arguments = [], public readonly ?Name $schema = null)
    {
        $this->arguments = Check::listOf($arguments, CallArgument::class, 'Function arguments are call arguments.');
        Check::input($schema === null || !$this->named(), 'Only an unqualified call takes argument aliases.');
    }

    /**
     * Tells whether an argument has an alias.
     */
    public function named(): bool
    {
        foreach ($this->arguments as $argument) {
            if ($argument->alias !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Derives the arguments and the result of the called function.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $facts = [];
        foreach ($this->arguments as $argument) {
            $facts[] = (new Arguments())->one($argument->expression, $derivation, $environment);
        }

        return (new RoutineCalls())->result($this, $facts, $derivation);
    }

    /**
     * Writes the call with the name against its parenthesis.
     */
    public function render(Output $out): void
    {
        if ($this->schema !== null) {
            $out->name($this->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name, NameUse::Routine)->glue()->symbol('(')->list($this->arguments)->symbol(')');
    }
}
