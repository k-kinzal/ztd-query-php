<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Group;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential;

#[CoversClass(Credential::class)]
#[Small]
final class CredentialTest extends TestCase
{
    public function testCasesSpellEveryOption(): void
    {
        self::assertSame(['USER', 'PASSWORD', 'DEFAULT_AUTH'], array_column(Credential::cases(), 'value'));
    }
}
