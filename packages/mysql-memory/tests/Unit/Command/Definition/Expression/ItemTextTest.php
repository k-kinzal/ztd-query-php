<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Expression;

use MySqlMemory\Command\Definition\Expression\ItemText;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ItemText::class)]
#[Small]
final class ItemTextTest extends TestCase
{
    public function testTextWritesOperationsInParenthesesAndColumnsByTheirDeclaredName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (Abc INT, b VARCHAR(10), x INT AS (abc + 1 - 2 * 3), y VARCHAR(20) AS (concat(b, \'q\')), z INT AS (-abc))');

        $shown1 = $session->query('SHOW CREATE TABLE t')[0];

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $shown1);

        self::assertSame(
            ["CREATE TABLE `t` (\n  `Abc` int DEFAULT NULL,\n  `b` varchar(10) DEFAULT NULL,\n  `x` int GENERATED ALWAYS AS (((`Abc` + 1) - (2 * 3))) VIRTUAL,\n  `y` varchar(20) GENERATED ALWAYS AS (concat(`b`,_utf8mb4'q')) VIRTUAL,\n  `z` int GENERATED ALWAYS AS (-(`Abc`)) VIRTUAL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"],
            [$shown1->rows[0][1]],
        );
    }

    public function testNegatedWritesTheOppositeComparisonAndDeMorgansLaws(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, x INT AS (NOT (a > 1)), y INT AS (NOT (a AND 1)), z INT AS (NOT a))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(['(`a` <= 1)', '((0 = `a`) or (0 = 1))', '(0 = `a`)'], [$table->definition->columns[1]->expression, $table->definition->columns[2]->expression, $table->definition->columns[3]->expression]);
    }

    public function testLogicalComparesAnOperandThatIsNoConditionWithZero(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, x INT AS (a XOR 1), y INT AS (a = 1 OR a > 2))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(['((0 <> `a`) xor (0 <> 1))', '((`a` = 1) or (`a` > 2))'], [$table->definition->columns[1]->expression, $table->definition->columns[2]->expression]);
    }

    public function testLiteralWritesHexadecimalAndBitLiteralsAsBytes(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT, x VARCHAR(9) AS (X'4a'), y INT AS (b'101'), z DATE AS (DATE '2020-01-01'))");
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(['0x4a', '0x05', "DATE'2020-01-01'"], [$table->definition->columns[1]->expression, $table->definition->columns[2]->expression, $table->definition->columns[3]->expression]);
    }

    public function testPredicateWritesInBetweenLikeAndRegexp(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b VARCHAR(5), w INT AS (a IN (1)), x INT AS (a NOT BETWEEN 1 AND 2), y INT AS (b NOT LIKE 'a'), z INT AS (b REGEXP 'a'))");
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(['(`a` = 1)', '(`a` not between 1 and 2)', "(not((`b` like _utf8mb4'a')))", "regexp_like(`b`,_utf8mb4'a')"], array_map(static fn ($column): string => $column->expression, array_slice($table->definition->columns, 2)));
    }

    public function testQuotedEscapesABackslashAQuoteAndControlCharacters(): void
    {
        self::assertSame("'it\\'s\\n\\\\'", (new ItemText([], 'utf8mb4'))->quoted("it's\n\\"));
    }

    public function testBytesAnswersTheBytesOfBits(): void
    {
        self::assertSame("\x01\x01", (new ItemText([], 'utf8mb4'))->bytes('100000001'));
    }

    public function testBranchesWritesACaseExpression(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, x INT AS (CASE WHEN a > 1 THEN 1 ELSE 0 END), y INT AS (CASE a WHEN 1 THEN 2 END))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(['(case when (`a` > 1) then 1 else 0 end)', '(case `a` when 1 then 2 end)'], [$table->definition->columns[1]->expression, $table->definition->columns[2]->expression]);
    }

    public function testTruthWritesTruthTestsOverConditions(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, x INT AS (a IS NOT TRUE), y INT AS (a IS UNKNOWN))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(['((0 <> `a`) is not true)', '(`a` is null)'], [$table->definition->columns[1]->expression, $table->definition->columns[2]->expression]);
    }

    public function testBinaryWritesAnOperationInParentheses(): void
    {
        self::assertSame('(1 + 2)', (new ItemText([], 'utf8mb4'))->binary(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), '+', new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')));
    }

    public function testJoinedAnswersNullWhenAPartCouldNotBeWritten(): void
    {
        self::assertSame(['(a,b)', null], [(new ItemText([], 'utf8mb4'))->joined(['a', 'b'], '(', ',', ')'), (new ItemText([], 'utf8mb4'))->joined(['a', null], '(', ',', ')')]);
    }

    public function testWrapWritesAnExpressionBetweenAPrefixAndASuffix(): void
    {
        self::assertSame("f(_latin1'x')", (new ItemText([], 'latin1'))->wrap(new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['x']), 'f(', ')'));
    }

    public function testConditionComparesAnExpressionThatIsNoConditionWithZero(): void
    {
        self::assertSame('(0 <> `a`)', (new ItemText([], 'utf8mb4'))->condition(new ColumnUse(new Name('a'))));
    }

    public function testUnaryWritesMinusAndInversionBeforeTheOperand(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, x INT AS (~a), y INT AS (+a), z INT AS (!a))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(['~(`a`)', '`a`', '(0 = `a`)'], array_map(static fn ($column): string => $column->expression, array_slice($table->definition->columns, 1)));
    }

    public function testBetweenWritesTheRange(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, x INT AS (a BETWEEN 1 AND 2))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame('(`a` between 1 and 2)', $table->definition->columns[1]->expression);
    }

    public function testInWritesTheList(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, x INT AS (a NOT IN (1, 2)))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame('(`a` not in (1,2))', $table->definition->columns[1]->expression);
    }

    public function testLikeWritesTheEscape(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (b VARCHAR(9), x INT AS (b LIKE 'a' ESCAPE '|'))");
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame("(`b` like _utf8mb4'a' escape _utf8mb4'|')", $table->definition->columns[1]->expression);
    }

    public function testLegacyWritesAStringWithoutIntroducerAsWritten(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT, c VARCHAR(10) AS (concat(a, 'x')))");
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(["concat(`a`,'x')", true], [$table->definition->columns[1]->expression, (new ItemText([], 'latin1', \SqlSemantics\Contract\GrammarRelease::MySql5744))->legacy()]);
    }

    public function testRenderedWritesAnExpressionAsSqlSemanticsRendersIt(): void
    {
        self::assertSame(['@v', 'abc'], [(new ItemText(['abc' => 'Abc'], 'utf8mb4'))->rendered(new UserVariable(new Name('v'))), (new ItemText(['abc' => 'Abc'], 'utf8mb4'))->rendered(new ColumnUse(new Name('abc')))]);
    }

    public function testScalarWritesAColumnByItsDeclaredNameAndAnswersNullForAnUnknownExpression(): void
    {
        self::assertSame(['`Abc`', null], [(new ItemText(['abc' => 'Abc'], 'utf8mb4'))->scalar(new ColumnUse(new Name('abc'))), (new ItemText(['abc' => 'Abc'], 'utf8mb4'))->scalar(new UserVariable(new Name('v')))]);
    }

    public function testOppositeAnswersTheComparisonThatNegatesAnother(): void
    {
        self::assertSame(['<>', '=', '>=', '>', '<=', '<', null], array_map(static fn (ComparisonOperator $operator): ?string => (new ItemText([], 'utf8mb4'))->opposite($operator), [ComparisonOperator::Equal, ComparisonOperator::NotEqual, ComparisonOperator::Less, ComparisonOperator::LessOrEqual, ComparisonOperator::Greater, ComparisonOperator::GreaterOrEqual, ComparisonOperator::NullSafeEqual]));
    }
}
