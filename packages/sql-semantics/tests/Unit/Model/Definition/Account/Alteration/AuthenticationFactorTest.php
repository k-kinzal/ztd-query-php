<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Alteration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationFactor;

#[CoversClass(AuthenticationFactor::class)]
#[Medium]
final class AuthenticationFactorTest extends TestCase
{
    public function testCasesAreSpelledAsTheirSqlKeywords(): void
    {
        self::assertSame(['2', '3'], array_column(AuthenticationFactor::cases(), 'value'));
    }
}
