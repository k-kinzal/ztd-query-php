<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Printer;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Shape\Field;

#[CoversClass(Printer::class)]
#[Small]
final class PrinterTest extends TestCase
{
    public function testExpressionParenthesizesEachOperator(): void
    {
        $expression = new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new Arithmetic(ArithmeticOperator::Multiply, new NumberLiteral('2'), new NumberLiteral('3')));

        self::assertSame('(1 + (2 * 3))', (new Printer())->expression($expression));
    }

    public function testExpressionDropsTheParenthesesWrittenAroundAnOperand(): void
    {
        $expression = new Arithmetic(ArithmeticOperator::Minus, new Grouped(new NumberLiteral('1')), new NumberLiteral('2'));

        self::assertSame('(1 - 2)', (new Printer())->expression($expression));
    }

    public function testExpressionWritesAUnaryOperatorBeforeItsParenthesizedOperand(): void
    {
        self::assertSame('~(0)', (new Printer())->expression(new Unary(UnaryOperator::Invert, new NumberLiteral('0'))));
    }

    public function testExpressionQuotesAStringAndWritesNull(): void
    {
        $expression = new Arithmetic(ArithmeticOperator::Plus, new StringLiteral(["it's"]), new NullLiteral());

        self::assertSame("('it\\'s' + NULL)", (new Printer())->expression($expression));
    }

    public function testExpressionWritesAFunctionCallInLowerCase(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage("BIGINT UNSIGNED value is out of range in '(18446744073709551615 + abs(1))'");

        $session->query('SELECT 18446744073709551615 + ABS(1)');
    }

    public function testExpressionWritesACastInLowerCase(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage("BIGINT UNSIGNED value is out of range in '(cast(1 as unsigned) - 2)'");

        $session->query('SELECT CAST(1 AS UNSIGNED) - 2');
    }

    public function testExpressionWritesAnInvertedOperandInAMessage(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage("BIGINT UNSIGNED value is out of range in '(~(0) * 2)'");

        $session->query('SELECT ~0 * 2');
    }

    public function testExpressionPrintsTheColumnsTheServerReads(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE tt (a INT UNSIGNED); INSERT INTO tt VALUES (5)');

        $session->run('SELECT a - 9 FROM tt');
        self::assertSame([['Error', 1690, "BIGINT UNSIGNED value is out of range in '(`p`.`tt`.`a` - 9)'"]], $session->diagnostics->conditions);
        $session->run('SELECT a - 9 FROM (SELECT a + 0 AS a FROM tt x) d');
        self::assertSame([['Error', 1690, "BIGINT UNSIGNED value is out of range in '((`p`.`x`.`a` + 0) - 9)'"]], $session->diagnostics->conditions);
        $session->run('WITH c AS (SELECT DISTINCT a FROM tt) SELECT a - 9 FROM c z');
        self::assertSame([['Error', 1690, "BIGINT UNSIGNED value is out of range in '(`z`.`a` - 9)'"]], $session->diagnostics->conditions);
        $session->run('SELECT (SELECT tt.a - 9) FROM tt');
        self::assertSame([['Error', 1690, "BIGINT UNSIGNED value is out of range in '(`p`.`tt`.`a` - 9)'"]], $session->diagnostics->conditions);
    }

    public function testColumnPrintsTheColumnOfATableWithItsDatabaseAndCorrelationName(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE tt (a INT)');
        $operation = $session->analyze('SELECT x.A FROM tt x');
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(SelectExpression::class, $operation->statement->items[0]);
        self::assertInstanceOf(ColumnUse::class, $operation->statement->items[0]->expression);

        self::assertSame('`p`.`x`.`a`', (new Printer($operation->facts, 'p'))->column($operation->statement->items[0]->expression));
        self::assertSame('`x`.`A`', (new Printer())->column($operation->statement->items[0]->expression));
    }

    public function testFieldPrintsTheExpressionOfAnOutputField(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE tt (a INT)');
        $operation = $session->analyze('SELECT a + 0 AS b FROM tt');
        self::assertInstanceOf(Query::class, $operation->statement);
        $field = $operation->facts->query($operation->statement)->projection[0];
        self::assertInstanceOf(Field::class, $field);

        self::assertSame('(`p`.`tt`.`a` + 0)', (new Printer($operation->facts, 'p'))->field($field));
    }

    public function testResolvedPrintsAMergedDerivedColumnAsItsExpressionAndAMaterializedOneByItsAlias(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE tt (a INT)');
        $merged = $session->analyze('SELECT a FROM (SELECT a + 0 AS a FROM tt) d');
        $materialized = $session->analyze('SELECT a FROM (SELECT DISTINCT a FROM tt) d');
        self::assertInstanceOf(Select::class, $merged->statement);
        self::assertInstanceOf(Select::class, $materialized->statement);
        self::assertInstanceOf(SelectExpression::class, $merged->statement->items[0]);
        self::assertInstanceOf(SelectExpression::class, $materialized->statement->items[0]);
        $first = $merged->facts->scalar($merged->statement->items[0]->expression)->resolution;
        $second = $materialized->facts->scalar($materialized->statement->items[0]->expression)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $first);
        self::assertInstanceOf(ResolvedColumn::class, $second);

        self::assertSame('(`p`.`tt`.`a` + 0)', (new Printer($merged->facts, 'p'))->resolved($first));
        self::assertSame('`d`.`a`', (new Printer($materialized->facts, 'p'))->resolved($second));
    }

    public function testTableColumnPrintsTheDatabaseTheCorrelationNameAndTheDeclaredName(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE tt (a INT)');
        $operation = $session->analyze('SELECT x.A FROM tt x');
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(SelectExpression::class, $operation->statement->items[0]);
        $resolution = $operation->facts->scalar($operation->statement->items[0]->expression)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $relation = $resolution->relation;
        self::assertInstanceOf(NamedRelation::class, $relation);
        $table = $operation->facts->relation($relation)->table;
        self::assertInstanceOf(DeclaredTable::class, $table);

        self::assertSame('`p`.`x`.`a`', (new Printer($operation->facts, 'p'))->tableColumn($resolution, $table, $relation));
        self::assertSame('`p`.`x`.`a`', (new Printer($operation->facts))->tableColumn($resolution, $table, $relation));
    }

    public function testMergedPrintsTheFieldOfAMergedDerivedTableAndNothingForAMaterializedOne(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE tt (a INT)');
        $merged = $session->analyze('SELECT a FROM (SELECT a + 0 AS a FROM tt) d');
        $materialized = $session->analyze('SELECT a FROM (SELECT DISTINCT a FROM tt) d');
        self::assertInstanceOf(Select::class, $merged->statement);
        self::assertInstanceOf(Select::class, $materialized->statement);
        self::assertInstanceOf(SelectExpression::class, $merged->statement->items[0]);
        self::assertInstanceOf(SelectExpression::class, $materialized->statement->items[0]);
        $first = $merged->facts->scalar($merged->statement->items[0]->expression)->resolution;
        $second = $materialized->facts->scalar($materialized->statement->items[0]->expression)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $first);
        self::assertInstanceOf(ResolvedColumn::class, $second);

        self::assertSame('(`p`.`tt`.`a` + 0)', (new Printer($merged->facts, 'p'))->merged($first, null));
        self::assertNull((new Printer($materialized->facts, 'p'))->merged($second, null));
        self::assertNull((new Printer())->merged($first, null));
    }

    public function testMaterializedPrintsTheCorrelationNameAndTheColumnName(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE tt (a INT)');
        $operation = $session->analyze('SELECT a FROM (SELECT DISTINCT a FROM tt) d');
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(SelectExpression::class, $operation->statement->items[0]);
        $resolution = $operation->facts->scalar($operation->statement->items[0]->expression)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);

        self::assertSame('`d`.`a`', (new Printer())->materialized($resolution));
    }

    public function testPositionAnswersThePlaceOfAColumnInItsRelation(): void
    {
        $session = (new Instance('8.4.7', [], ['p']))->connect('root', 'localhost', 'p');
        $session->query('CREATE TABLE tt (a INT)');
        $operation = $session->analyze('SELECT b FROM (SELECT a, a + 1 AS b FROM tt) d');
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(SelectExpression::class, $operation->statement->items[0]);
        $resolution = $operation->facts->scalar($operation->statement->items[0]->expression)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);

        self::assertSame(1, (new Printer($operation->facts))->position($resolution));
        self::assertSame(-1, (new Printer())->position($resolution));
    }

    public function testExpressionWritesATemporalLiteralWithItsKeyword(): void
    {
        self::assertSame("DATE'2020-01-01'", (new Printer())->expression(new \SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Date, '2020-01-01')));
    }

    public function testRadixWritesTheBytesOfAHexadecimalOrBitLiteral(): void
    {
        $printer = new Printer();

        self::assertSame(['0x0102', "X''", '0x05', "_latin1'A'"], [$printer->radix(new RadixLiteral(Radix::Hexadecimal, '102')), $printer->radix(new RadixLiteral(Radix::Hexadecimal, '')), $printer->expression(new RadixLiteral(Radix::Bit, '101')), $printer->radix(new RadixLiteral(Radix::Hexadecimal, '41', new Name('latin1')))]);
    }
}
