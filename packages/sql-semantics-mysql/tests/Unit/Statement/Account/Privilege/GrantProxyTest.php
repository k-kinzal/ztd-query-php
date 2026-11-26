<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantProxy;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(GrantProxy::class)]
#[Medium]
final class GrantProxyTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Inspection())->inspect('GRANT PROXY ON a TO b')->facts->diagnostics);
    }

    public function testRenderWritesTheGrantOption(): void
    {
        self::assertSame("GRANT PROXY ON a TO b IDENTIFIED BY 'x' WITH GRANT OPTION", (new Inspection('mysql-5.6.51'))->inspect("grant proxy on a to b identified by 'x' with grant option")->toString());
    }
}
