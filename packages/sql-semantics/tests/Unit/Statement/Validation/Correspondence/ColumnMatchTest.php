<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation\Correspondence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Projection as P;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Validation\Correspondence as V;
use SqlSemantics\Statement\Validation\Failure\InvariantViolation;

#[CoversClass(V\ColumnMatch::class)]
#[Small]
final class ColumnMatchTest extends TestCase
{
    public function testCheckRejectsTreatingAnAliasAsAnUnresolvedColumn(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $name = new Name('answer');
        $field = new P\Field(new E\NullConstant(), $name);
        $aliases = new SqliteAliasScope(new P\Fields($scope, $field), $field);
        $this->expectException(InvariantViolation::class);
        (new V\ColumnMatch())->check(new C\Expression\ColumnUse($name), new E\ColumnReference($scope, $name), $aliases);
    }

    public function testCheckRejectsLosingTheTruthLiteralAlternative(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $name = new Name('TRUE');
        $this->expectException(InvariantViolation::class);
        (new V\ColumnMatch())->check(new C\Expression\ColumnUse($name), new E\ColumnReference($scope, $name), $scope);
    }

    public function testReferenceRejectsAColumnBoundInADifferentScope(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $name = new Name('id');
        $this->expectException(InvariantViolation::class);
        (new V\ColumnMatch())->reference(new E\ColumnReference(new Scope($catalog), $name), new E\ColumnReference(new Scope($catalog), $name));
    }

    public function testAliasRejectsAnEqualFieldFromAnotherProjection(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $name = new Name('answer');
        $field = new P\Field(new E\NullConstant(), $name);
        $expected = new P\AliasReference(new P\Fields($scope, $field), $field, $name);
        $actual = new P\AliasReference(new P\Fields($scope, $field), $field, $name);
        $this->expectException(InvariantViolation::class);
        (new V\ColumnMatch())->alias($expected, $actual);
    }
}
