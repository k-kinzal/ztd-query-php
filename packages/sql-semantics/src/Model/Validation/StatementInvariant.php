<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Validation;

use SqlSemantics\Model\BoundStatement;

/**
 * Keeps output order and relation ownership valid for every constructed statement.
 *
 * @visibility SqlSemantics
 */
final class StatementInvariant
{
    /**
     * Validates representation, allowing diagnosed invalid SQL to remain representable.
     * @throws InvalidStructure
     */
    public static function check(BoundStatement $statement): void
    {
        self::collections($statement);
        if ($statement->scopeId === '' || $statement->kind === '') {
            throw new InvalidStructure('A statement requires a scope and operation.');
        }
        foreach ($statement->outputs as $ordinal => $output) {
            if ($output->ordinal !== $ordinal) {
                throw new InvalidStructure('Output positions must be contiguous and ordered.');
            }
        }
        foreach ([...$statement->relations, ...$statement->targets] as $relation) {
            if ($relation->scopeId !== $statement->scopeId) {
                throw new InvalidStructure('A relation belongs to a different statement scope.');
            }
        }
        if ($statement->merge !== null && !in_array($statement->merge->target, $statement->targets, true)) {
            throw new InvalidStructure('The MERGE target must be a statement target.');
        }
        if ($statement->insertion !== null && !in_array($statement->insertion->target, $statement->targets, true)) {
            throw new InvalidStructure('The INSERT destination must be a statement target.');
        }
    }
    /**
     * Checks runtime element types before accessing collection members.
     */
    public static function collections(BoundStatement $statement): void
    {
        Collections::objects($statement->diagnostics, \SqlSemantics\Model\Diagnostic::class);
        Collections::objects($statement->indexes, \SqlSemantics\Model\Definition\IndexDeclaration::class);
        Collections::objects($statement->relations, \SqlSemantics\Model\TableUse::class);
        Collections::objects($statement->targets, \SqlSemantics\Model\TableUse::class);
        Collections::objects($statement->outputs, \SqlSemantics\Model\OutputColumn::class);
        Collections::objects($statement->orderBy, \SqlSemantics\Model\Ordering::class);
        Collections::objects($statement->groupBy, \SqlSemantics\Model\Expression::class);
        Collections::objects($statement->assignments, \SqlSemantics\Model\Expression::class, false);
        Collections::objects($statement->statements, BoundStatement::class);
        Collections::objects($statement->ctes, BoundStatement::class, false);
        Collections::objects($statement->queries, \SqlSemantics\Model\BoundQuery::class);
        Collections::objects($statement->branches, \SqlSemantics\Model\BoundQuery::class);
        Collections::objects($statement->definitions, \SqlSemantics\Model\Definition\TableDeclaration::class);
        Collections::objects($statement->declarations, \SqlSemantics\Schema\TableDefinition::class);
        Collections::objects($statement->writes, \SqlSemantics\Model\Write\Assignment::class);
        Collections::objects($statement->settings, \SqlSemantics\Model\Configuration\Setting::class);
        Collections::objects($statement->conflicts, \SqlSemantics\Model\Write\ConflictAction::class);
        foreach ([...$statement->rows, ...array_values($statement->clauses)] as $expressions) {
            Collections::objects($expressions, \SqlSemantics\Model\Expression::class);
        }
    }

}
