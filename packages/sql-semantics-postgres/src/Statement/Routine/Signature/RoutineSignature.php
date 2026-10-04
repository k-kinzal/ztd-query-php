<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\FunctionParameter;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * A function, procedure or routine named with or without its argument list.
 *
 * Mirrors PostgreSQL's `ObjectWithArgs` for `function_with_argtypes`. Without
 * an argument list (`args_unspecified`) the name must identify one routine;
 * an empty list names the routine without arguments. The argument types
 * identify the routine; argument names and output arguments are written but
 * do not take part in the lookup of a function.
 * Source: https://www.postgresql.org/docs/17/sql-dropfunction.html.
 *
 * @visibility public
 * @example Telling a name without an argument list
 *     $signature = new \SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\RoutineSignature(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('f')]));
 *     $signature->arguments // => null
 */
final class RoutineSignature implements ObjectReference
{
    use Snapshot;

    /**
     * @var list<FunctionParameter>|null The written arguments; null when no argument list is written
     */
    public readonly ?array $arguments;

    /**
     * @param DottedName $name The routine name
     * @param list<FunctionParameter>|null $arguments The written arguments, without default values; null when no argument list is written
     */
    public function __construct(public readonly DottedName $name, ?array $arguments = null)
    {
        if ($arguments === null) {
            $this->arguments = null;

            return;
        }
        $this->arguments = Check::listOf($arguments, FunctionParameter::class, 'The arguments of a signature are function parameters.');
        foreach ($this->arguments as $argument) {
            Check::input($argument->default === null, 'An argument of a signature has no default value.');
        }
    }

    /**
     * Derives the type modifiers of the arguments.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ($this->arguments ?? [] as $argument) {
            $argument->deriveClause($derivation, $environment);
        }
    }

    /**
     * Writes the name and the argument list, if any.
     */
    public function render(Output $out): void
    {
        $out->node($this->name);
        if ($this->arguments !== null) {
            $out->symbol('(')->list($this->arguments)->symbol(')');
        }
    }
}
