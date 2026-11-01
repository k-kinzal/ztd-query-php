<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\Encryption;

#[CoversClass(Encryption::class)]
#[Small]
final class EncryptionTest extends TestCase
{
    public function testFromOperandDistinguishesValidationFromTheConvertedSetting(): void
    {
        self::assertSame([Encryption::Enabled, Encryption::Disabled, Encryption::Enabled, Encryption::Enabled, Encryption::Disabled, null, null], array_map(Encryption::fromOperand(...), ['y', 'n', "N\0", "n\0ignored", 'N', '', 'yes']));
    }

    public function testTheDomainContainsOnlyTheConvertedEncryptionSettings(): void
    {
        self::assertSame(['Y', 'N'], array_column(Encryption::cases(), 'value'));
        self::assertSame([null, null, null], array_map(Encryption::tryFrom(...), ['', 'yes', 'Y ']));
    }
}
