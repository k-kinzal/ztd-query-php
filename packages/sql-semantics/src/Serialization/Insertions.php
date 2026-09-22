<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization;

use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Parts;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Insert;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Validation\InvalidStructure;

/**

 * Writes only the insertion payload allowed by its concrete form. @visibility SqlSemantics

 */
final class Insertions
{
    /**
     * @throws InvalidStructure
     */
    public static function write(InsertStatement $statement, bool $trigger = false): Tree
    {
        $dialect = $statement->origin->dialect;
        $insertion = $statement->insertion;
        $columns = $insertion->explicitColumns ? Build::parentheses(Build::separated(array_map(Write\StoragePaths::write(...), $insertion->columns))) : new Tree('columns', []);
        $input = match (true) {
            $statement instanceof Insert\InsertValuesStatement => Parts::rows($statement->rows),
            $statement instanceof Insert\InsertSelectStatement => Query\Queries::write($statement->query),
            $statement instanceof Insert\InsertSetStatement => new Tree('set', [Build::keyword('SET'), Write\Assignments::write($statement->writes)]),
            $statement instanceof Insert\InsertDefaultValuesStatement => Build::keyword($dialect === \SqlSemantics\Dialect::MySql ? '() VALUES ()' : 'DEFAULT VALUES'),
            default => throw new InvalidStructure('Unclassified insertion input.'),
        };
        return new Tree('insertion', [Query\QueryParts::with($statement->ctes, $dialect), self::header($statement), $trigger ? Build::identifier([$insertion->target->declaration->name], $dialect) : Query\Relations::write($insertion->target, $dialect), $columns, self::overriding($statement), $input, ...array_map(static fn ($conflict): Tree => Write\Conflicts::write($conflict, $dialect), $statement->conflicts), ...($statement->outputs === [] ? [] : [Build::keyword('RETURNING'), Parts::outputs($statement->outputs, $dialect)])]);
    }
    /**
     * Writes conflict and scheduling policies in the dialect's insertion header.
     */
    public static function header(InsertStatement $statement): Tree
    {
        $policy = $statement->policy;
        $parts = [$statement->mode->value];
        if ($policy instanceof \SqlSemantics\Model\Write\Policy\SqliteInsertion && $policy->onViolation !== \SqlSemantics\Model\Write\Policy\ConstraintResponse::Default) {
            $parts[] = 'OR ' . $policy->onViolation->value;
        }
        if ($policy instanceof \SqlSemantics\Model\Write\Policy\MySqlInsertion) {
            if ($policy->scheduling !== \SqlSemantics\Model\Write\Policy\Scheduling::Default) {
                $parts[] = $policy->scheduling->value;
            }
            if ($policy->ignore) {
                $parts[] = 'IGNORE';
            }
        }
        $parts[] = 'INTO';
        return Build::keyword(implode(' ', $parts));
    }

    /**
     * Writes PostgreSQL's explicit identity override before the row source.
     */
    public static function overriding(InsertStatement $statement): Tree
    {
        $policy = $statement->policy;
        return $policy instanceof \SqlSemantics\Model\Write\Policy\PostgreSqlInsertion && $policy->overriding !== \SqlSemantics\Model\Write\Policy\IdentityOverride::Default ? Build::keyword('OVERRIDING ' . $policy->overriding->value . ' VALUE') : new Tree('identity-override', []);
    }

}
