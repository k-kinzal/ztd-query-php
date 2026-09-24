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
use SqlSemantics\Model\Statement\Loading\FieldLayout;
use SqlSemantics\Model\Statement\Loading\LineLayout;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Retrieval\SelectIntoOutfileStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SelectIntoOutfileStatement::class)]
#[Medium]
final class SelectIntoOutfileStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindsTheFileCharacterSetAndSeparatorsOnEveryRelease(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind("SELECT a FROM t INTO OUTFILE '/tmp/a.csv' CHARACTER SET latin1 FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"' LINES STARTING BY '>' TERMINATED BY ';'");
        self::assertInstanceOf(SelectIntoOutfileStatement::class, $statement);
        self::assertSame(["'/tmp/a.csv'", 'latin1', "','", true, "'>'", "';'"], [$statement->file->text, $statement->characterSet, $statement->fields->terminator?->text, $statement->fields->optionallyEnclosed, $statement->lines->start?->text, $statement->lines->terminator?->text]);
        self::assertSame("SELECT `a` AS `a` FROM `t` INTO OUTFILE '/tmp/a.csv' CHARACTER SET `latin1` FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"' LINES STARTING BY '>' TERMINATED BY ';'", $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWithLayoutReplacesTheSeparators(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("SELECT a FROM t INTO OUTFILE 'f'");
        self::assertInstanceOf(SelectIntoOutfileStatement::class, $statement);
        $comma = Expression::literal(',', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $comma);
        $changed = $statement->withLayout(new FieldLayout($comma), new LineLayout());
        self::assertSame("SELECT `a` AS `a` FROM `t` INTO OUTFILE 'f' FIELDS TERMINATED BY ','", $changed->toString());
        self::assertNull($statement->fields->terminator);
    }

    public function testWithQueryReplacesTheWrittenQuery(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SELECT 1 INTO OUTFILE 'f'");
        $query = $binder->bind('SELECT 2');
        self::assertInstanceOf(SelectIntoOutfileStatement::class, $statement);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame("SELECT 2 INTO OUTFILE 'f'", $statement->withQuery($query)->toString());
        self::assertSame("SELECT 1 INTO OUTFILE 'f'", $statement->toString());
    }

    public function testWithOriginKeepsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SELECT 1 INTO OUTFILE 'f'");
        self::assertInstanceOf(SelectIntoOutfileStatement::class, $statement);
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
        new SelectIntoOutfileStatement(new Origin('s', $query->source, Dialect::MySql), $query, $file);
    }
}
