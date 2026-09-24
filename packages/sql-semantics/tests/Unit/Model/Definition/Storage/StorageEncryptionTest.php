<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Storage\StorageEncryption;

#[CoversClass(StorageEncryption::class)]
#[Medium]
final class StorageEncryptionTest extends TestCase
{
    public function testCasesSpellTheServerFlags(): void
    {
        self::assertSame(['Y', 'N'], array_map(static fn (StorageEncryption $encryption): string => $encryption->value, StorageEncryption::cases()));
    }
}
