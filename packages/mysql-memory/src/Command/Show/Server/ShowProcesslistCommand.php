<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show\Server;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\System\Server\Processlist;
use Override;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowProcesslist;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW [FULL] PROCESSLIST: the sessions connected, by connection id.
 *
 * The rows are those of INFORMATION_SCHEMA.PROCESSLIST, which MySQL 8.0 and later read with a
 * deprecation warning; the session that runs the statement is in the state init, starting in
 * 5.6 and 5.7. Without FULL the statement of a session is cut to its first 100 characters
 * (verified on live 5.7.44, 8.0.44 and 8.4.7 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-processlist.html.
 *
 * @visibility MySqlMemory
 */
final class ShowProcesslistCommand implements Command
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
     * Lists the sessions.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowProcesslist);
        $legacy = $session->settings()->legacy();
        if (!$legacy) {
            $context->warning(StatementError::DeprecatedSyntax, 'INFORMATION_SCHEMA.PROCESSLIST', 'performance_schema.processlist');
        }
        $rows = [];
        $sessions = [];
        foreach ($session->instance->sessions as $id => $reference) {
            $open = $reference->get();
            if ($open !== null && isset($session->instance->registry->threads->connected[$id])) {
                $sessions[$id] = $open;
            }
        }
        ksort($sessions);
        foreach ($sessions as $id => $open) {
            $row = array_values(Processlist::row($open, $id === $session->id, $legacy ? 'starting' : 'init'));
            $info = $row[7];
            $row[7] = is_string($info) && !$statement->full ? mb_substr($info, 0, 100) : $info;
            $rows[] = array_slice($row, 0, 8);
        }
        $daemon = $session->instance->registry->eventScheduler->row();
        if ($daemon !== null) {
            $rows[] = array_slice(array_values($daemon), 0, 8);
        }

        return (new Listing($this->headings($statement->full, $session->settings()->release())))->sent($rows, $context);
    }

    /**
     * Answers the columns of the rows.
     *
     * @return list<Heading>
     */
    public function headings(bool $full, GrammarRelease $release): array
    {
        $legacy = $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;
        $number = ColumnFlag::NotNull->value | ColumnFlag::Binary->value | ColumnFlag::Numeric->value;
        $text = ColumnFlag::NotNull->value;

        return [
            new Heading('Id', Field::LongLong, $legacy ? 21 : 22, $number),
            Heading::text('User', Field::VarString, 32, $text, 31),
            Heading::text('Host', Field::VarString, $legacy ? 64 : 255, $text, 31),
            Heading::text('db', Field::VarString, 64, 0, 31),
            Heading::text('Command', Field::VarString, 16, $text, 31),
            new Heading('Time', Field::Long, $legacy ? 7 : 8, $number),
            Heading::text('State', Field::VarString, 30, 0, 31),
            $full ? Heading::text('Info', $legacy ? Field::MediumBlob : Field::LongBlob, $legacy ? 12582912 : 201326592, 0, 31) : Heading::text('Info', Field::VarString, 100, 0, 31),
        ];
    }
}
