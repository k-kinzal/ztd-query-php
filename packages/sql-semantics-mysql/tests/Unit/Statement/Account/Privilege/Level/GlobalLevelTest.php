<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Level;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(GlobalLevel::class)]
#[Medium]
final class GlobalLevelTest extends TestCase
{
    public function testDescribeNamesTheLevel(): void
    {
        self::assertSame('the global level', (new GlobalLevel())->describe());
    }

    public function testRenderWritesStarDotStar(): void
    {
        self::assertSame('REVOKE SELECT ON *.* FROM u', (new Inspection())->inspect('revoke select on * . * from u')->toString());
    }
}
