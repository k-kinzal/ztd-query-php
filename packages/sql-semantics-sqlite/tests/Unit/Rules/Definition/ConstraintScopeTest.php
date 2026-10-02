<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ConstraintScope::class)]
#[Small]
final class ConstraintScopeTest extends TestCase
{
    public function testLacksComparesNamesAsTheContextDoes(): void
    {
        $environment = new Environment((new Semantics(Dialect::Sqlite))->context());
        $scope = new ConstraintScope($environment, $environment, $environment, [new Name('a'), new Name('B')], Comparison::AsciiInsensitive);

        self::assertFalse($scope->lacks(new Name('b')));
        self::assertTrue($scope->lacks(new Name('c')));
    }

    public function testLacksIsNeverCertainWhileTheColumnListIsNotCompletelyKnown(): void
    {
        $environment = new Environment((new Semantics(Dialect::Sqlite))->context());
        $scope = new ConstraintScope($environment, $environment, $environment, null, Comparison::AsciiInsensitive);

        self::assertFalse($scope->lacks(new Name('c')));
    }
}
