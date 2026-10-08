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
use SqlSemantics\Platform\MySql\Statement\View\DropView;
use SqlSemantics\Statement\Operation;

/**
 * Executes DROP VIEW.
 *
 * A name written twice is ER_NONUNIQ_TABLE. Without IF EXISTS a base table is ER_WRONG_OBJECT,
 * and the missing views are named together in one ER_BAD_TABLE_ERROR, and nothing is dropped;
 * with it each missing view and each base table is a note, in the order of the list.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-view.html.
 *
 * @visibility MySqlMemory
 */
final class DropViewCommand implements Command
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
     * Drops the views.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof DropView);
        $session->transaction->commit();
        $dictionary = $session->instance->dictionary;
        $seen = [];
        $targets = [];
        foreach ($statement->views as $view) {
            $schema = ProgramSource::database($view->schema, $session);
            $key = $schema . '.' . $view->name->value;
            if (isset($seen[$key])) {
                throw ErrorCode::NonUniqueTable->error($view->name->value);
            }
            $seen[$key] = true;
            $targets[] = [$schema, $view->name->value];
        }
        $missing = [];
        $found = [];
        foreach ($targets as [$schema, $name]) {
            $holder = $dictionary->schema($schema);
            if ($holder?->table($name) !== null) {
                if (!$statement->ifExists) {
                    throw ErrorCode::WrongObject->error($schema, $name, 'VIEW');
                }
                $context->note(ErrorCode::WrongObject, $schema, $name, 'VIEW');
            } elseif ($holder === null || !isset($holder->views[$name])) {
                $missing[] = $schema . '.' . $name;
                if ($statement->ifExists) {
                    $context->note(ErrorCode::BadTable, $schema . '.' . $name);
                }
            } else {
                $found[] = [$holder, $name];
            }
        }
        if ($missing !== [] && !$statement->ifExists) {
            throw ErrorCode::BadTable->error(implode(',', $missing));
        }
        foreach ($found as [$holder, $name]) {
            unset($holder->views[$name]);
        }

        return new Completion(0, 0, $context->diagnostics->count());
    }
}
