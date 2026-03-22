<?php

declare(strict_types=1);

namespace Spec\Support;

use Closure;
use PDO;

final class TrackingPdo extends PDO
{
    public function __construct(
        private readonly Closure $execHandler,
    ) {
        parent::__construct('sqlite::memory:');
    }

    public function exec(string $statement): int|false
    {
        $result = ($this->execHandler)($statement);
        if (!is_int($result) && $result !== false) {
            throw new \LogicException('TrackingPdo exec handler must return int|false.');
        }

        return $result;
    }
}
