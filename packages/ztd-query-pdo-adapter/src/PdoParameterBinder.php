<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use PDOStatement;

final class PdoParameterBinder
{
    /**
     * @param array<int|string, mixed>|null $params
     */
    public function execute(PDOStatement $statement, ?array $params): bool
    {
        if ($params === null) {
            return $statement->execute();
        }

        $position = 1;
        foreach ($params as $key => $value) {
            $parameter = is_int($key) ? $position++ : $this->parameterName($key);
            if (!$statement->bindValue($parameter, $value, PdoParameterType::fromValue($value))) {
                return false;
            }
        }

        return $statement->execute();
    }

    private function parameterName(string $parameter): string
    {
        if (str_starts_with($parameter, ':')) {
            return $parameter;
        }

        return sprintf(':%s', $parameter);
    }
}
