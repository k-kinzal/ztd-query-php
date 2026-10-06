<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;

#[CoversNothing]
#[Small]
final class AlterOptionTest extends TestCase
{
    public function testDeriveOptionIsDeclaredByAlgorithmAndLock(): void
    {
        self::assertContains(AlterOption::class, class_implements(LockOption::class));
    }
}
