<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

final class ColumnDefinition
{
    /**
     * @param list<string>|null $enumValues ENUM/SET values
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?int $length = null,
        public readonly ?int $precision = null,
        public readonly ?int $scale = null,
        public readonly bool $nullable = true,
        public readonly bool $unsigned = false,
        public readonly mixed $default = null,
        public readonly bool $autoIncrement = false,
        public readonly bool $generated = false,
        public readonly ?array $enumValues = null,
    ) {
    }
}
