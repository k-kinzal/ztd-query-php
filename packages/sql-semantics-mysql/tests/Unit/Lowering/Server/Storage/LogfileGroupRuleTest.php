<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Server\Storage\LogfileGroupRule;

#[CoversClass(LogfileGroupRule::class)]
#[Medium]
final class LogfileGroupRuleTest extends TestCase
{
    public function testStatementLowersAlterOf5x(): void
    {
        self::assertSame("ALTER LOGFILE GROUP g ADD REDOFILE 'r' INITIAL_SIZE 1", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("alter logfile group g add redofile 'r' initial_size 1")->toString());
    }

    public function testFileLowersAnUndoFile(): void
    {
        self::assertSame("CREATE LOGFILE GROUP g ADD UNDOFILE 'u'", (new Semantics(Dialect::MySql))->analyze("create logfile group g add undofile 'u'")->toString());
    }
}
