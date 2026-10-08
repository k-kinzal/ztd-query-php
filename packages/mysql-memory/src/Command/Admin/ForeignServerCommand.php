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
 * when the server does not exist. Server names compare without regard to letter case and
 * accents; they are kept, and named in messages, to their first 64 bytes. A server that
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
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        $registry = $session->instance->registry;
        $session->transaction->commit();
        $name = $statement instanceof CreateServer || $statement instanceof AlterServer || $statement instanceof DropServer ? mb_strcut($statement->name->value, 0, 64, 'UTF-8') : '';
        if ($statement instanceof CreateServer) {
            if (isset($registry->servers[Registry::key($name)])) {
                throw AdministrationError::ForeignServerExists->error($name);
            }
            $registry->servers[Registry::key($name)] = new ForeignServer($name, $statement->wrapper->value, $this->options($statement->options));
        } elseif ($statement instanceof AlterServer) {
            $server = $registry->servers[Registry::key($name)] ?? throw AdministrationError::ForeignServerMissing->error($name);
            $server->options = [...$server->options, ...$this->options($statement->options)];
        } elseif ($statement instanceof DropServer) {
            $key = Registry::key($name);
            if (!isset($registry->servers[$key]) && !$statement->ifExists) {
                throw AdministrationError::ForeignServerMissing->error($name);
            }
            unset($registry->servers[$key]);
        }

        return new Completion(1);
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
