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
use SqlSemantics\Statement\Expression\SqliteBinary;
use SqlSemantics\Statement\Expression\SqliteBinaryOperator;
use SqlSemantics\Statement\Expression\SqliteInteger;
use SqlSemantics\Statement\Expression\SqliteReal;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Literal\UnsignedInteger;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\SqliteNumericDomain;

#[CoversClass(SqliteBinary::class)]
#[Small]
final class SqliteBinaryTest extends TestCase
{
    public function testTypeKeepsArithmeticOverflowInTheNumericDomain(): void
    {
        $expression = new SqliteBinary(new SqliteInteger(new UnsignedInteger('9223372036854775807')), SqliteBinaryOperator::Add, new SqliteInteger(new UnsignedInteger('1')));
        self::assertSame(SqliteNumericDomain::IntegerOrReal, $expression->type());
        $result = (new PDO('sqlite::memory:'))->query('SELECT typeof(' . $expression->toString() . ')');
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame('real', $result->fetchColumn());
    }

    public function testNullabilityAllowsNullFromNonfiniteArithmetic(): void
    {
        $infinity = new SqliteReal('1e999');
        $expression = new SqliteBinary($infinity, SqliteBinaryOperator::Subtract, $infinity);
        self::assertSame(Nullability::MaybeNull, $expression->nullability());
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $expression->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertNull($result->fetchColumn());
    }

    public function testNullabilityDoesNotTreatLogicAsStrictNullPropagation(): void
    {
        $expression = new SqliteBinary(new SqliteInteger(new UnsignedInteger('0')), SqliteBinaryOperator::And, new NullConstant());
        self::assertSame(Nullability::NotNull, $expression->nullability());
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $expression->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(0, $result->fetchColumn());
    }

    public function testNullabilityKeepsEqualitySeparateFromNullSafeComparison(): void
    {
        $null = new NullConstant();
        self::assertSame(Nullability::AlwaysNull, (new SqliteBinary($null, SqliteBinaryOperator::Equal, $null))->nullability());
        self::assertSame(Nullability::NotNull, (new SqliteBinary($null, SqliteBinaryOperator::Is, $null))->nullability());
        self::assertSame(NullDomain::Null, (new SqliteBinary($null, SqliteBinaryOperator::Equal, $null))->type());
    }

    public function testReferencesRetainsEachActualOperandLookup(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main')), complete: false));
        $left = new ColumnReference($scope, new Name('foo'));
        $right = new ColumnReference($scope, new Name('bar'));
        self::assertSame([$left, $right], (new SqliteBinary($left, SqliteBinaryOperator::Add, $right))->references());
    }

    #[TestWith([SqliteBinaryOperator::Add])]
    #[TestWith([SqliteBinaryOperator::Subtract])]
    #[TestWith([SqliteBinaryOperator::Multiply])]
    #[TestWith([SqliteBinaryOperator::Divide])]
    #[TestWith([SqliteBinaryOperator::Remainder])]
    #[TestWith([SqliteBinaryOperator::BitwiseAnd])]
    #[TestWith([SqliteBinaryOperator::BitwiseOr])]
    #[TestWith([SqliteBinaryOperator::ShiftLeft])]
    #[TestWith([SqliteBinaryOperator::ShiftRight])]
    #[TestWith([SqliteBinaryOperator::Concatenate])]
    #[TestWith([SqliteBinaryOperator::Less])]
    #[TestWith([SqliteBinaryOperator::Greater])]
    #[TestWith([SqliteBinaryOperator::LessOrEqual])]
    #[TestWith([SqliteBinaryOperator::GreaterOrEqual])]
    #[TestWith([SqliteBinaryOperator::Equal])]
    #[TestWith([SqliteBinaryOperator::DoubleEqual])]
    #[TestWith([SqliteBinaryOperator::NotEqual])]
    #[TestWith([SqliteBinaryOperator::AngleNotEqual])]
    #[TestWith([SqliteBinaryOperator::Is])]
    #[TestWith([SqliteBinaryOperator::IsNot])]
    #[TestWith([SqliteBinaryOperator::DistinctFrom])]
    #[TestWith([SqliteBinaryOperator::NotDistinctFrom])]
    #[TestWith([SqliteBinaryOperator::And])]
    #[TestWith([SqliteBinaryOperator::Or])]
    public function testToStringPreservesEachSupportedBinaryOperation(SqliteBinaryOperator $operator): void
    {
        $expression = new SqliteBinary(new SqliteInteger(new UnsignedInteger('1')), $operator, new SqliteInteger(new UnsignedInteger('2')));
        $db = new PDO('sqlite::memory:');
        $original = $db->query('SELECT 1 ' . $operator->value . ' 2');
        $rebuilt = $db->query('SELECT ' . $expression->toString());
        self::assertInstanceOf(PDOStatement::class, $original);
        self::assertInstanceOf(PDOStatement::class, $rebuilt);
        self::assertSame($original->fetchColumn(), $rebuilt->fetchColumn());
    }

    public function testToStringKeepsTheGroupingOfNestedArithmetic(): void
    {
        $one = new SqliteInteger(new UnsignedInteger('1'));
        $two = new SqliteInteger(new UnsignedInteger('2'));
        $expression = new SqliteBinary(new SqliteBinary($one, SqliteBinaryOperator::Add, $two), SqliteBinaryOperator::Multiply, $two);
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $expression->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(6, $result->fetchColumn());
    }

    public function testDiscardsOperandsMatchesTheEarlyIntegerZeroReduction(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $missing = new ColumnReference($scope, new Name('absent'));
        $expression = new SqliteBinary(new SqliteInteger(new UnsignedInteger('0')), SqliteBinaryOperator::And, $missing);
        self::assertTrue($expression->discardsOperands());
        self::assertSame([], $expression->references());
        $type = $expression->type();
        self::assertInstanceOf(TypeDescriptor::class, $type);
        self::assertSame(Builtin::Integer, $type->name);
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $expression->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(0, $result->fetchColumn());
    }

    public function testDiscardsOperandsDoesNotApplyTheIntegerRuleToLaterDecodedNumerals(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $missing = new ColumnReference($scope, new Name('absent'));
        $expression = new SqliteBinary(new SqliteInteger(new UnsignedInteger('0_0')), SqliteBinaryOperator::And, $missing);
        self::assertFalse($expression->discardsOperands());
        self::assertSame([$missing], $expression->references());
    }
}
