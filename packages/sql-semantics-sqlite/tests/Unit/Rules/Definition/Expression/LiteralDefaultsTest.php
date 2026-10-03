<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Expression\LiteralDefaults;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultExpression;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultWord;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;

#[CoversClass(LiteralDefaults::class)]
#[Medium]
final class LiteralDefaultsTest extends TestCase
{
    public function testDefaultsAnswersTheDefaultClausesInWrittenOrder(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a NOT NULL DEFAULT 1 UNIQUE DEFAULT (2) DEFAULT word)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $defaults = (new LiteralDefaults())->defaults($statement->columns[0]->constraints);
        self::assertCount(3, $defaults);
        self::assertInstanceOf(DefaultLiteral::class, $defaults[0]);
        self::assertInstanceOf(DefaultExpression::class, $defaults[1]);
        self::assertInstanceOf(DefaultWord::class, $defaults[2]);
    }

    public function testNullIsTheLiteralNullWithOrWithoutParentheses(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a DEFAULT NULL DEFAULT (NULL) DEFAULT ((NULL)) DEFAULT -NULL DEFAULT (+NULL) DEFAULT 0 DEFAULT null)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $rule = new LiteralDefaults();
        self::assertSame([true, true, true, false, false, false, true], array_map(static fn (DefaultLiteral|DefaultExpression|DefaultWord $default): bool => $rule->null($default), $rule->defaults($statement->columns[0]->constraints)));
    }

    public function testLiteralTellsTheDefaultsSqliteComputesWhenAColumnIsAdded(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze("CREATE TABLE t (a DEFAULT 1 DEFAULT -1.5 DEFAULT 'x' DEFAULT x'00' DEFAULT CURRENT_TIME DEFAULT word DEFAULT TRUE DEFAULT (1) DEFAULT (-(-5)) DEFAULT (+'x') DEFAULT (CAST(1 AS TEXT)) DEFAULT (true) DEFAULT (1 + 1) DEFAULT (abs(1)) DEFAULT (CURRENT_DATE) DEFAULT (CAST(abs(1) AS TEXT)) DEFAULT (~1))")->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $rule = new LiteralDefaults();
        self::assertSame([true, true, true, true, false, true, true, true, true, true, true, true, false, false, false, false, false], array_map(static fn (DefaultLiteral|DefaultExpression|DefaultWord $default): bool => $rule->literal($default), $rule->defaults($statement->columns[0]->constraints)));
    }

    public function testConstantLooksThroughParenthesesSignsAndCasts(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze("SELECT ((-(+CAST((1) AS INT)))), -(1 + 1), NOT 1, (?)");
        $rule = new LiteralDefaults();

        self::assertTrue($rule->constant($query->field(0)->expression));
        self::assertFalse($rule->constant($query->field(1)->expression));
        self::assertFalse($rule->constant($query->field(2)->expression));
        self::assertFalse($rule->constant($query->field(3)->expression));
    }

    public function testCoreRemovesTheParentheses(): void
    {
        $literal = new IntegerLiteral('1');

        self::assertSame($literal, (new LiteralDefaults())->core(new Grouped(new Grouped($literal))));
        self::assertSame($literal, (new LiteralDefaults())->core($literal));
    }
}
