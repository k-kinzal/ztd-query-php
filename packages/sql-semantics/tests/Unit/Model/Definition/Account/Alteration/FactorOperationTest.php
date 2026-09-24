<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Alteration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Account\Alteration\FactorOperation;

#[CoversClass(FactorOperation::class)]
#[Medium]
final class FactorOperationTest extends TestCase
{
    public function testCasesAreSpelledAsTheirSqlKeywords(): void
    {
        self::assertSame(['ADD', 'MODIFY'], array_column(FactorOperation::cases(), 'value'));
    }
}
