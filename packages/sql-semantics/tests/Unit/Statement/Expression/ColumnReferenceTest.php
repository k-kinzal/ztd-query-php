<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\CandidateColumn;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Unresolved;

#[CoversClass(ColumnReference::class)]
#[Small]
final class ColumnReferenceTest extends TestCase
{
    public function testTypeAndNullabilityAreDerivedFromTheExactDeclaration(): void
    {

        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);

        $expression = new ColumnReference($scope, new Name('foo'));
        self::assertSame($declared->type, $expression->type());
        self::assertSame(Nullability::NotNull, $expression->nullability());
        self::assertInstanceOf(ResolvedColumn::class, $expression->resolution);
        self::assertSame($table, $expression->resolution->table);
        self::assertSame($declared, $expression->resolution->column);
    }

    public function testTypeDoesNotTreatMissingDeclarationsAsMissingColumns(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $relation = new TableReference($catalog, new QualifiedName(new Name('bar')));
        $expression = new ColumnReference(new Scope($catalog, $relation), new Name('foo'));
        self::assertSame(Unresolved::MissingDeclaration, $expression->type());
        self::assertSame(Nullability::Unknown, $expression->nullability());
        self::assertInstanceOf(CandidateColumn::class, $expression->resolution);
        self::assertSame([$relation], $expression->resolution->possibilities);
    }

    public function testTypeDistinguishesDefiniteMissingAndAmbiguousColumns(): void
    {

        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);

        $missing = new ColumnReference($scope, new Name('absent'));
        $joined = new Scope($catalog, $relation, new TableReference($catalog, $table->name, new Name('other')));
        $ambiguous = new ColumnReference($joined, new Name('foo'));
        self::assertSame(Invalid::MissingColumn, $missing->type());
        self::assertSame(Invalid::AmbiguousColumn, $ambiguous->type());
    }

    public function testReferencesReturnsTheActualLookupForScopeChecks(): void
    {

        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);

        $expression = new ColumnReference($scope, new Name('foo'));
        self::assertSame([$expression], $expression->references());
    }

    public function testNullabilityPreservesTheDeclarationFact(): void
    {

        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);

        self::assertSame(Nullability::NotNull, (new ColumnReference($scope, new Name('foo')))->nullability());
    }

    public function testToStringReconstructsTheColumnQualifier(): void
    {

        $declared = new Column(new Name('foo'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $table = new Table(new QualifiedName(new Name('bar')), $declared);
        $catalog = new Catalog(new SearchPath(new Name('main')), Comparison::Sensitive, Comparison::Sensitive, true, null, null, $table);
        $relation = new TableReference($catalog, $table->name);
        $scope = new Scope($catalog, $relation);

        self::assertSame('bar.foo', (new ColumnReference($scope, new Name('foo'), $table->name))->toString());
    }
}
