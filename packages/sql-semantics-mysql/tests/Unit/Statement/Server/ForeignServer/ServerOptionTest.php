<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\ForeignServer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOption;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ServerOption::class)]
#[Medium]
final class ServerOptionTest extends TestCase
{
    public function testRenderWritesKeywordAndValue(): void
    {
        self::assertSame("ALTER SERVER s OPTIONS (PORT 1, SOCKET '/s')", (new Inspection())->inspect("alter server s options (port 1, socket '/s')")->toString());
    }
}
