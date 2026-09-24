<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Query;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Loading\LoadLayout;
use SqlSemantics\Model\Statement\Retrieval;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Table\Persistence;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes SELECT ... INTO statements: the query with its INTO clause where the dialect expects it.
 * @visibility SqlSemantics
 */
final class Retrievals
{
    /**
     * Writes a SELECT ... INTO form, or returns null for any other statement.
     * @throws InvalidStructure
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if ($statement instanceof Retrieval\SelectIntoTableStatement) {
            return self::leftmost(Queries::write($statement->query), self::table($statement));
        }
        if (!$statement instanceof Retrieval\SelectIntoVariablesStatement && !$statement instanceof Retrieval\SelectIntoOutfileStatement && !$statement instanceof Retrieval\SelectIntoDumpfileStatement) {
            return null;
        }
        return self::last($statement->query, self::destination($statement));
    }

    /**
     * Writes the MySQL INTO clause naming user variables, an export file or a dump file.
     */
    public static function destination(Retrieval\SelectIntoVariablesStatement|Retrieval\SelectIntoOutfileStatement|Retrieval\SelectIntoDumpfileStatement $statement): Tree
    {
        return match (true) {
            $statement instanceof Retrieval\SelectIntoVariablesStatement => new Tree('into', [Build::keyword('INTO'), Build::separated(array_map(static fn (string $name): Tree => \SqlSemantics\Serialization\Scalar\ReferenceExpressions::variable($name, \SqlSemantics\Schema\VariableScope::User, Dialect::MySql), $statement->variables))]),
            $statement instanceof Retrieval\SelectIntoOutfileStatement => new Tree('into', [
                Build::keyword('INTO OUTFILE'),
                Expressions::write($statement->file),
                ...($statement->characterSet === null ? [] : [Build::keyword('CHARACTER SET'), Build::identifier([$statement->characterSet], Dialect::MySql)]),
                ...\SqlSemantics\Serialization\Procedural\Loads::separators(new LoadLayout(null, $statement->fields, $statement->lines), $statement->lines->terminator),
            ]),
            $statement instanceof Retrieval\SelectIntoDumpfileStatement => new Tree('into', [Build::keyword('INTO DUMPFILE'), Expressions::write($statement->file)]),
        };
    }

    /**
     * Writes the PostgreSQL INTO clause naming the created table and its persistence.
     */
    public static function table(Retrieval\SelectIntoTableStatement $statement): Tree
    {
        $persistence = match ($statement->persistence) {
            Persistence::Permanent => 'INTO TABLE',
            Persistence::Temporary => 'INTO TEMPORARY TABLE',
            Persistence::Unlogged => 'INTO UNLOGGED TABLE',
        };
        return new Tree('into', [Build::keyword($persistence), Build::identifier($statement->table->parts, Dialect::PostgreSql)]);
    }

    /**
     * Places a PostgreSQL INTO clause after the select list of the first SELECT.
     * @throws InvalidStructure
     */
    public static function leftmost(Tree $query, Tree $into): Tree
    {
        if ($query->role === 'select') {
            return new Tree('select', [...array_slice($query->children, 0, 4), $into, ...array_slice($query->children, 4)]);
        }
        $children = $query->children;
        foreach ($children as $index => $child) {
            if ($child instanceof Tree && !($query->role === 'query' && $index === 0)) {
                $children[$index] = self::leftmost($child, $into);
                return new Tree($query->role, $children);
            }
        }
        throw new InvalidStructure('SELECT ... INTO requires a SELECT as its first query operand.');
    }

    /**
     * Places a MySQL INTO clause after the query expression and before its locking clauses.
     * @throws InvalidStructure
     */
    public static function last(\SqlSemantics\Model\BoundQuery $source, Tree $into): Tree
    {
        $query = Queries::write($source, true);
        $children = $query->children;
        $last = $children[count($children) - 1] ?? null;
        if ($last instanceof Tree && $last->role === 'locking') {
            return new Tree($query->role, [...array_slice($children, 0, -1), $into, $last]);
        }
        return new Tree($query->role, [...$children, $into]);
    }
}
