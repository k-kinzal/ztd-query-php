<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\User\Credential;

#[CoversClass(Credential::class)]
#[Small]
final class CredentialTest extends TestCase
{
    public function testWrittenTellsWhichCredentialsCarryAString(): void
    {
        self::assertSame([false, true, false, true, true], array_map(static fn (Credential $credential): bool => $credential->written(), Credential::cases()));
    }
}
