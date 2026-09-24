<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Loading\ImportTableStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ImportTableStatement::class)]
#[Medium]
final class ImportTableStatementTest extends TestCase
{
    public function testWithOriginRetainsTheFilePatterns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("IMPORT TABLE FROM 'a.sdi', 'b*.sdi'");
        self::assertInstanceOf(ImportTableStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->files, $copy->files);
        self::assertSame("IMPORT TABLE FROM 'a.sdi', 'b*.sdi'", $copy->toString());
    }

    public function testWithFilesReplacesThePatternsImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("IMPORT TABLE FROM 'a.sdi'");
        $replacement = $binder->bind("IMPORT TABLE FROM 'c.sdi', 'd.sdi'");
        self::assertInstanceOf(ImportTableStatement::class, $statement);
        self::assertInstanceOf(ImportTableStatement::class, $replacement);
        $changed = $statement->withFiles($replacement->files);
        self::assertSame("IMPORT TABLE FROM 'c.sdi', 'd.sdi'", $changed->toString());
        self::assertSame("IMPORT TABLE FROM 'a.sdi'", $statement->toString());
    }

    public function testRejectsAnEmptyFileList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("IMPORT TABLE FROM 'a.sdi'");
        $this->expectException(InvalidStructure::class);
        new ImportTableStatement($statement->origin, []);
    }

    public function testRejectsANumericFileName(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("IMPORT TABLE FROM 'a.sdi'");
        $number = $binder->bind('DO 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Execution\DoExpressionsStatement::class, $number);
        $literal = $number->expressions[0];
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $literal);
        $this->expectException(InvalidStructure::class);
        new ImportTableStatement($statement->origin, [$literal]);
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("IMPORT TABLE FROM 'a.sdi'");
        self::assertInstanceOf(ImportTableStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ImportTableStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), $statement->files);
    }
}
