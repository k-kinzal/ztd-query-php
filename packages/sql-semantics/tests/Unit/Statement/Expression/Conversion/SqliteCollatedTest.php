<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Conversion;

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
use SqlSemantics\Statement\Expression\Conversion\SqliteCollated;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Expression\SqliteText;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\NullDomain;

#[CoversClass(SqliteCollated::class)]
#[Small]
final class SqliteCollatedTest extends TestCase
{
    public function testTypeDoesNotConvertTheOperand(): void
    {
        $expression = new SqliteCollated(new SqliteText(new StringLiteral('12')), new Name('NOCASE'));
        $type = $expression->type();
        self::assertInstanceOf(TypeDescriptor::class, $type);
        self::assertSame(Builtin::Text, $type->name);
    }

    public function testNullabilityRetainsTheOperandsNullFact(): void
    {
        self::assertSame(Nullability::AlwaysNull, (new SqliteCollated(new NullConstant(), new Name('BINARY')))->nullability());
        self::assertSame(NullDomain::Null, (new SqliteCollated(new NullConstant(), new Name('BINARY')))->type());
    }

    public function testReferencesRetainsTheOriginalLookup(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $column = new ColumnReference($scope, new Name('foo'));
        $expression = new SqliteCollated($column, new Name('NOCASE'));
        self::assertSame([$column], $expression->references());
        self::assertSame(Invalid::MissingColumn, $expression->type());
    }

    #[TestWith(['NOCASE', 1])]
    #[TestWith(['BINARY', 0])]
    public function testToStringPreservesTheRequestedComparison(string $name, int $expected): void
    {
        $expression = new SqliteCollated(new SqliteText(new StringLiteral('a')), new Name($name));
        $result = (new PDO('sqlite::memory:'))->query('SELECT (' . $expression->toString() . ") = 'A'");
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame($expected, $result->fetchColumn());
    }
}
