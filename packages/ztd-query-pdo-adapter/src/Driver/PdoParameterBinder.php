<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo\Driver;

use PDOStatement;

/**
 * Binds a caller's parameters to a statement and runs it.
 *
 * ZTD rewrites a statement's SQL between prepare and execute, so the array a
 * caller passed to execute() cannot simply be handed on: each value has to be
 * bound to the rewritten statement under the placeholder it belongs to, and
 * under the kind PDO would have read it as.
 */
final class PdoParameterBinder
{
    /**
     * Binds every parameter to the statement and runs it.
     *
     * Positional parameters are numbered from one in the order they were
     * written, which is how PDO numbers them.
     *
     * @param PDOStatement $statement Statement to bind against and run
     * @param array<int|string, mixed>|null $params Parameters as the caller passed them, or null to run what is already bound
     *
     * @return bool Whether the statement ran
     */
    public function execute(PDOStatement $statement, ?array $params): bool
    {
        if ($params === null) {
            return $statement->execute();
        }

        $position = 1;
        foreach ($params as $key => $value) {
            $parameter = is_int($key) ? $position++ : $this->parameterName($key);
            if (!$statement->bindValue($parameter, $value, PdoParameterKind::fromValue($value))) {
                return false;
            }
        }

        return $statement->execute();
    }

    /**
     * Answers a named parameter as the placeholder it stands for.
     *
     * A caller may write the name with or without the colon PDO's placeholders
     * carry; both name the same placeholder.
     *
     * @param string $parameter Name as the caller wrote it
     *
     * @return string The same name, written as a placeholder
     */
    public function parameterName(string $parameter): string
    {
        if (str_starts_with($parameter, ':')) {
            return $parameter;
        }

        return sprintf(':%s', $parameter);
    }
}
