<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Reference\ProposedColumn;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(ProposedColumn::class)]
#[Medium]
final class ProposedColumnTest extends TestCase
{
    #[TestWith(['mysql-5.7.44', 'VALUES(n)', 'VALUES (`n`)'])]
    #[TestWith(['mysql-8.0.44', 'VALUES(t.n) + 1', '(VALUES (`t`.`n`) + 1)'])]
    #[TestWith(['mysql-8.4.7', 'VALUES(n)', 'VALUES (`n`)'])]
    #[TestWith(['mysql-9.1.0', 'VALUES(n)', 'VALUES (`n`)'])]
    public function testInputsKeepTheNamedColumn(string $version, string $value, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (id INT PRIMARY KEY, n INT NOT NULL)'));
        $statement = $binder->bind('INSERT INTO t (id, n) VALUES (1, 2) ON DUPLICATE KEY UPDATE n = ' . $value);
        $sql = 'INSERT INTO `t`(`id`, `n`) VALUES (1, 2) ON DUPLICATE KEY UPDATE `n` = ' . $expected;
        self::assertSame($sql, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($sql, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($sql)));
    }

    public function testInputsResolveTheColumnAndItsType(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT NOT NULL)')))->bind('SELECT VALUES(n) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(ProposedColumn::class, $value);
        self::assertSame('n', $value->column->columnBinding()?->column->name);
        self::assertSame([$value->column], $value->inputs());
        self::assertSame('integer', $value->type->name);
        self::assertSame(Nullability::MaybeNull, $value->nullability);
    }

    public function testInputsRejectAnOperandThatIsNotAColumn(): void
    {
        $value = Expression::literal(1, Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new ProposedColumn($value->source, $value);
    }

    public function testSpellingNamesTheFunction(): void
    {
        $column = Expression::reference(['n'], Dialect::MySql);
        self::assertSame('VALUES', (new ProposedColumn($column->source, $column))->spelling());
    }

    public function testWithFactsPreservesTheColumn(): void
    {
        $column = Expression::reference(['n'], Dialect::MySql);
        $value = new ProposedColumn($column->source, $column);
        $copy = $value->withFacts($value->facts);
        self::assertNotSame($value, $copy);
        self::assertSame($column, $copy->column);
    }

    public function testWithFactsRejectsContradictoryFacts(): void
    {
        $column = Expression::reference(['n'], Dialect::MySql);
        $value = new ProposedColumn($column->source, $column);
        $this->expectException(InvalidStructure::class);
        $value->withFacts($column->facts);
    }
}
