<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Model\Sqlite\Value\NmWithIdj_a2015ecf as Name;
use SqlSemantics\Statement\Reference;
use SqlSemantics\Statement\ReferenceKind;
use SqlSemantics\Statement\Resolution;

#[CoversClass(Resolution::class)]
#[UsesClass(Reference::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\Invariant::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[Small]
final class ResolutionTest extends TestCase
{
    public function testTablesListsOnlyTheReferencesToDependencies(): void
    {
        $dependency = new Reference(new Name('users'), ['users'], ReferenceKind::Dependency);
        $common = new Reference(new Name('recent'), ['recent'], ReferenceKind::CommonTableExpression);
        $resolution = new Resolution([], [], [$common, $dependency]);
        self::assertSame([$dependency], $resolution->tables());
        self::assertSame([], (new Resolution())->tables());
    }

}
