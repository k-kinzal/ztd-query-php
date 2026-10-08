<?php

declare(strict_types=1);

namespace MySqlMemory\Command\View;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Program\ProgramSource;
use MySqlMemory\Dictionary\View;
use MySqlMemory\Error\ErrorCode;
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
            throw ErrorCode::BadDatabase->error($database);
        }
        $name = $definition->name->name->value;
        foreach (array_keys(Views::tables($operation, $definition->query, $session->variables->database)) as $key) {
            [$holder, $read] = explode('.', $key, 2);
            $inner = $session->instance->dictionary->schema($holder)->views[$read] ?? null;
            if ($inner !== null) {
                Views::refresh($inner, $session->instance->dictionary, $session->settings());
            }
        }
        $replaces = $statement instanceof AlterView || $statement->orReplace;
        if ($schema->table($name) !== null) {
            throw $replaces ? ErrorCode::WrongObject->error($database, $name, 'VIEW') : ErrorCode::TableExists->error($name);
        }
        if ($statement instanceof AlterView && !isset($schema->views[$name])) {
            throw ErrorCode::NoSuchTable->error($database, $name);
        }
        if (!$replaces && isset($schema->views[$name])) {
            throw ErrorCode::TableExists->error($name);
        }
        foreach ((new Walker())->find($definition->query, TableReference::class) as $reference) {
            $resolution = $operation->facts->covers($reference) ? $operation->facts->relation($reference)->table : null;
            $stored = $resolution instanceof DeclaredTable ? $session->instance->dictionary->table($resolution->table->name->schema->value ?? $session->variables->database, $resolution->table->name->name->value) : null;
            if ($stored !== null && $stored->definition->temporary) {
                throw ErrorCode::ViewSelectTemporary->error($reference->name->name->value);
            }
        }
        $source = ProgramSource::of($session);
        $select = $source->text('query_expression_with_opt_locking_clauses');
        $resolved = $statement instanceof CreateView ? $operation : $session->analyze('CREATE OR REPLACE VIEW ' . \MySqlMemory\Dictionary\Routine::quoted($database) . '.' . \MySqlMemory\Dictionary\Routine::quoted($name) . ($definition->columns === null ? '' : '(' . implode(',', array_map(static fn (Name $column): string => \MySqlMemory\Dictionary\Routine::quoted($column->value), $definition->columns)) . ')') . ' AS ' . $select);
        $resolvedStatement = $resolved->statement;
        assert($resolvedStatement instanceof CreateView);
        $declared = $resolved->declarations()[0];
        $declaration = new Table(new QualifiedName(new Name($name), new Name($database)), $declared->profile, $declared->columns, $declared->implicit, $declared->complete, RelationKind::View, $declared->keys, $declared->partitions);
        $algorithm = ($definition->algorithm ?? ViewAlgorithm::Undefined)->value;
        $query = $resolvedStatement->definition->query;
        if ($algorithm === 'MERGE' && !(new Materialization())->mergeable($query)) {
            $context->warning(ErrorCode::ViewMergeUnavailable);
            $algorithm = 'UNDEFINED';
        }
        $check = match ($definition->check) {
            null => '',
            ViewCheckOption::Unqualified, ViewCheckOption::Cascaded => 'CASCADED',
            ViewCheckOption::Local => 'LOCAL',
        };
        $schema->views[$name] = new View($database, $name, ProgramSource::definer($definition->definer, $session, $context), $algorithm, ($definition->security ?? \SqlSemantics\Platform\MySql\Statement\View\ViewSecurity::Definer)->value, $check, $select, $session->variables->database === '' ? $database : $session->variables->database, $resolved, $query, $declaration, Views::tables($resolved, $query, $session->variables->database), [(string) $session->variables->read('character_set_client'), (string) $session->variables->read('collation_connection')]);

        return new Completion(0, 0, $context->diagnostics->count());
    }
}
