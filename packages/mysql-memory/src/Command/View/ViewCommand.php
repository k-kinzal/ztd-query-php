<?php

declare(strict_types=1);

namespace MySqlMemory\Command\View;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Program\ProgramSource;
use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Dictionary\View;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Plan\Views;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Rules\Typing\Materialization;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\View\AlterView;
use SqlSemantics\Platform\MySql\Statement\View\CreateView;
use SqlSemantics\Platform\MySql\Statement\View\ViewAlgorithm;
use SqlSemantics\Platform\MySql\Statement\View\ViewCheckOption;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

/**
 * Executes CREATE VIEW and ALTER VIEW.
 *
 * The query is resolved first. A table or view of the name is ER_TABLE_EXISTS_ERROR; OR REPLACE
 * replaces a view but refuses a base table (ER_WRONG_OBJECT), as ALTER VIEW does, which also
 * refuses a missing view (ER_NO_SUCH_TABLE). A query that reads a temporary table is
 * ER_VIEW_SELECT_TMPTABLE. MERGE for a query the server cannot merge is a warning, and the view
 * is UNDEFINED. WITH CHECK OPTION is CASCADED unless it says LOCAL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-view.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-view.html.
 *
 * @visibility MySqlMemory
 */
final class ViewCommand implements Command
{
    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Creates or replaces the view.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof CreateView || $statement instanceof AlterView);
        $session->transaction->commit();
        $definition = $statement->definition;
        $database = ProgramSource::database($definition->name->schema, $session);
        $schema = $session->instance->dictionary->schema($database);
        if ($schema === null) {
            throw QueryError::BadDatabase->error($database);
        }
        $name = $definition->name->name->value;
        self::refreshReadViews($operation, $definition->query, $session);
        self::checkName($statement, $schema, $database, $name);
        self::refuseTemporaryTables($operation, $definition->query, $session);
        $source = ProgramSource::of($session);
        $select = $source->text('query_expression_with_opt_locking_clauses');
        $resolved = $statement instanceof CreateView ? $operation : self::reanalyze($session, $database, $name, $definition->columns, $select);
        $resolvedStatement = $resolved->statement;
        assert($resolvedStatement instanceof CreateView);
        $declared = $resolved->declarations()[0];
        $declaration = new Table(new QualifiedName(new Name($name), new Name($database)), $declared->profile, $declared->columns, $declared->implicit, $declared->complete, RelationKind::View, $declared->keys, $declared->partitions);
        $query = $resolvedStatement->definition->query;
        $algorithm = self::algorithm($definition->algorithm, $query, $context);
        $check = self::checkOption($definition->check);
        $schema->views[$name] = $view = new View($database, $name, ProgramSource::definer($definition->definer, $session, $context), $algorithm, ($definition->security ?? \SqlSemantics\Platform\MySql\Statement\View\ViewSecurity::Definer)->value, $check, $select, $session->variables->database === '' ? $database : $session->variables->database, $resolved, $query, $declaration, Views::tables($resolved, $query, $session->variables->database), [(string) $session->variables->read('character_set_client'), (string) $session->variables->read('collation_connection')]);
        $view->hints = (new \MySqlMemory\Hint\Hints())->view($query, $session);

        return new Completion(0, 0, $context->diagnostics->count());
    }

    /**
     * Brings the views the query reads up to date with the tables they read, so that the new view
     * resolves against their current columns.
     */
    public static function refreshReadViews(Operation $operation, Query $query, Session $session): void
    {
        foreach (array_keys(Views::tables($operation, $query, $session->variables->database)) as $key) {
            [$holder, $read] = explode('.', $key, 2);
            $inner = $session->instance->dictionary->schema($holder)->views[$read] ?? null;
            if ($inner !== null) {
                Views::refresh($inner, $session->instance->dictionary, $session->settings());
            }
        }
    }

    /**
     * Checks the name of the view against the tables and views of its database: a base table of
     * the name is ER_TABLE_EXISTS_ERROR, or ER_WRONG_OBJECT when the statement replaces; ALTER VIEW
     * of a missing view is ER_NO_SUCH_TABLE; and an existing view is ER_TABLE_EXISTS_ERROR unless
     * the statement replaces it.
     *
     * @throws SqlError When the name cannot be used for the view
     */
    public static function checkName(CreateView|AlterView $statement, Schema $schema, string $database, string $name): void
    {
        $replaces = $statement instanceof AlterView || $statement->orReplace;
        if ($schema->table($name) !== null) {
            throw $replaces ? SchemaError::WrongObject->error($database, $name, 'VIEW') : SchemaError::TableExists->error($name);
        }
        if ($statement instanceof AlterView && !isset($schema->views[$name])) {
            throw QueryError::NoSuchTable->error($database, $name);
        }
        if (!$replaces && isset($schema->views[$name])) {
            throw SchemaError::TableExists->error($name);
        }
    }

    /**
     * Refuses a query that reads a temporary table (ER_VIEW_SELECT_TMPTABLE).
     *
     * @throws SqlError When a table the query reads is temporary
     */
    public static function refuseTemporaryTables(Operation $operation, Query $query, Session $session): void
    {
        foreach ((new Walker())->find($query, TableReference::class) as $reference) {
            $resolution = $operation->facts->covers($reference) ? $operation->facts->relation($reference)->table : null;
            $stored = $resolution instanceof DeclaredTable ? $session->instance->dictionary->table($resolution->table->name->schema->value ?? $session->variables->database, $resolution->table->name->name->value) : null;
            if ($stored !== null && $stored->definition->temporary) {
                throw SchemaError::ViewSelectTemporary->error($reference->name->name->value);
            }
        }
    }

    /**
     * Resolves the query of ALTER VIEW as CREATE OR REPLACE VIEW of the same name, columns and
     * query text.
     *
     * @param list<Name>|null $columns The column list of the view, or null when none is written
     * @throws SqlError When the statement does not resolve
     */
    public static function reanalyze(Session $session, string $database, string $name, ?array $columns, string $select): Operation
    {
        return $session->analyze('CREATE OR REPLACE VIEW ' . \MySqlMemory\Dictionary\Routine::quoted($database) . '.' . \MySqlMemory\Dictionary\Routine::quoted($name) . ($columns === null ? '' : '(' . implode(',', array_map(static fn (Name $column): string => \MySqlMemory\Dictionary\Routine::quoted($column->value), $columns)) . ')') . ' AS ' . $select);
    }

    /**
     * Answers the algorithm the view keeps: the one written, UNDEFINED by default, and UNDEFINED
     * with a warning (ER_WARN_VIEW_MERGE) for MERGE over a query the server cannot merge.
     */
    public static function algorithm(?ViewAlgorithm $algorithm, Query $query, Context $context): string
    {
        $written = ($algorithm ?? ViewAlgorithm::Undefined)->value;
        if ($written === 'MERGE' && !(new Materialization())->mergeable($query)) {
            $context->warning(SchemaError::ViewMergeUnavailable);

            return 'UNDEFINED';
        }

        return $written;
    }

    /**
     * Answers the check option the view keeps: CASCADED unless LOCAL is written, and nothing
     * without WITH CHECK OPTION.
     */
    public static function checkOption(?ViewCheckOption $check): string
    {
        return match ($check) {
            null => '',
            ViewCheckOption::Unqualified, ViewCheckOption::Cascaded => 'CASCADED',
            ViewCheckOption::Local => 'LOCAL',
        };
    }
}
