<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Validation;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\WithClause;

/**
 * Validates the shared dialect, result-position and CTE invariants of statement operands.
 * @visibility SqlSemantics
 */
final class StatementOperands
{
    /**
     * @param list<OutputColumn> $outputs Ordered projections
     * @throws InvalidStructure
     */
    public static function outputs(array $outputs, Dialect $dialect, bool $returning = false): void
    {
        Collections::objects($outputs, OutputColumn::class);
        if ($returning && $dialect === Dialect::MySql && $outputs !== []) {
            throw new InvalidStructure('MySQL mutations do not have a RETURNING projection.');
        }
        foreach ($outputs as $ordinal => $output) {
            if ($output->ordinal !== $ordinal || $output->expression->type->dialect !== $dialect) {
                throw new InvalidStructure('Result positions and expression dialects must agree with the statement.');
            }
        }
    }

    /**
     * @param list<Expression|null> $expressions Expressions evaluated by this operation
     * @throws InvalidStructure
     */
    public static function expressions(array $expressions, Dialect $dialect): void
    {
        foreach ($expressions as $expression) {
            if ($expression !== null && $expression->type->dialect !== $dialect) {
                throw new InvalidStructure('A statement expression must use its statement dialect.');
            }
        }
    }

    /**
     * Checks CTE definitions without modifying their declaration order or scope.
     * @throws InvalidStructure
     */
    public static function ctes(?WithClause $with, Dialect $dialect): void
    {
        foreach ($with->definitions ?? [] as $definition) {
            if ($definition->query->origin->dialect !== $dialect) {
                throw new InvalidStructure('A CTE must use its enclosing statement dialect.');
            }
        }
    }
    /**
     * Checks relation declarations and nested inputs against the owning dialect.
     * @throws InvalidStructure
     */
    public static function relation(\SqlSemantics\Model\TableUse|\SqlSemantics\Model\Join|null $input, Dialect $dialect): void
    {
        JoinOrder::straight($input, $dialect);
        foreach (\SqlSemantics\Model\Relation\Joining\Inputs::tables($input) as $table) {
            if ($table instanceof \SqlSemantics\Model\Relation\OnlyTableReference && $dialect !== Dialect::PostgreSql) {
                throw new InvalidStructure('ONLY table references require PostgreSQL.');
            }
            if ($table instanceof \SqlSemantics\Model\Relation\TableReference && $table->indexHints !== [] && $dialect !== Dialect::MySql) {
                throw new InvalidStructure('Index hints require MySQL.');
            }
            if ($table->declaration->properties !== null && $table->declaration->properties->dialect() !== $dialect) {
                throw new InvalidStructure('A relation declaration must use the statement dialect.');
            }
            foreach ($table->declaration->columns as $column) {
                if ($column->type->dialect !== $dialect) {
                    throw new InvalidStructure('A relation column must use the statement dialect.');
                }
            }
            self::expressions($table->resultExpressions(), $dialect);
            if ($table instanceof \SqlSemantics\Model\Relation\AliasedRelation) {
                self::relation($table->input, $dialect);
            }
        }
    }

}
