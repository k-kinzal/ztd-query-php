<?php

declare(strict_types=1);

namespace MySqlMemory\Command\View;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Program\ProgramSource;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlParser\Parser\Node;
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
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
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
            throw $insert ? ErrorCode::NonInsertableTable->error($view->name, 'INSERT') : ErrorCode::NonUpdatableTable->error($view->name, $statement instanceof Update ? 'UPDATE' : 'DELETE');
        }
        $source = ProgramSource::of($session);
        $reply = $session->execute($insert ? $this->insert($source, $writes, $view->name) : $this->change($source, $writes, [$view->name, $target->alias->value ?? $view->name], $session));
        if (($statement instanceof Update || $statement instanceof Delete) && $statement->limit !== null && !$writes->keyed && $reply instanceof Completion) {
            $session->diagnostics->note(ErrorCode::IncompleteViewKey, ErrorCode::IncompleteViewKey->message());

            return new Completion($reply->affectedRows, $reply->lastInsertId, $session->diagnostics->count(), $reply->info);
        }

        return $reply;
    }

    /**
     * Rewrites an INSERT through a view: the base table, and the base columns of the columns it names or of every column of the view.
     *
     * @throws \MySqlMemory\Error\SqlError When a column written is computed, or the view has a computed column (ER_NON_INSERTABLE_TABLE)
     */
    public function insert(ProgramSource $source, ViewWrites $writes, string $view): string
    {
        $tree = $source->tree;
        $table = $tree->find('table_ident')[0] ?? null;
        $span = $table?->span();
        if ($span === null) {
            throw ErrorCode::NotSupportedYet->error('this INSERT through a view');
        }
        $edits = [];
        $columns = $tree->find('insert_columns');
        $named = $columns !== [];
        foreach ([...$tree->find('insert_column'), ...$tree->find('simple_ident_nospvar')] as $column) {
            $written = $column->span();
            if ($written !== null) {
                $edits[$written[0]] = [$written[0], $written[1], $writes->written(ViewWrites::unquoted(trim($column->text($source->text))))];
            }
        }
        $list = '';
        if (!$named && $tree->find('update_list') === []) {
            $list = ' (' . implode(',', array_map(static fn (array $column): string => $writes->written($column[0]), $writes->columns)) . ')';
        }
        foreach ($writes->columns as $column) {
            if ($column[1] === null) {
                throw ErrorCode::NonInsertableTable->error($view, 'INSERT');
            }
        }
        $partition = $tree->find('opt_use_partition')[0] ?? null;
        $end = $partition?->span()[1] ?? $span[1];
        $edits[$span[0]] = [$span[0], $end, $writes->table . $list];
        foreach ($tree->find('opt_insert_update_list') as $update) {
            $edits = $writes->reads($update, $source->text, [$view], $edits);
        }

        return ViewWrites::apply($source->text, $edits);
    }

    /**
     * Rewrites an UPDATE or a DELETE through a view: the base table, the base columns, and the condition of the view added to the statement's.
     *
     * @param list<string> $names The names that qualify a column of the view: its name and its alias
     *
     * @throws \MySqlMemory\Error\SqlError When a column assigned is computed
     */
    public function change(ProgramSource $source, ViewWrites $writes, array $names, Session $session): string
    {
        $tree = $source->tree;
        $table = $tree->find('table_ident')[0] ?? null;
        $span = $table?->span();
        $statement = $tree->find('update_stmt')[0] ?? $tree->find('delete_stmt')[0] ?? null;
        if ($span === null || !$statement instanceof Node) {
            throw ErrorCode::NotSupportedYet->error('this statement through a view');
        }
        $alias = $tree->find('opt_table_alias')[0] ?? null;
        $edits = [$span[0] => [$span[0], $alias?->span()[1] ?? $span[1], $writes->table . $writes->alias]];
        foreach ($tree->find('update_elem') as $element) {
            $column = $element->children[0] ?? null;
            $written = $column instanceof Node ? $column->span() : null;
            if ($column instanceof Node && $written !== null) {
                $edits[$written[0]] = [$written[0], $written[1], $writes->written(ViewWrites::unquoted(trim((string) preg_replace('/\A.*\./s', '', $column->text($source->text)))))];
            }
            $value = $element->children[2] ?? null;
            if ($value instanceof Node) {
                $edits = $writes->reads($value, $source->text, $names, $edits);
            }
        }
        $where = $tree->find('where_clause')[0] ?? null;
        $condition = $where?->children[1] ?? null;
        if ($condition instanceof Node && $condition->span() !== null) {
            $edits = $writes->reads($condition, $source->text, $names, $edits);
        }
        foreach ([...$tree->find('order_clause'), ...$tree->find('opt_simple_limit')] as $clause) {
            $edits = $writes->reads($clause, $source->text, $names, $edits);
        }
        $text = ViewWrites::apply($source->text, $edits);
        if ($writes->where === null) {
            return $text;
        }
        $rewritten = $session->semantics()->parser()->parse($text);
        $condition = ($rewritten->find('where_clause')[0] ?? null)?->children[1] ?? null;
        $span = $condition instanceof Node ? $condition->span() : null;
        if ($span !== null) {
            return substr($text, 0, $span[0]) . '(' . substr($text, $span[0], $span[1] - $span[0]) . ') AND (' . $writes->where . ')' . substr($text, $span[1]);
        }
        $anchor = ($rewritten->find('update_list')[0] ?? $rewritten->find('opt_use_partition')[0] ?? $rewritten->find('table_ident')[0] ?? null)?->span()[1] ?? strlen(rtrim($text));

        return substr($text, 0, $anchor) . ' WHERE (' . $writes->where . ')' . substr($text, $anchor);
    }
}
