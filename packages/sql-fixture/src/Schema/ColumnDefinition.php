<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

/**
 * One column, as a fixture is generated against it.
 *
 * Everything a generator needs in order to invent a value the server will
 * accept: what the column holds, how much of it, whether it may be absent, and
 * whether the server fills it in itself.
 */
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
        public readonly int|float|string|bool|null $default = null,
        public readonly bool $autoIncrement = false,
        public readonly bool $generated = false,
        public readonly ?array $enumValues = null,
    ) {
    }
}
