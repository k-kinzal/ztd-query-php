<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\RoutineCalls;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

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
 * @visibility public
 * @example Typing a call of a native function reached by name
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT concat('a', 'b')");
 *     $query->field(0)->type->descriptor->name() // => 'VARCHAR'
 * @example Depending on the signature of a stored function
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT db.score(1)');
 *     $query->field(0)->type->missing[0]->describe() // => 'the signature of routine score'
 * @example Refusing an alias on a qualified call
 *     new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('f'), [new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Statement\Identifier\Name('x'))], new \SqlSemantics\Statement\Identifier\Name('db')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
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
