<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin\Plugin;

use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallComponent;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\OnWord;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName;

/**
 * Prepares component assignment values before attempting to load their libraries.
 *
 * Values are resolved in written order, tested for NULL, then evaluated again for
 * their stored setting value. A failure preserves earlier
 * expression side effects, but component variable lookup waits for loading.
 * Observed through INSTALL COMPONENT on MySQL 8.0, 8.4 and 9.1.
 *
 * @visibility MySqlMemory
 */
final class Settings
{
    /**
     * Computes the SET list, refusing NULL and nonconstant expression roles.
     *
     * @throws SqlError When a value cannot be prepared or is NULL
     */
    public function prepare(MySqlStatement $operation, Session $session, Context $context, Connection $connection): void
    {
        $statement = $operation;
        assert($statement instanceof InstallComponent);
        $planner = new Planner($statement, $operation->statementInformation(), $session->settings(), $connection, $session->instance->dictionary, $session->temporaries, \MySqlMemory\Session\Statement\Catalog::snapshot($session));
        foreach ($statement->settings as $setting) {
            $value = $setting->value;
            if ($value instanceof OnWord) {
                continue;
            }
            if ($value instanceof BareName) {
                if ($value->qualifier !== null) {
                    throw AdministrationError::WrongTypeForVariable->error($setting->variable->name->value);
                }
                continue;
            }
            try {
                $compiled = $planner->compiler->compile($value, new Scope());
            } catch (SqlError $error) {
                throw $error->error === QueryError::InvalidGroupFunctionUse ? StatementError::SetConstantExpression->error() : $error;
            }
            $variable = $setting->variable;
            $name = ($variable->instance === null ? '' : $variable->instance->value . '.') . $variable->name->value;
            try {
                $result = $compiled->evaluate(new Frame($context));
            } catch (SqlError $error) {
                throw new SqlError($error->error, $error->getMessage(), $error, [...$error->following, [AdministrationError::ComponentSettingNull->value, AdministrationError::ComponentSettingNull->message($name)]]);
            }
            if ($result === null) {
                throw AdministrationError::ComponentSettingNull->error($name);
            }
            $compiled->evaluate(new Frame($context));
        }
    }
}
