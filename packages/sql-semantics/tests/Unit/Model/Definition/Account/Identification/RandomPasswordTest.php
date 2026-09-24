<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Identification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;

#[CoversClass(RandomPassword::class)]
#[Medium]
final class RandomPasswordTest extends TestCase
{
    public function testCasesAreSpelledAsTheirSqlKeywords(): void
    {
        self::assertSame(['RANDOM PASSWORD'], array_column(RandomPassword::cases(), 'value'));
    }
}
