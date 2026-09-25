<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Retrieval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Retrieval\SelectIntoDumpfileStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SelectIntoDumpfileStatement::class)]
#[Medium]
final class SelectIntoDumpfileStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindsTheDumpFileOnEveryRelease(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a BLOB)'));
        $statement = $binder->bind("SELECT a INTO DUMPFILE '/tmp/a.bin' FROM t");
        self::assertInstanceOf(SelectIntoDumpfileStatement::class, $statement);
        self::assertSame("'/tmp/a.bin'", $statement->file->text);
        self::assertSame("SELECT `a` AS `a` FROM `t` INTO DUMPFILE '/tmp/a.bin'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWithQueryReplacesTheWrittenQuery(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SELECT 1 INTO DUMPFILE 'f'");
        $query = $binder->bind('SELECT 2');
        self::assertInstanceOf(SelectIntoDumpfileStatement::class, $statement);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame("SELECT 2 INTO DUMPFILE 'f'", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withQuery($query)));
        self::assertSame("SELECT 1 INTO DUMPFILE 'f'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithOriginKeepsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SELECT 1 INTO DUMPFILE 'f'");
        self::assertInstanceOf(SelectIntoDumpfileStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('other', $statement->source, Dialect::MySql));
        self::assertSame([$statement->query, $statement->file], [$copy->query, $copy->file]);
    }

    public function testRejectsANumericFileName(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $query);
        $file = Expression::literal(1, Dialect::MySql);
        self::assertInstanceOf(Literal::class, $file);
        $this->expectException(InvalidStructure::class);
        new SelectIntoDumpfileStatement(new Origin('s', $query->source, Dialect::MySql), $query, $file);
    }
}
