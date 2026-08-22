<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

final class ZtdPdo
{
    private const DRIVER_MAP = [
        'mysql' => 'factory',
        'pgsql' => 'factory',
        'sqlite' => 'factory',
    ];

    public function platformClass(): string
    {
        return 'ZtdQuery\\Platform\\MySql\\MySqlSessionFactory';
    }
}

final class PdoStatement
{
    /** @param array<string, mixed> $metadata */
    public function nativeType(array $metadata): mixed
    {
        return $metadata['native_type'];
    }
}

final class DynamicDialectReference
{
    public function className(): string
    {
        return 'ZtdQuery\\Platform\\MySql\\MySqlSessionFactory';
    }
}
