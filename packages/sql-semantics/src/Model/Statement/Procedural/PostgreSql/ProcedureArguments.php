<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Procedural\PostgreSql;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Argument-list invariants the server checks before looking up a procedure.
 * @visibility SqlSemantics
 */
final class ProcedureArguments
{
    /**
     * Positional arguments precede named ones, parameter names are unique, only the last argument is variadic, and values use PostgreSQL.
     * @param list<ProcedureArgument> $arguments
     * @throws InvalidStructure
     */
    public static function validate(array $arguments): void
    {
        $names = [];
        $last = count($arguments) - 1;
        foreach ($arguments as $position => $argument) {
            if ($argument->value->type->dialect !== Dialect::PostgreSql) {
                throw new InvalidStructure('Procedure arguments must use the statement dialect.');
            }
            if ($argument->variadic && $position !== $last) {
                throw new InvalidStructure('Only the last procedure argument can be variadic.');
            }
            if ($argument->name === null && $names !== []) {
                throw new InvalidStructure('A positional argument cannot follow a named argument.');
            }
            if ($argument->name !== null && isset($names[$argument->name])) {
                throw new InvalidStructure('A procedure argument name can be used only once.');
            }
            if ($argument->name !== null) {
                $names[$argument->name] = true;
            }
        }
    }
}
