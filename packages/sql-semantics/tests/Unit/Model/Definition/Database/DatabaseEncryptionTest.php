<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Database\DatabaseEncryption;

#[CoversClass(DatabaseEncryption::class)]
#[Medium]
final class DatabaseEncryptionTest extends TestCase
{
    public function testCasesDefineOnlyTheApplicablePolicies(): void
    {
        self::assertSame([DatabaseEncryption::Enabled, DatabaseEncryption::Disabled], DatabaseEncryption::cases());
    }
}
