<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Modifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\ValidationOption;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ValidationOption::class)]
#[Medium]
final class ValidationOptionTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        self::assertSame([], (new Inspection())->inspect('ALTER TABLE t WITH VALIDATION')->facts->diagnostics);
    }

    public function testRenderWritesWithOrWithout(): void
    {
        self::assertSame('ALTER TABLE t WITHOUT VALIDATION, ADD COLUMN a INT', (new Inspection('mysql-5.7.44'))->inspect('ALTER TABLE t WITHOUT VALIDATION, ADD a INT')->toString());
    }
}
