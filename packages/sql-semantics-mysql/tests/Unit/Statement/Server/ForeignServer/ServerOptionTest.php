<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\ForeignServer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOption;

#[CoversClass(ServerOption::class)]
#[Medium]
final class ServerOptionTest extends TestCase
{
    public function testRenderWritesKeywordAndValue(): void
    {
        self::assertSame("ALTER SERVER s OPTIONS (PORT 1, SOCKET '/s')", (new Semantics(Dialect::MySql))->analyze("alter server s options (port 1, socket '/s')")->toString());
    }
    public function testValueRejectsRadixStrings(): void
    {
        $this->expectExceptionMessage('A server option string is quoted text.');

        new ServerOption(\SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOptionKind::Socket, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('ab', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal));
    }
}
