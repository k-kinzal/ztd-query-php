<?php

declare(strict_types=1);

namespace SqlFaker\Internal;

use SqlFaker\Contract\UsedGrammar;
use SqlFaker\Contract\UsedRuntime;

use function SqlFaker\Contract\used_helper;

use const SqlFaker\Contract\USED_LIMIT;

final class UsesContract
{
    public function __construct(
        private readonly UsedRuntime $runtime,
    ) {
    }

    public function grammar(): UsedGrammar
    {
        return new UsedGrammar();
    }

    public function run(): void
    {
        if (USED_LIMIT < 0) {
            return;
        }

        used_helper();
    }
}
