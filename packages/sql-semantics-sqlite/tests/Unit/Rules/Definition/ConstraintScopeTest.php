<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(ConstraintScope::class)]
#[Small]
final class ConstraintScopeTest extends TestCase
{
    public function testLacksComparesNamesAsTheContextDoes(): void
    {
        $environment = new Environment((new Inspection())->context());
        $scope = new ConstraintScope($environment, $environment, $environment, [new Name('a'), new Name('B')], Comparison::AsciiInsensitive);

        self::assertFalse($scope->lacks(new Name('b')));
        self::assertTrue($scope->lacks(new Name('c')));
    }

    public function testLacksIsNeverCertainWhileTheColumnListIsNotCompletelyKnown(): void
    {
        $environment = new Environment((new Inspection())->context());
        $scope = new ConstraintScope($environment, $environment, $environment, null, Comparison::AsciiInsensitive);

        self::assertFalse($scope->lacks(new Name('c')));
    }
}
