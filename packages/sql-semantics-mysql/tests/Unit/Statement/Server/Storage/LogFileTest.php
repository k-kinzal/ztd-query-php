<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\LogFile;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(LogFile::class)]
#[Medium]
final class LogFileTest extends TestCase
{
    public function testRenderWritesKindAndFile(): void
    {
        self::assertSame("ALTER LOGFILE GROUP g ADD REDOFILE 'r'", (new Inspection('mysql-5.6.51'))->inspect("alter logfile group g add redofile 'r'")->toString());
    }
}
