<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Spelling\Layout;
use SqlSemantics\Statement\Spelling\Spelled;

#[CoversClass(SelectExpression::class)]
#[Medium]
final class SelectExpressionTest extends TestCase
{
    public function testRenderWritesTheExpressionAndTheAlias(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('select a total from t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(SelectExpression::class, $operation->statement->items[0]);
        self::assertInstanceOf(ColumnUse::class, $operation->statement->items[0]->expression);
        self::assertSame('a', $operation->statement->items[0]->expression->name->value);
        self::assertSame('total', $operation->statement->items[0]->alias?->value);
        self::assertSame('SELECT a total FROM t', $operation->toString());
    }

    public function testRenderOmitsTheAliasWhenNoneWasWritten(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a, 1 FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(SelectExpression::class, $operation->statement->items[0]);
        self::assertInstanceOf(SelectExpression::class, $operation->statement->items[1]);
        self::assertNull($operation->statement->items[0]->alias);
        self::assertNull($operation->statement->items[1]->alias);
        self::assertSame('SELECT a, 1 FROM t', $operation->toString());
    }

    public function testRenderQuotesAnAliasThatIsAReservedWord(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a AS `select` FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame('select', $operation->statement->items[0]->alias?->value);
        self::assertSame('SELECT a AS `select` FROM t', $operation->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        yield 'MySQL 5.6' => ['mysql-5.6.51'];
        yield 'MySQL 5.7' => ['mysql-5.7.44'];
        yield 'MySQL 8.0' => ['mysql-8.0.44'];
        yield 'MySQL 8.4' => ['mysql-8.4.7'];
        yield 'MySQL 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerReleases')]
    public function testRenderKeepsTheTextTheServerNamesAnUnaliasedItemAfter(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('SELECT 1+1, 5 MOD 2, 1 && 1, CONVERT(1, CHAR), ROW(1, 2) = (1, 2), 0x0a, x\'0a\', NOW( ), CURRENT_DATE, 1 /* c */ + 2', []);

        self::assertSame(
            ['1+1', '5 MOD 2', '1 && 1', 'CONVERT(1, CHAR)', 'ROW(1, 2) = (1, 2)', '0x0a', "x'0a'", 'NOW( )', 'CURRENT_DATE', '1 /* c */ + 2'],
            array_map(static fn (Field $field): ?string => $field->name?->value, $operation->fields()->items ?? []),
        );
        self::assertSame("SELECT 1+1, 5 MOD 2, 1 && 1, CONVERT(1, CHAR), ROW(1, 2) = (1, 2), 0x0a, x'0a', NOW( ), CURRENT_DATE, 1 /* c */ + 2", $operation->toString());
    }

    #[DataProvider('providerReleases')]
    public function testDeriveNamesTheColumnsOfADerivedTableAfterTheirText(string $release): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $missing = $semantics->analyze('SELECT x FROM (SELECT 1+1) AS d', []);
        $found = $semantics->analyze('SELECT `1+1` FROM (SELECT 1+1) AS d', []);

        self::assertInstanceOf(MissingColumn::class, $missing->field('x')->resolution);
        self::assertInstanceOf(ResolvedColumn::class, $found->field('1+1')->resolution);
        self::assertSame('SELECT `1+1` FROM (SELECT 1+1) AS d', $found->toString());
        self::assertSame('SELECT `1+1` FROM (SELECT 1+1) AS d', $semantics->analyze($found->toString(), [])->toString());
    }

    #[DataProvider('providerReleases')]
    public function testDeriveNamesSelfNamedItemsByTheirOwnNames(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze("SELECT (1), +a, null, '  x' 'y', ? FROM t");

        self::assertSame(['1', 'a', 'NULL', 'x', '?'], array_map(static fn (Field $field): ?string => $field->name?->value, $operation->fields()->items ?? []));
    }

    public function testDeriveNamesBooleansByTheirWordsIn5xAndByTheirTextLater(): void
    {
        self::assertSame('TRUE', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT true')->field(0)->name?->value);
        self::assertSame('true', (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('SELECT true')->field(0)->name?->value);
    }

    public function testDeriveNamesTheColumnsOfACommonTableAndOfAUnionAfterTheFirstOperand(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertInstanceOf(ResolvedColumn::class, $semantics->analyze('WITH c AS (SELECT 1+1) SELECT `1+1` FROM c', [])->field(0)->resolution);
        self::assertSame(['1+1'], array_map(static fn (Field $field): ?string => $field->name?->value, $semantics->analyze('SELECT * FROM (SELECT 1+1 UNION SELECT 2) AS d', [])->fields()->items ?? []));
    }

    public function testDeriveNamesTheColumnsOfACreatedTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $create = $semantics->analyze("CREATE TABLE t2 AS SELECT 1+1, 'b'", []);

        self::assertSame(['1+1', 'b'], array_map(static fn (Field $field): ?string => $field->name?->value, $semantics->analyze('SELECT * FROM t2', [$create])->fields()->items ?? []));
    }

    public function testAnAliasedItemWithALayoutIsRejected(): void
    {
        $this->expectExceptionMessage('An aliased select item is named by its alias and keeps no layout.');

        new SelectExpression(new NumberLiteral('1'), new Name('x'), new Layout([new Spelled('', '1')]));
    }

    public function testALayoutWithTrailingTriviaIsRejected(): void
    {
        $this->expectExceptionMessage('MySQL names a select item without the trivia after its expression, so its layout has no trailing trivia.');

        new SelectExpression(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('1')), null, new Layout([new Spelled('', '1'), new Spelled('', '+'), new Spelled('', '1')], ' /* c */'));
    }

    public function testDeriveNamesAnItemWithoutTheTriviaAfterIt(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT 1+1 /* c */, 2 -- d\n, 1 /*e*/ + 1 /*f*/ FROM dual");

        self::assertSame(['1+1', '2', '1 /*e*/ + 1'], [$operation->field(0)->name?->value, $operation->field(1)->name?->value, $operation->field(2)->name?->value]);
    }

    public function testAnEqualsSignBeforeASelectAliasIsRejected(): void
    {
        $this->expectExceptionMessage('A select alias is written with or without AS, and an item without alias has no alias mark.');

        new SelectExpression(new NumberLiteral('1'), new Name('x'), null, AliasMark::Equals);
    }

    public function testRenderWritesAnItemBuiltWithoutLayoutCanonically(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = new Operation($semantics->context(), new Select([], [new SelectExpression(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('1')))]));

        self::assertSame('SELECT 1 + 1', $operation->toString());
        self::assertSame('1 + 1', $operation->field(0)->name?->value);
    }
}
