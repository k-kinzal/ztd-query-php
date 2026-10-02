<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation\Correspondence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Validation\Correspondence as V;

#[CoversClass(V\ScalarPair::class)]
#[Small]
final class ScalarPairTest extends TestCase
{
    public function testRetainsTheActualOperandAndItsRequiredUseEnvironment(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $input = new C\Expression\ColumnUse(new Name('id'));
        $actual = new E\ColumnReference($scope, $input->name);
        $pair = new V\ScalarPair($input, $actual, $scope);
        self::assertSame($input, $pair->input);
        self::assertSame($actual, $pair->actual);
        self::assertSame($scope, $pair->scope);
    }
}
