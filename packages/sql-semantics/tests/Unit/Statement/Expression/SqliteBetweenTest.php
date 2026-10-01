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
use SqlSemantics\Statement\Expression\SqliteBetween;
use SqlSemantics\Statement\Expression\SqliteInteger;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Literal\UnsignedInteger;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(SqliteBetween::class)]
#[Small]
final class SqliteBetweenTest extends TestCase
{
    public function testTypeProducesAnIntegerTruthResultForKnownBounds(): void
    {
        $one = new SqliteInteger(new UnsignedInteger('1'));
        $type = (new SqliteBetween($one, $one, $one))->type();
        self::assertInstanceOf(TypeDescriptor::class, $type);
        self::assertSame(Builtin::Integer, $type->name);
    }

    public function testNullabilityDoesNotInferNullFromJustOneNullBound(): void
    {
        $expression = new SqliteBetween(new SqliteInteger(new UnsignedInteger('1')), new SqliteInteger(new UnsignedInteger('2')), new NullConstant());
        self::assertSame(Nullability::MaybeNull, $expression->nullability());
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $expression->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(0, $result->fetchColumn());
    }

    public function testReferencesDoesNotDuplicateTheSubjectLookup(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main')), complete: false));
        $subject = new ColumnReference($scope, new Name('foo'));
        $one = new SqliteInteger(new UnsignedInteger('1'));
        self::assertSame([$subject], (new SqliteBetween($subject, $one, $one))->references());
    }

    #[TestWith([false, 1])]
    #[TestWith([true, 0])]
    public function testToStringRetainsInclusiveBoundsAndNegation(bool $negated, int $expected): void
    {
        $one = new SqliteInteger(new UnsignedInteger('1'));
        $expression = new SqliteBetween($one, $one, new SqliteInteger(new UnsignedInteger('2')), $negated);
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $expression->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame($expected, $result->fetchColumn());
    }
}
