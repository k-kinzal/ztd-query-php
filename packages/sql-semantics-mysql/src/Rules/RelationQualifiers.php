<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules;

use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\NamedRelation;

/**
 * Tells whether a qualifier of a column or a star names a visible relation.
 *
 * Rule: MYSQL-RELATION-QUALIFIER-001. A qualifier names a relation by its
 * alias when it has one, else by its table name. A database the qualifier
 * writes must be the database of the table or view the relation reads, the
 * current one when its name writes none, even under an alias; any database
 * names a derived table, a common table expression and a table function.
 * Database names compare as table names do. A context that names no current
 * database (MYSQL-CURRENT-DATABASE-001) admits any database for an
 * unqualified table. Verified on a live 8.4 server. Terminates: one
 * comparison per relation.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/identifier-qualifiers.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RelationQualifiers
{
    /**
     * Tells whether a qualifier names a relation visible at a position.
     */
    public function admits(Environment $environment, VisibleRelation $relation, QualifiedName $qualifier): bool
    {
        $names = $environment->context->relationNames;
        $named = $relation->alias === null && $relation->name !== null ? $names->equal($relation->name->name->value, $qualifier->name->value) : $relation->alias !== null && $names->equal($relation->alias->value, $qualifier->name->value);
        if (!$named || $qualifier->schema === null) {
            return $named;
        }
        $node = $relation->relation;
        if (!$node instanceof NamedRelation) {
            return true;
        }
        $table = $node->name();
        if ($table->schema === null && $environment->commonTable($table->name) !== null) {
            return true;
        }
        $schema = $table->schema ?? (new SessionDatabase())->named($environment->context);

        return $schema === null || $names->equal($schema->value, $qualifier->schema->value);
    }

    /**
     * Answers a position whose relations are those a qualifier that writes a database names, so that the qualifier then names them by its table or alias alone.
     */
    public function narrowed(Environment $environment, QualifiedName $qualifier): Environment
    {
        $relations = array_values(array_filter($environment->relations, fn (VisibleRelation $relation): bool => $this->admits($environment, $relation, $qualifier)));

        return new Environment($environment->context, $environment->outer, $relations, $environment->commonTables, $environment->aliases, $environment->written);
    }
}
