<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Prepared as Statement;

/**
 * Writes prepared-statement operands without interpreting dynamic SQL values.
 * @visibility SqlSemantics
 */
final class PreparedStatements
{
    /**
     * Writes each preparation, execution, and deallocation form.
     */
    public static function write(Statement\PrepareQueryStatement|Statement\PrepareTextStatement|Statement\ExecuteQueryStatement|Statement\ExecuteUsingStatement|Statement\DeallocateStatement|Statement\DeallocateAllStatement $statement): Tree
    {
        $dialect = $statement->origin->dialect;
        if ($statement instanceof Statement\DeallocateAllStatement) {
            return Build::keyword('DEALLOCATE ALL');
        }
        $name = Build::identifier([$statement->name], $dialect);
        return match (true) {
            $statement instanceof Statement\PrepareQueryStatement => new Tree('prepare-query', [Build::keyword('PREPARE'), $name, ...($statement->parameterTypes === [] ? [] : [Build::parentheses(Build::separated(array_map(TypeDeclaration::write(...), $statement->parameterTypes)))]), Build::keyword('AS'), Statements::write($statement->statement)]),
            $statement instanceof Statement\PrepareTextStatement => new Tree('prepare-text', [Build::keyword('PREPARE'), $name, Build::keyword('FROM'), Expressions::write($statement->sql)]),
            $statement instanceof Statement\ExecuteQueryStatement => new Tree('execute-query', [Build::keyword('EXECUTE'), $name, ...($statement->arguments === [] ? [] : [Build::parentheses(Build::separated(array_map(Expressions::write(...), $statement->arguments)))])]),
            $statement instanceof Statement\ExecuteUsingStatement => new Tree('execute-using', [Build::keyword('EXECUTE'), $name, ...($statement->variables === [] ? [] : [Build::keyword('USING'), Build::separated(array_map(Expressions::write(...), $statement->variables))])]),
            $statement instanceof Statement\DeallocateStatement => new Tree('deallocate', [Build::keyword($dialect === Dialect::MySql ? 'DEALLOCATE PREPARE' : 'DEALLOCATE'), $name]),
        };
    }
}
