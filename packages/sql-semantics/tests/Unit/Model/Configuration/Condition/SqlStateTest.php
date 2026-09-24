<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Condition\SqlState;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(SqlState::class)]
final class SqlStateTest extends TestCase
{
    public function testConditionClassReturnsTheFirstTwoCharacters(): void
    {
        self::assertSame('HY', (new SqlState('HY000'))->conditionClass());
    }

    #[TestWith(['00000'])]
    #[TestWith(['4500'])]
    #[TestWith(['45a00'])]
    #[TestWith(['450000'])]
    public function testRejectsCodesTheServerCannotSignal(string $code): void
    {
        $this->expectException(InvalidStructure::class);
        new SqlState($code);
    }
}
