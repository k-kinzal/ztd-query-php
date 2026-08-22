<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

interface ParameterBindingCompiler
{
    /**
     * @param array<int|string, mixed>|null $params
     * @return array{sql: string, params: array<int|string, mixed>|null}
     */
    public function compile(string $sql, ?array $params): array;
}
