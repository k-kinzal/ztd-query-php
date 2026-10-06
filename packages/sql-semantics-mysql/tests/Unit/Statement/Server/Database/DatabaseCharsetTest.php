<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset;

#[CoversClass(DatabaseCharset::class)]
#[Medium]
final class DatabaseCharsetTest extends TestCase
{
    public function testRenderWritesCharacterSet(): void
    {
        self::assertSame('ALTER DATABASE d CHARACTER SET latin1', (new Semantics(Dialect::MySql))->analyze('alter database d charset = latin1')->toString());
    }
}
