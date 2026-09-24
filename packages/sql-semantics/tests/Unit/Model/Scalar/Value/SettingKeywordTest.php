<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Value\SettingKeyword;

#[CoversClass(SettingKeyword::class)]
final class SettingKeywordTest extends TestCase
{
    public function testRepresentsEveryBareSettingKeyword(): void
    {
        self::assertSame(
            ['READ COMMITTED', 'READ UNCOMMITTED', 'REPEATABLE READ', 'SERIALIZABLE', 'READ ONLY', 'READ WRITE', 'DEFERRABLE', 'NOT DEFERRABLE', 'ON', 'OFF', 'NONE', 'ALL', 'LOCAL', 'DEFAULT', 'DOCUMENT', 'CONTENT'],
            array_column(SettingKeyword::cases(), 'value'),
        );
    }

    public function testResolvesAKeywordFromItsUppercaseWords(): void
    {
        self::assertSame(SettingKeyword::ReadWrite, SettingKeyword::from('READ WRITE'));
    }

    #[TestWith(['read write'])]
    #[TestWith(['COMMITTED'])]
    public function testLeavesOtherSpellingsUnclassified(string $spelling): void
    {
        self::assertNull(SettingKeyword::tryFrom($spelling));
    }
}
