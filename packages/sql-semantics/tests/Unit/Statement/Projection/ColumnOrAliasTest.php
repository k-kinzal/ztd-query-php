<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Projection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Projection\AliasReference;
use SqlSemantics\Statement\Projection\ColumnOrAlias;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Projection\Fields;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Type\Unresolved;

#[CoversClass(ColumnOrAlias::class)]
#[Small]
final class ColumnOrAliasTest extends TestCase
{
    public function testTypeNamesTheMissingInformationWithoutChoosingAnOwner(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $scope = new Scope($catalog, new TableReference($catalog, new QualifiedName(new Name('bar'))));
        $lookup = new ColumnReference($scope, new Name('answer'));
        $field = new Field(new NullConstant(), new Name('answer'));
        $alias = new AliasReference(new Fields($scope, $field), $field, new Name('answer'));
        $reference = new ColumnOrAlias($lookup, $alias);
        self::assertSame(Unresolved::MissingDeclaration, $reference->type());
        self::assertSame($field, $reference->alias->field);
    }

    public function testNullabilityCannotDiscardTheUnavailableInputColumnAlternative(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $scope = new Scope($catalog, new TableReference($catalog, new QualifiedName(new Name('bar'))));
        $lookup = new ColumnReference($scope, new Name('answer'));
        $field = new Field(new NullConstant(), new Name('answer'));
        $alias = new AliasReference(new Fields($scope, $field), $field, new Name('answer'));
        $reference = new ColumnOrAlias($lookup, $alias);
        self::assertSame(Nullability::Unknown, $reference->nullability());
    }

    public function testReferencesRetainsThePotentialInputColumn(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $scope = new Scope($catalog, new TableReference($catalog, new QualifiedName(new Name('bar'))));
        $lookup = new ColumnReference($scope, new Name('answer'));
        $field = new Field(new NullConstant(), new Name('answer'));
        $alias = new AliasReference(new Fields($scope, $field), $field, new Name('answer'));
        $reference = new ColumnOrAlias($lookup, $alias);
        self::assertSame([$lookup], $reference->references());
    }

    public function testToStringLeavesTheContextDependentLookupForTheDatabase(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $scope = new Scope($catalog, new TableReference($catalog, new QualifiedName(new Name('bar'))));
        $lookup = new ColumnReference($scope, new Name('answer'));
        $field = new Field(new NullConstant(), new Name('answer'));
        $alias = new AliasReference(new Fields($scope, $field), $field, new Name('answer'));
        $reference = new ColumnOrAlias($lookup, $alias);
        self::assertSame('answer', $reference->toString());
    }
}
