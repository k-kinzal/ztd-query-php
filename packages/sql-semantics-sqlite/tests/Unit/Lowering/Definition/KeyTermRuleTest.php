<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\KeyTermRule;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateIndex;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\LiteralColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TablePrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableUnique;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(KeyTermRule::class)]
#[Medium]
final class KeyTermRuleTest extends TestCase
{
    public function testKeyTermsWrapAStringUnderAnyParenthesesAndCollations(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze("CREATE TABLE t (a, b, PRIMARY KEY ((('a') COLLATE nocase) COLLATE binary), UNIQUE (b, 'b' DESC))")->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $key = $statement->constraints[0]->items[0];
        $unique = $statement->constraints[1]->items[0];
        self::assertInstanceOf(TablePrimaryKey::class, $key);
        self::assertInstanceOf(TableUnique::class, $unique);
        $outer = $key->terms[0]->expression;
        self::assertInstanceOf(Collate::class, $outer);
        self::assertInstanceOf(Grouped::class, $outer->operand);
        self::assertInstanceOf(Collate::class, $outer->operand->operand);
        self::assertInstanceOf(Grouped::class, $outer->operand->operand->operand);
        self::assertInstanceOf(LiteralColumn::class, $outer->operand->operand->operand->operand);
        self::assertInstanceOf(ColumnUse::class, $unique->terms[0]->expression);
        self::assertInstanceOf(LiteralColumn::class, $unique->terms[1]->expression);
        self::assertSame(SortDirection::Descending, $unique->terms[1]->direction);
    }

    public function testIndexTermsWrapAStringUnderAtMostOneCollation(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze("CREATE INDEX i ON t ('a', 'a' COLLATE nocase, 'a' COLLATE nocase COLLATE binary, ('a'))")->statement;

        self::assertInstanceOf(CreateIndex::class, $statement);
        self::assertInstanceOf(LiteralColumn::class, $statement->terms[0]->expression);
        self::assertInstanceOf(Collate::class, $statement->terms[1]->expression);
        self::assertInstanceOf(LiteralColumn::class, $statement->terms[1]->expression->operand);
        self::assertInstanceOf(Collate::class, $statement->terms[2]->expression);
        self::assertInstanceOf(Collate::class, $statement->terms[2]->expression->operand);
        self::assertInstanceOf(TextLiteral::class, $statement->terms[2]->expression->operand->operand);
        self::assertInstanceOf(Grouped::class, $statement->terms[3]->expression);
        self::assertInstanceOf(LiteralColumn::class, $statement->terms[3]->expression->operand);
    }

    public function testTermLeavesEveryOtherTermAsItIs(): void
    {
        $rule = new KeyTermRule();
        $column = new SortTerm(new ColumnUse(new Name('a')), SortDirection::Descending);
        $string = new SortTerm(new Grouped(new TextLiteral('a')));

        self::assertSame($column, $rule->term($column, null));
        self::assertNotSame($string, $rule->term($string, null));
        self::assertSame(SortDirection::Descending, $rule->term($column, 1)->direction);
    }

    public function testExpressionRebuildsTheWrappersInOrderAroundTheLiteral(): void
    {
        $literal = new TextLiteral('a');
        $rebuilt = (new KeyTermRule())->expression(new Grouped(new Collate($literal, new Name('nocase'))));

        self::assertInstanceOf(Grouped::class, $rebuilt);
        self::assertInstanceOf(Collate::class, $rebuilt->operand);
        self::assertSame('nocase', $rebuilt->operand->collation->value);
        self::assertInstanceOf(LiteralColumn::class, $rebuilt->operand->operand);
        self::assertSame($literal, $rebuilt->operand->operand->literal);
    }
}
