<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Storage\CompletionWait;
use SqlSemantics\Model\Definition\Storage\LogFileKind;
use SqlSemantics\Model\Statement\Definition\MySql\Storage\AlterLogfileGroupStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterLogfileGroupStatement::class)]
#[Medium]
final class AlterLogfileGroupStatementTest extends TestCase
{
    #[TestWith(['mysql-5.7.44', 'REDOFILE'])]
    #[TestWith(['mysql-8.4.7', 'UNDOFILE'])]
    public function testWithOriginPreservesTheAddedFile(string $version, string $kind): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind("ALTER LOGFILE GROUP lg ADD $kind 'f.log' NO_WAIT");
        self::assertInstanceOf(AlterLogfileGroupStatement::class, $statement);
        self::assertSame("ALTER LOGFILE GROUP `lg` ADD $kind 'f.log' NO_WAIT", $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithNameKeepsTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER LOGFILE GROUP lg ADD UNDOFILE 'f.log'");
        self::assertInstanceOf(AlterLogfileGroupStatement::class, $statement);
        self::assertSame(['lg2', 'lg'], [$statement->withName('lg2')->name, $statement->name]);
    }

    public function testWithFileRejectsARedoFileInMySqlEight(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER LOGFILE GROUP lg ADD UNDOFILE 'f.log'");
        self::assertInstanceOf(AlterLogfileGroupStatement::class, $statement);
        self::assertSame('g.log', $statement->withFile(LogFileKind::Undo, 'g.log')->file);
        $this->expectException(InvalidStructure::class);
        $statement->withFile(LogFileKind::Redo, 'r.log');
    }

    public function testWithInitialSizeRejectsANegativeSize(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER LOGFILE GROUP lg ADD UNDOFILE 'f.log'");
        self::assertInstanceOf(AlterLogfileGroupStatement::class, $statement);
        self::assertSame(4096, $statement->withInitialSize(4096)->initialSize);
        $this->expectException(InvalidStructure::class);
        $statement->withInitialSize(-1);
    }

    public function testWithEngineSelectsTheEngine(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER LOGFILE GROUP lg ADD UNDOFILE 'f.log'");
        self::assertInstanceOf(AlterLogfileGroupStatement::class, $statement);
        self::assertSame("ALTER LOGFILE GROUP `lg` ADD UNDOFILE 'f.log' ENGINE = `NDB` WAIT", $statement->withEngine('NDB')->toString());
    }

    public function testWithWaitingReplacesTheCompletionRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER LOGFILE GROUP lg ADD UNDOFILE 'f.log'");
        self::assertInstanceOf(AlterLogfileGroupStatement::class, $statement);
        self::assertSame(CompletionWait::NoWait, $statement->withWaiting(CompletionWait::NoWait)->waiting);
    }
}
