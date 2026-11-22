<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCaseArm;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;

#[CoversClass(IntegerCaseArm::class)]
#[Small]
final class IntegerCaseArmTest extends TestCase
{
    public function testKeepsConditionAndResultInSeparateRoles(): void
    {
        $condition = new NullLiteral();
        $result = new IntegerLiteral('1');
        $arm = new IntegerCaseArm($condition, $result);

        self::assertSame($condition, $arm->condition);
        self::assertSame($result, $arm->result);
    }
}
