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
use SqlSemantics\Statement\Expression\BooleanReference;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;
use SqlSemantics\Statement\Type\Unresolved;

#[CoversClass(BooleanReference::class)]
#[Small]
final class BooleanReferenceTest extends TestCase
{
    public function testTypeAndNullabilityUseTheColumnBeforeTheLiteralAlternative(): void
    {
        $column = new Column(new Name('true'), new TypeDescriptor(Builtin::Text), Nullability::MaybeNull);
        $table = new Table(new QualifiedName(new Name('users')), $column);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::AsciiInsensitive, Comparison::AsciiInsensitive, true, null, null, $table);
        $scope = new Scope($catalog, new TableReference($catalog, $table->name));
        $expression = new BooleanReference(new ColumnReference($scope, new Name('TRUE')));
        self::assertSame($column->type, $expression->type());
        self::assertSame(Nullability::MaybeNull, $expression->nullability());
    }

    public function testTypeDoesNotSelectALiteralBeforeKnowingTheDeclaration(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $scope = new Scope($catalog, new TableReference($catalog, new QualifiedName(new Name('users'))));
        $expression = new BooleanReference(new ColumnReference($scope, new Name('TRUE')));
        self::assertSame(Unresolved::MissingDeclaration, $expression->type());
        self::assertSame(Nullability::Unknown, $expression->nullability());
    }

    public function testNullabilityOfTheSelectedLiteralIsNotNull(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $expression = new BooleanReference(new ColumnReference(new Scope($catalog), new Name('TRUE')));
        self::assertSame(Nullability::NotNull, $expression->nullability());
        $type = $expression->type();
        self::assertInstanceOf(TypeDescriptor::class, $type);
        self::assertSame(Builtin::Integer, $type->name);
    }

    public function testReferencesKeepsTheLookupOwnedByTheOriginalScope(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $column = new ColumnReference(new Scope($catalog), new Name('TRUE'));
        self::assertSame([$column], (new BooleanReference($column))->references());
    }

    #[TestWith(['TRUE', 1])]
    #[TestWith(['true', 1])]
    #[TestWith(['FaLsE', 0])]
    public function testToStringRetainsTheTruthIdentifierAndOutputLabel(string $name, int $expected): void
    {
        $db = new PDO('sqlite::memory:');
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $expression = new BooleanReference(new ColumnReference(new Scope($catalog), new Name($name)));
        $result = $db->query('SELECT ' . $expression->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame([$name => $expected], $result->fetch(PDO::FETCH_ASSOC));
        self::assertSame($expected, $expression->fallback);
    }

    public function testToStringDoesNotReplaceABooleanTestWithAnIntegerComparison(): void
    {
        $db = new PDO('sqlite::memory:');
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $expression = new BooleanReference(new ColumnReference(new Scope($catalog), new Name('TRUE')));
        $result = $db->query('SELECT 2 IS ' . $expression->toString() . ' AS truth_test, 2 IS 1 AS equality');
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(['truth_test' => 1, 'equality' => 0], $result->fetch(PDO::FETCH_ASSOC));
    }
}
