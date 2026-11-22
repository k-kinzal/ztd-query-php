<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Choice;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Invalid;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Testing\Sqlite\InspectedStatement;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Collate::class)]
#[Medium]
final class CollateTest extends TestCase
{
    public function testAcceptsAPrefixOperandWhichBindsTighter(): void
    {
        $statement = (new Inspection())->diagnose('SELECT -1')->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        $collate = new Collate($statement->columns[0]->expression, new Name('nocase'));
        self::assertSame($statement->columns[0]->expression, $collate->operand);
        self::assertSame('nocase', $collate->collation->value);
    }

    public function testDeriveScalarKeepsTheTypeAndNullFactAndLeavesTheResolutionToTheOperand(): void
    {
        $semantics = new Inspection();
        $create = $semantics->diagnose('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->diagnose('SELECT a COLLATE NOCASE FROM t', [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(Collate::class, $statement->columns[0]->expression);
        $collate = $statement->columns[0]->expression;
        self::assertInstanceOf(ColumnUse::class, $collate->operand);
        self::assertSame('NOCASE', $collate->collation->value);
        $fact = $operation->facts->scalar($collate);
        self::assertNull($fact->resolution);
        self::assertInstanceOf(ResolvedColumn::class, $operation->facts->scalar($collate->operand)->resolution);
        self::assertNull($operation->field(0)->resolution);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertSame($operation->facts->scalar($collate->operand)->type, $fact->type);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarCollatesAPrefixExpression(): void
    {
        $semantics = new Inspection();
        $create = $semantics->diagnose('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->diagnose('SELECT -a COLLATE nocase AS c1 FROM t', [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(Collate::class, $statement->columns[0]->expression);
        self::assertEquals(new Choice([Storage::Integer, Storage::Real]), $operation->field(0)->type);
        self::assertSame('SELECT - a COLLATE nocase AS c1 FROM t', $operation->toString());
    }

    public function testDeriveScalarReportsACollatedRowValue(): void
    {
        $operation = (new Inspection())->diagnose('SELECT (1, 2) COLLATE nocase', []);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        $fact = $operation->facts->scalar($statement->columns[0]->expression);
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertEquals(new Misuse(MisuseRule::TooManyValueColumns), $fact->type->cause);
        self::assertSame('row value misused', $operation->facts->diagnostics[0]->message());
        self::assertContains($fact->type->cause, $operation->facts->diagnostics);
    }

    public function testRenderWritesTheOperandAndTheCollationName(): void
    {
        $operation = (new Inspection())->diagnose('select a collate nocase AS c1, (a) collate "nocase" AS c2, b collate BINARY collate rtrim AS c3 from t');

        self::assertSame('SELECT a COLLATE nocase AS c1, (a) COLLATE nocase AS c2, b COLLATE BINARY COLLATE rtrim AS c3 FROM t', $operation->toString());
    }

    public function testRenderWritesANewlyBuiltCollation(): void
    {
        $semantics = new Inspection();
        $operation = new InspectedStatement($semantics->context(), new Select([new ResultColumn(new Collate(new IntegerLiteral('1'), new Name('NOCASE')))]));

        self::assertSame('SELECT 1 COLLATE NOCASE', $operation->toString());
    }
}
