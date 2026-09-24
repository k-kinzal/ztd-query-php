<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Administration\MasterKeyScope;

#[CoversClass(MasterKeyScope::class)]
#[Small]
final class MasterKeyScopeTest extends TestCase
{
    public function testCasesSpellTheirKeywords(): void
    {
        self::assertSame(['INNODB', 'BINLOG'], array_column(MasterKeyScope::cases(), 'value'));
    }
}
