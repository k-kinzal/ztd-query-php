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
        self::assertSame("CREATE SERVER s FOREIGN DATA WRAPPER 'mysql' OPTIONS (OWNER 'o')", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("create server s foreign data wrapper 'mysql' options (owner 'o')")->toString());
    }

    public function testProblemReportsAnEmptyNameAndAllowsAnOrdinaryName(): void
    {
        $option = new \SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOption(\SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOptionKind::Host, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('h'));
        $wrapper = new \SqlSemantics\Statement\Identifier\Name('mysql');
        $empty = new CreateServer(new \SqlSemantics\Statement\Identifier\Name(''), $wrapper, [$option]);
        self::assertSame('', $empty->name->value);
        self::assertSame("Incorrect server name value: ''", $empty->problem()?->message());
        self::assertNull((new CreateServer(new \SqlSemantics\Statement\Identifier\Name('s'), $wrapper, [$option]))->problem());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE SERVER s FOREIGN DATA WRAPPER mysql OPTIONS (HOST 'h')");

        self::assertSame([], $operation->diagnostics());
        self::assertNull($operation->output());
    }
}
