<?php

declare(strict_types=1);

namespace MySqlMemory\Variable;

/**
 * One system variable of the server: its name, scope, values and default.
 *
 * @visibility MySqlMemory
 */
final class Definition
{
    /**
     * @param string $name The name, in lower case
     * @param Scope $scope Where the variable lives
     * @param Shape $shape The values it takes
     * @param string|int $default The global value the server starts with
     * @param bool $readOnly Whether no statement may set it
     * @param list<string> $members The names an enumeration or set takes, in upper case
     * @param int|null $minimum The lowest integer value, if bounded
     * @param int|null $maximum The highest integer value, if bounded
     * @param bool $nullable Whether it may be set to NULL
     */
    public function __construct(
        public readonly string $name,
        public readonly Scope $scope,
        public readonly Shape $shape,
        public readonly string|int $default,
        public readonly bool $readOnly = false,
        public readonly array $members = [],
        public readonly ?int $minimum = null,
        public readonly ?int $maximum = null,
        public readonly bool $nullable = false,
    ) {
    }
}
