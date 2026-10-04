<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\ForeignServer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\AlterServer;

#[CoversClass(AlterServer::class)]
#[Medium]
final class AlterServerTest extends TestCase
{
    public function testRenderWritesTheOptions(): void
    {
        self::assertSame("ALTER SERVER s OPTIONS (PASSWORD 'p')", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("alter server s options (password 'p')")->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("ALTER SERVER s OPTIONS (HOST 'h')");

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
