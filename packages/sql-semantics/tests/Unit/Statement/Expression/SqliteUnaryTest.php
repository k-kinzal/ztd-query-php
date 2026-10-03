<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Expression\SqliteInteger;
use SqlSemantics\Statement\Expression\SqliteText;
use SqlSemantics\Statement\Expression\SqliteUnary;
use SqlSemantics\Statement\Expression\SqliteUnaryOperator;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Literal\UnsignedInteger;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\SqliteNumericDomain;

#[CoversClass(SqliteUnary::class)]
#[Small]
final class SqliteUnaryTest extends TestCase
{
    public function testTypeRetainsTheTextDomainUnderUnaryPlus(): void
    {
        $expression = new SqliteUnary(SqliteUnaryOperator::Plus, new SqliteText(new StringLiteral('abc')));
        $type = $expression->type();
        self::assertInstanceOf(TypeDescriptor::class, $type);
        self::assertSame(Builtin::Text, $type->name);
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $expression->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame('abc', $result->fetchColumn());
    }

    public function testTypeAccountsForTheSpecialMinimumIntegerLiteral(): void
    {
        $expression = new SqliteUnary(SqliteUnaryOperator::Negate, new SqliteInteger(new UnsignedInteger('9223372036854775808')));
        $type = $expression->type();
        self::assertInstanceOf(TypeDescriptor::class, $type);
        self::assertSame(Builtin::Integer, $type->name);
        $result = (new PDO('sqlite::memory:'))->query('SELECT typeof(' . $expression->toString() . ')');
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame('integer', $result->fetchColumn());
    }

    public function testTypeRetainsRuntimeOverflowAsAConcreteNumericAlternative(): void
    {
        $minimum = new SqliteUnary(SqliteUnaryOperator::Negate, new SqliteInteger(new UnsignedInteger('9223372036854775808')));
        $expression = new SqliteUnary(SqliteUnaryOperator::Negate, $minimum);
        self::assertSame(SqliteNumericDomain::IntegerOrReal, $expression->type());
        $result = (new PDO('sqlite::memory:'))->query('SELECT typeof(' . $expression->toString() . ')');
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame('real', $result->fetchColumn());
    }

    #[TestWith([SqliteUnaryOperator::Plus])]
    #[TestWith([SqliteUnaryOperator::Negate])]
    #[TestWith([SqliteUnaryOperator::Not])]
    #[TestWith([SqliteUnaryOperator::BitwiseNot])]
    public function testNullabilityPropagatesNullForEveryPrefix(SqliteUnaryOperator $operator): void
    {
        $expression = new SqliteUnary($operator, new NullConstant());
        self::assertSame(Nullability::AlwaysNull, $expression->nullability());
        self::assertSame(NullDomain::Null, $expression->type());
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $expression->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertNull($result->fetchColumn());
    }

    public function testReferencesPreservesTheOriginalColumnLookup(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main')), complete: false));
        $column = new ColumnReference($scope, new Name('foo'));
        $expression = new SqliteUnary(SqliteUnaryOperator::BitwiseNot, $column);
        self::assertSame([$column], $expression->references());
    }

    public function testToStringPreservesNestedNegationWithoutOpeningAComment(): void
    {
        $expression = new SqliteUnary(SqliteUnaryOperator::Negate, new SqliteUnary(SqliteUnaryOperator::Negate, new SqliteInteger(new UnsignedInteger('1'))));
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $expression->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(1, $result->fetchColumn());
    }
}
