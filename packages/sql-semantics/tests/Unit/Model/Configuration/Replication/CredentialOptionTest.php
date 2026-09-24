<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Replication\CredentialOption;

#[CoversClass(CredentialOption::class)]
#[Small]
final class CredentialOptionTest extends TestCase
{
    public function testCasesFollowTheStartReplicaOrder(): void
    {
        self::assertSame(['USER', 'PASSWORD', 'DEFAULT_AUTH', 'PLUGIN_DIR'], array_column(CredentialOption::cases(), 'value'));
    }
}
