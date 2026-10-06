<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\LogFile;

#[CoversClass(LogFile::class)]
#[Medium]
final class LogFileTest extends TestCase
{
    public function testRenderWritesKindAndFile(): void
    {
        self::assertSame("ALTER LOGFILE GROUP g ADD REDOFILE 'r'", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("alter logfile group g add redofile 'r'")->toString());
    }
}
