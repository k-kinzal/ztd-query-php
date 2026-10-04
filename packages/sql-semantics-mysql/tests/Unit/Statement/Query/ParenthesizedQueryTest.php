<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ParenthesizedQuery::class)]
#[Medium]
final class ParenthesizedQueryTest extends TestCase
{
    public function testDeriveStatementRecordsTheRowsOfTheInnerQuery(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->analyze('(SELECT a FROM t)', [$t]);

        self::assertInstanceOf(ParenthesizedQuery::class, $operation->statement);
        self::assertInstanceOf(Select::class, $operation->statement->query);
        self::assertSame($operation->facts->query($operation->statement->query), $operation->facts->output);
    }

    public function testDeriveQueryAnswersTheFactsOfTheInnerQuery(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->analyze('((SELECT a FROM t)) UNION SELECT c FROM u', [$t, $u]);

        self::assertInstanceOf(SetOperation::class, $operation->statement);
        self::assertInstanceOf(ParenthesizedQuery::class, $operation->statement->left);
        self::assertInstanceOf(ParenthesizedQuery::class, $operation->statement->left->query);
        self::assertSame($t->columns[0], $operation->facts->query($operation->statement->left)->fields()?->at(0)->column());
    }

    public function testRenderWritesTheParentheses(): void
    {
        self::assertSame('((SELECT 1)) UNION SELECT 2', (new Semantics(Dialect::MySql))->analyze('((select 1)) union select 2')->toString());
    }
}
