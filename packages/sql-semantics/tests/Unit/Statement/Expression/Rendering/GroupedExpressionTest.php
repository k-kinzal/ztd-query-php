<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Rendering;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Literal\UnsignedInteger;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(E\Rendering\GroupedExpression::class)]
#[Small]
final class GroupedExpressionTest extends TestCase
{
    public function testTypeKeepsTheOperandDeclarationIdentityThroughGrouping(): void
    {
        $value = new E\NullConstant();
        $group = new E\Rendering\GroupedExpression($value);
        self::assertSame(\SqlSemantics\Statement\Type\NullDomain::Null, $group->type());
    }

    public function testNullabilityKeepsTheGroupedOperandFact(): void
    {
        self::assertSame(\SqlSemantics\Statement\Declaration\Nullability::AlwaysNull, (new E\Rendering\GroupedExpression(new E\NullConstant()))->nullability());
    }

    public function testReferencesKeepsTheOriginalUseSite(): void
    {
        $scope = new \SqlSemantics\Statement\Relation\Scope(new Catalog(new SearchPath(new Name('main'))));
        $column = new E\ColumnReference($scope, new Name('id'));
        self::assertSame([$column], (new E\Rendering\GroupedExpression($column))->references());
    }

    public function testToStringKeepsGroupingWithoutAlteringEvaluation(): void
    {
        $value = new E\SqliteBinary(new E\SqliteInteger(new UnsignedInteger('1')), E\SqliteBinaryOperator::Add, new E\SqliteInteger(new UnsignedInteger('2')));
        $group = new E\Rendering\GroupedExpression($value);
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $group->toString() . ' * 3');
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(9, $result->fetchColumn());
    }
}
