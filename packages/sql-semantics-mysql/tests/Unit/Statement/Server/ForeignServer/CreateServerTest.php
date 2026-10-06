<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\ForeignServer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer;

#[CoversClass(CreateServer::class)]
#[Medium]
final class CreateServerTest extends TestCase
{
    public function testRenderWritesNameWrapperAndOptions(): void
    {
        self::assertSame("CREATE SERVER s FOREIGN DATA WRAPPER mysql OPTIONS (OWNER 'o')", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("create server s foreign data wrapper 'mysql' options (owner 'o')")->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE SERVER s FOREIGN DATA WRAPPER mysql OPTIONS (HOST 'h')");

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
