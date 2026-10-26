<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Registry\ForeignServer;
use MySqlMemory\Registry\Registry;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\AlterServer;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\DropServer;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOption;
use SqlSemantics\Statement\Operation;

/**
 * Executes CREATE SERVER, ALTER SERVER and DROP SERVER.
 *
 * Each commits the open transaction and reports one affected row, DROP SERVER IF EXISTS even
 * when the server does not exist from MySQL 5.7 onward; MySQL 5.6 reports zero
 * for that absent conditional drop. Server names compare without regard to letter case and
 * accents. Storage keeps 64 characters; missing-server diagnostics display 64 bytes.
 * Creation keeps the original lookup name until ALTER SERVER refreshes names from storage.
 * Duplicate creation reports the original operand, bounded by the diagnostic buffer. A server that
 * exists is ER_FOREIGN_SERVER_EXISTS; one that does not is ER_FOREIGN_SERVER_DOESNT_EXIST, whose
 * message names it after two spaces (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-server.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-server.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-server.html.
 *
 * @visibility MySqlMemory
 */
final class ForeignServerCommand implements Command
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
     * Creates, changes or drops the server.
     */
    #[Override]
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof Operation);
        $statement = $operation->statement;
        $registry = $session->instance->registry;
        $affected = 1;
        $session->transaction->commit();
        $operand = $statement instanceof CreateServer || $statement instanceof AlterServer || $statement instanceof DropServer ? $statement->name->value : '';
        $original = preg_replace('/[\xF0-\xF4][\x80-\xBF]{3}/', '?', $operand) ?? $operand;
        $name = rtrim(mb_substr($original, 0, 64, 'UTF-8'), ' ');
        $cached = array_key_first(array_filter($registry->servers, static fn (ForeignServer $server): bool => $server->lookupName !== null && Registry::key($session->settings()->legacy() ? $server->lookupName : rtrim($server->lookupName, ' ')) === Registry::key($session->settings()->legacy() ? $original : rtrim($original, ' '))));
        $text = new \MySqlMemory\Error\DiagnosticText();
        $missing = $text->bytes(explode("\0", $original, 2)[0], 64, $session->settings()->legacy());
        if ($statement instanceof CreateServer) {
            if (isset($registry->servers[Registry::key($name)])) {
                throw new \MySqlMemory\Error\SqlError(AdministrationError::ForeignServerExists, $text->bytes(AdministrationError::ForeignServerExists->error(explode("\0", $original, 2)[0])->getMessage(), 511, $session->settings()->legacy()));
            }
            $registry->servers[Registry::key($name)] = new ForeignServer($original, (new Foreign\Fields())->wrapper($statement->wrapper->value, $session), (new Foreign\Fields())->options($this->options($statement->options), $session), $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651, (new Foreign\Fields())->cached($this->options($statement->options), $session));
        } elseif ($statement instanceof AlterServer) {
            $server = $cached === null ? throw AdministrationError::ForeignServerMissing->error($missing) : $registry->servers[$cached];
            $server->options = [...$server->options, ...(new Foreign\Fields())->options((new Foreign\Fields())->changed($this->options($statement->options), $server->cachedOptions, $session), $session)];
            foreach ($registry->servers as $stored) {
                $stored->cachedOptions = $stored->options;
                $stored->lookupName = str_contains($stored->name, "\0") ? null : $stored->name;
            }
        } elseif ($statement instanceof DropServer) {
            $key = Registry::key($name);
            if ($cached === null && !$statement->ifExists) {
                throw AdministrationError::ForeignServerMissing->error($missing);
            }
            $affected = $cached === null && $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651 ? 0 : 1;
            if ($affected !== 0) {
                unset($registry->servers[$key]);
            }
        }

        return new Completion($affected);
    }

    /**
     * Answers the value of each option, by lower-case option name; a later option overrides an earlier one.
     *
     * @param list<ServerOption> $options
     * @return array<string, string|int>
     */
    public function options(array $options): array
    {
        $values = [];
        foreach ($options as $option) {
            $values[strtolower($option->kind->value)] = $option->value instanceof Numeral ? (int) (new Literals())->number($option->value) : (new Literals())->bytes($option->value);
        }

        return $values;
    }
}
