<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Storage\LogfileGroupOptions;
use SqlSemantics\Model\Definition\Storage\LogFileKind;
use SqlSemantics\Model\Statement\Definition\MySql\Storage\CreateLogfileGroupStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateLogfileGroupStatement::class)]
#[Medium]
final class CreateLogfileGroupStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-8.4.7'])]
    public function testWithOriginPreservesTheDefinition(string $version): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind("CREATE LOGFILE GROUP lg ADD UNDOFILE 'u.log' REDO_BUFFER_SIZE 1M COMMENT = 'c'");
        self::assertInstanceOf(CreateLogfileGroupStatement::class, $statement);
        self::assertSame("CREATE LOGFILE GROUP `lg` ADD UNDOFILE 'u.log' REDO_BUFFER_SIZE = 1048576 COMMENT = 'c' WAIT", $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithNameKeepsTheFile(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE LOGFILE GROUP lg ADD UNDOFILE 'u.log'");
        self::assertInstanceOf(CreateLogfileGroupStatement::class, $statement);
        self::assertSame(['lg2', 'u.log'], [$statement->withName('lg2')->name, $statement->withName('lg2')->file]);
    }

    public function testWithFileRejectsARedoFileInMySqlEight(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE LOGFILE GROUP lg ADD UNDOFILE 'u.log'");
        self::assertInstanceOf(CreateLogfileGroupStatement::class, $statement);
        self::assertSame('v.log', $statement->withFile(LogFileKind::Undo, 'v.log')->file);
        $this->expectException(InvalidStructure::class);
        $statement->withFile(LogFileKind::Redo, 'r.log');
    }

    public function testWithOptionsReplacesTheProperties(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE LOGFILE GROUP lg ADD UNDOFILE 'u.log'");
        self::assertInstanceOf(CreateLogfileGroupStatement::class, $statement);
        self::assertSame("CREATE LOGFILE GROUP `lg` ADD UNDOFILE 'u.log' NODEGROUP = 2 ENGINE = `NDB` WAIT", $statement->withOptions(new LogfileGroupOptions(nodegroup: 2, engine: 'NDB'))->toString());
    }
}
