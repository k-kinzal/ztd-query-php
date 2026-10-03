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
use SqlSemantics\Statement\Expression\SqliteInList;
use SqlSemantics\Statement\Expression\SqliteInteger;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Literal\UnsignedInteger;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Type\NullDomain;

#[CoversClass(SqliteInList::class)]
#[Small]
final class SqliteInListTest extends TestCase
{
    public function testTypeDoesNotBindAnEliminatedEmptyListSubject(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $subject = new ColumnReference($scope, new Name('absent'));
        $expression = new SqliteInList($subject);
        $type = $expression->type();
        self::assertInstanceOf(TypeDescriptor::class, $type);
        self::assertSame(Builtin::Integer, $type->name);
        self::assertSame(Nullability::NotNull, $expression->nullability());
        self::assertSame([], $expression->references());
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $expression->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(0, $result->fetchColumn());
    }

    public function testNullabilityAllowsAMatchDespiteANullChoice(): void
    {
        $one = new SqliteInteger(new UnsignedInteger('1'));
        $expression = new SqliteInList($one, false, $one, new NullConstant());
        self::assertSame(Nullability::MaybeNull, $expression->nullability());
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $expression->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(1, $result->fetchColumn());
    }

    public function testNullabilityRecognizesAnAllNullList(): void
    {
        $expression = new SqliteInList(new SqliteInteger(new UnsignedInteger('1')), false, new NullConstant(), new NullConstant());
        self::assertSame(Nullability::AlwaysNull, $expression->nullability());
        self::assertSame(NullDomain::Null, $expression->type());
    }

    public function testReferencesRetainsListDependenciesInTheirPositions(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main')), complete: false));
        $subject = new ColumnReference($scope, new Name('foo'));
        $choice = new ColumnReference($scope, new Name('bar'));
        self::assertSame([$subject, $choice], (new SqliteInList($subject, false, $choice))->references());
    }

    #[TestWith([false, 0])]
    #[TestWith([true, 1])]
    public function testToStringKeepsTheEmptySetRuleEvenForNull(bool $negated, int $expected): void
    {
        $expression = new SqliteInList(new NullConstant(), $negated);
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $expression->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame($expected, $result->fetchColumn());
    }
}
