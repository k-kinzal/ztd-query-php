<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation\Correspondence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Projection as P;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Validation\Correspondence as V;
use SqlSemantics\Statement\Validation\Failure\InvariantViolation;

#[CoversClass(V\EnvironmentMatch::class)]
#[Small]
final class EnvironmentMatchTest extends TestCase
{
    public function testSameRequiresTheActualProjectionAndItsVisibleAliases(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $field = new P\Field(new E\NullConstant(), new Name('x'));
        $fields = new P\Fields($scope, $field);
        self::assertTrue(V\EnvironmentMatch::same(new SqliteAliasScope($fields, $field), new SqliteAliasScope($fields, $field)));
        self::assertFalse(V\EnvironmentMatch::same(new SqliteAliasScope($fields, $field), new SqliteAliasScope($fields)));
        self::assertFalse(V\EnvironmentMatch::same(new SqliteAliasScope($fields, $field), new SqliteAliasScope(new P\Fields($scope, $field), $field)));
    }

    public function testCheckRejectsAnEquivalentButDifferentContextWrapper(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $other = new Catalog($catalog->searchPath);
        $this->expectException(InvariantViolation::class);
        (new V\EnvironmentMatch())->check($catalog, new C\Query\Inputs(), new Scope($other));
    }

    public function testCheckRejectsAChangedAliasEvenWhenTheDeclarationIsUnchanged(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $name = new QualifiedName(new Name('items'));
        $scope = new Scope($catalog, new TableReference($catalog, $name, new Name('other')));
        $this->expectException(InvariantViolation::class);
        (new V\EnvironmentMatch())->check($catalog, new C\Query\Inputs(new C\Query\NamedInput($name, new Name('wanted'))), $scope);
    }

    public function testCheckRejectsAnUnexpectedLexicalParent(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $scope = new Scope(new Scope($catalog));
        $this->expectException(InvariantViolation::class);
        (new V\EnvironmentMatch())->check($catalog, new C\Query\Inputs(), $scope);
    }
}
