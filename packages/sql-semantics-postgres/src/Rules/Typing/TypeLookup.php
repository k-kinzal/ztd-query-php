<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Typing;

use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ImproperName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Resolution\TableLookup;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Missing\IncompleteMembers;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Reference\Missing\UndeclaredDomain;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Reference\Table\ConditionalTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Looks a type name up as the server does, against what a context can know.
 *
 * Rule: PG-TYPE-LOOKUP-001. Scope: `GenericType` names and `%TYPE`
 * references. A version 1 context declares relations only, so the types it
 * can know are those of `pg_catalog`, which every database has. A name
 * qualified with `pg_catalog` is that type. An unqualified name is searched
 * along the path: every schema before `pg_catalog` is asked first. The
 * temporary schema holds a type of that name only when a temporary relation
 * of that name exists, which a complete context settles; any other schema
 * before `pg_catalog` may hold an undeclared type. A name in another schema
 * is an undeclared type. A catalog qualifier must be the current database,
 * which is session state. More than three parts is an improper name.
 * Minimum precision: `Known` for a `pg_catalog` type that nothing earlier in
 * the path can hide; otherwise `Dependent` naming the exact missing input.
 * Source: https://www.postgresql.org/docs/17/ddl-schemas.html#DDL-SCHEMAS-CATALOG,
 * https://www.postgresql.org/docs/17/runtime-config-client.html#GUC-SEARCH-PATH.
 * Termination: one pass over the search path. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class TypeLookup
{
    /**
     * Finds the catalog type a name denotes, or the fact that says why it is not known.
     */
    public function find(AnalysisContext $context, DottedName $name): Builtin|Dependent|Invalid
    {
        $qualified = $name->qualified();
        if ($qualified === null) {
            return new Invalid(new ImproperName($name));
        }
        if ($qualified->catalog !== null) {
            return new Dependent([new SessionState('the name of the current database'), new UndeclaredDomain($qualified)]);
        }
        $builtin = Builtin::tryFrom($qualified->name->value);
        if ($qualified->schema !== null) {
            return $builtin !== null && $qualified->schema->value === 'pg_catalog' ? $builtin : new Dependent([new UndeclaredDomain($qualified)]);
        }
        if ($builtin === null) {
            return new Dependent([new UndeclaredDomain($qualified)]);
        }
        foreach ($context->searchPath as $schema) {
            if ($schema->value === 'pg_catalog') {
                return $builtin;
            }
            $hidden = new QualifiedName($qualified->name, $schema);
            if ($schema->value !== 'pg_temp' || $context->declared($qualified, $schema) !== []) {
                return new Dependent([new UndeclaredDomain($hidden)]);
            }
            if (!$context->complete) {
                return new Dependent([new UndeclaredRelation($hidden)]);
            }
        }

        return new Dependent([new UndeclaredDomain($qualified)]);
    }

    /**
     * Finds the declared type of the column a `%TYPE` reference names.
     */
    public function column(AnalysisContext $context, DottedName $name): TypeFact
    {
        $relation = (new DottedName(array_slice($name->parts, 0, -1)))->qualified();
        if ($relation === null) {
            return new Invalid(new ImproperName($name));
        }
        $resolution = (new TableLookup())->find($context, $relation);
        if ($resolution instanceof UndeclaredTable || $resolution instanceof ConditionalTable) {
            return new Dependent([$resolution->missing]);
        }
        if ($resolution instanceof DeclaredTable) {
            return $this->member($context, $resolution, $name->last(), $relation);
        }
        Check::invariant($resolution instanceof Diagnostic, 'A table lookup resolves, depends on missing inputs, or reports a problem.');

        return new Invalid($resolution);
    }

    /**
     * Finds the type of a column of a declared relation.
     */
    public function member(AnalysisContext $context, DeclaredTable $resolution, Name $column, QualifiedName $relation): TypeFact
    {
        $columns = $resolution->table->matchingColumns($column->value, $context->columnNames);
        Check::input(count($columns) <= 1, 'A declared PostgreSQL relation has no two columns of one name.');
        if ($columns !== []) {
            return new Known($columns[0]->type);
        }

        return $resolution->table->complete ? new Invalid(new MissingColumn($column, $relation)) : new Dependent([new IncompleteMembers($resolution->table)]);
    }
}
