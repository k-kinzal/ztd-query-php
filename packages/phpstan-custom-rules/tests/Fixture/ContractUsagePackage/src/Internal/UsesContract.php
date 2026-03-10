<?php

declare(strict_types=1);

namespace SqlFaker\Internal;

use SqlFaker\Contract\UsedRuntime;

use function SqlFaker\Contract\used_helper;

final class UsesContract
{
    public function __construct(
        private readonly UsedRuntime $runtime,
    ) {
    }

    public function run(): void
    {
        used_helper();
    }
}
