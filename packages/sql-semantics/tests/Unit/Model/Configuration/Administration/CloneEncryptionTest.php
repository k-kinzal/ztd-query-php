<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Administration\CloneEncryption;

#[CoversClass(CloneEncryption::class)]
#[Small]
final class CloneEncryptionTest extends TestCase
{
    public function testCasesSpellTheirClauses(): void
    {
        self::assertSame(['REQUIRE SSL', 'REQUIRE NO SSL'], array_column(CloneEncryption::cases(), 'value'));
    }
}
