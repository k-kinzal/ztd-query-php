<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\Conditional\SqliteCaseArm;
use SqlSemantics\Statement\Expression\SqliteInteger;
use SqlSemantics\Statement\Expression\SqliteText;
use SqlSemantics\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Literal\UnsignedInteger;

#[CoversClass(SqliteCaseArm::class)]
#[Small]
final class SqliteCaseArmTest extends TestCase
{
    public function testToStringKeepsTestsAndResultsInTheirOriginalRoles(): void
    {
        $one = new SqliteInteger(new UnsignedInteger('1'));
        $result = new SqliteText(new StringLiteral('matched'));
        $arm = new SqliteCaseArm($one, $result);
        self::assertSame($one, $arm->test);
        self::assertSame($result, $arm->result);
        self::assertSame("WHEN 1 THEN 'matched'", $arm->toString());
    }
}
