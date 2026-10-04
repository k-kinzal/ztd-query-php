<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Modifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\ValidationOption;

#[CoversClass(ValidationOption::class)]
#[Medium]
final class ValidationOptionTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t WITH VALIDATION')->facts->diagnostics);
    }

    public function testRenderWritesWithOrWithout(): void
    {
        self::assertSame('ALTER TABLE t WITHOUT VALIDATION, ADD COLUMN a INT', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t WITHOUT VALIDATION, ADD a INT')->toString());
    }
}
