<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\MissingColumn;
use SqlSemantics\Statement\Reference\OuterLookup;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(OuterLookup::class)]
#[Small]
final class OuterLookupTest extends TestCase
{
    public function testResolutionRetainsTheExactFallbackNamespace(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $name = new Name('absent');
        $lookup = new OuterLookup($scope, $name);
        self::assertSame($scope, $lookup->scope);
        self::assertSame($name, $lookup->name);
        self::assertSame(MissingColumn::Value, $lookup->resolution);
    }
}
