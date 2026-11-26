<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Server\Storage\LogfileGroupRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(LogfileGroupRule::class)]
#[Medium]
final class LogfileGroupRuleTest extends TestCase
{
    public function testStatementLowersAlterOf5x(): void
    {
        self::assertSame("ALTER LOGFILE GROUP g ADD REDOFILE 'r' INITIAL_SIZE 1", (new Inspection('mysql-5.7.44'))->inspect("alter logfile group g add redofile 'r' initial_size 1")->toString());
    }

    public function testFileLowersAnUndoFile(): void
    {
        self::assertSame("CREATE LOGFILE GROUP g ADD UNDOFILE 'u'", (new Inspection())->inspect("create logfile group g add undofile 'u'")->toString());
    }
}
