<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\MySqlObject\LogfileGroups;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Storage\CompletionWait;
use SqlSemantics\Model\Definition\Storage\LogFileKind;
use SqlSemantics\Model\Statement\Definition\MySql\Storage as Statement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LogfileGroups::class)]
#[Medium]
final class LogfileGroupsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindBothFormsInEveryRelease(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $create = $binder->bind("CREATE LOGFILE GROUP lg ADD UNDOFILE 'u.log' INITIAL_SIZE 1K UNDO_BUFFER_SIZE 2K NODEGROUP 1 ENGINE NDB");
        $alter = $binder->bind("ALTER LOGFILE GROUP lg ADD UNDOFILE 'v.log' INITIAL_SIZE 4 NO_WAIT");
        self::assertInstanceOf(Statement\CreateLogfileGroupStatement::class, $create);
        self::assertInstanceOf(Statement\AlterLogfileGroupStatement::class, $alter);
        self::assertSame([LogFileKind::Undo, 'u.log', 1024, 2048, 1, 'NDB'], [$create->fileKind, $create->file, $create->options->initialSize, $create->options->undoBufferSize, $create->options->nodegroup, $create->options->engine]);
        self::assertSame(['v.log', 4, CompletionWait::NoWait], [$alter->file, $alter->initialSize, $alter->waiting]);
        self::assertSame($alter->toString(), $binder->bind($alter->toString())->toString());
    }

    public function testBindLegacyRedoFile(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("ALTER LOGFILE GROUP lg ADD REDOFILE 'r.log'");
        self::assertInstanceOf(Statement\AlterLogfileGroupStatement::class, $statement);
        self::assertSame(LogFileKind::Redo, $statement->fileKind);
    }

    public function testBindRejectsAnEmptyGroupName(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER LOGFILE GROUP `` ADD UNDOFILE 'u'");
    }
}
