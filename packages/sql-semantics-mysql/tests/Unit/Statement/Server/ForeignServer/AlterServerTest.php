<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\ForeignServer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\AlterServer;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AlterServer::class)]
#[Medium]
final class AlterServerTest extends TestCase
{
    public function testRenderWritesTheOptions(): void
    {
        self::assertSame("ALTER SERVER s OPTIONS (PASSWORD 'p')", (new Inspection('mysql-5.7.44'))->inspect("alter server s options (password 'p')")->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection())->inspect("ALTER SERVER s OPTIONS (HOST 'h')");

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
