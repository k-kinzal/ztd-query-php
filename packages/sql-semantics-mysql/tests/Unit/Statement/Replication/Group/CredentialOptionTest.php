<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Group;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption;

#[CoversClass(CredentialOption::class)]
#[Medium]
final class CredentialOptionTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        self::assertSame("START GROUP_REPLICATION DEFAULT_AUTH = 'a'", (new Semantics(Dialect::MySql))->analyze("start group_replication default_auth = 'a'")->toString());
    }
}
