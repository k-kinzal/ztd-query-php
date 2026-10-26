<?php

declare(strict_types=1);

namespace MySqlMemory\Command\View;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Statement;

/**
 * Executes INSERT, UPDATE and DELETE of one table, through a view when the table is one.
 *
 * A statement whose target is a base table is executed by the command of its kind. One whose
 * target is a view is rewritten against the base table of the view and executed; a view that is
 * not updatable is refused (ER_NON_INSERTABLE_TABLE, ER_NON_UPDATABLE_TABLE). An UPDATE or a
 * DELETE with a LIMIT through a view that lacks every column of a unique key of its table is
 * noted (ER_VIEW_WITHOUT_KEY).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/view-updatability.html.
 *
 * @visibility MySqlMemory
 */
final class ViewWriteCommand implements Command
{
    /**
     * @param Command $command The command that writes a base table
     */
    public function __construct(public readonly Command $command)
    {
    }

    /**
     * Answers whether the command of the statement clears the diagnostics area.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return $this->command->clearsDiagnostics();
    }

    /**
     * Writes the table, or the base table of the view.
     */
    #[Override]
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof Operation);
        $statement = $operation->statement;
        $target = self::viewTarget($operation);
        if ($target === null) {
            return $this->command->execute($operation, $session, $context, $connection);
        }
        $schema = $target->name->schema->value ?? $session->variables->database;
        $view = $session->instance->dictionary->schema($schema)->views[$target->name->name->value] ?? null;
        if ($view === null) {
            return $this->command->execute($operation, $session, $context, $connection);
        }
        $insert = !$statement instanceof Update && !$statement instanceof Delete;
        $writes = ViewWrites::of($view, $session);
        if ($writes === null) {
            throw $insert ? QueryError::NonInsertableTable->error($view->name, 'INSERT') : QueryError::NonUpdatableTable->error($view->name, $statement instanceof Update ? 'UPDATE' : 'DELETE');
        }
        if (!$insert && $statement->limit !== null && !$writes->keyed && !self::limitable($session)) {
            throw QueryError::NonUpdatableTable->error($view->name, $statement instanceof Update ? 'UPDATE' : 'DELETE');
        }
        $rewritten = (new ViewRewrite())->operation($operation, $writes, $target);
        $reply = (new \MySqlMemory\Session\Execution($session))->perform($rewritten, $this->command);

        return self::noted($statement, $writes, $reply, $session);
    }

    /**
     * Answers the one table a statement writes when it resolves to a view, or null: for a base
     * table, or a statement that writes several tables or none.
     */
    public static function viewTarget(Operation $operation): TableReference|WriteTarget|null
    {
        $statement = $operation->statement;
        $target = match (true) {
            $statement instanceof InsertRows, $statement instanceof InsertSet, $statement instanceof InsertQuery => $statement->into->table,
            $statement instanceof Update => count($statement->tables) === 1 ? $statement->tables[0] : null,
            $statement instanceof Delete => $statement->table,
            default => null,
        };
        $named = $target instanceof TableReference || $target instanceof WriteTarget;
        $resolution = $named && $operation->facts->covers($target) ? $operation->facts->relation($target)->table : null;
        if (!$named || !$resolution instanceof DeclaredTable || $resolution->table->kind !== RelationKind::View) {
            return null;
        }

        return $target;
    }

    /**
     * Answers whether the session lets a LIMIT write through a view that lacks a key of its table (updatable_views_with_limit).
     */
    public static function limitable(Session $session): bool
    {
        return !in_array(strtoupper((string) $session->variables->read('updatable_views_with_limit')), ['NO', '0', 'OFF'], true);
    }

    /**
     * Notes an UPDATE or a DELETE with a LIMIT through a view that lacks a unique key of its
     * table (ER_VIEW_WITHOUT_KEY), and answers the reply with the note counted.
     */
    public static function noted(Statement $statement, ViewWrites $writes, Reply $reply, Session $session): Reply
    {
        if (($statement instanceof Update || $statement instanceof Delete) && $statement->limit !== null && !$writes->keyed && $reply instanceof Completion) {
            $session->diagnostics->note(QueryError::IncompleteViewKey, QueryError::IncompleteViewKey->message());

            return new Completion($reply->affectedRows, $reply->lastInsertId, $session->diagnostics->raised(), $reply->info);
        }

        return $reply;
    }
}
