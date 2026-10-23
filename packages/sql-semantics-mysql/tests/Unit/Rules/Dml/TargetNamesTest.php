<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Dml\TargetNames;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(TargetNames::class)]
#[Small]
final class TargetNamesTest extends TestCase
{
    public function testDuplicateDistinguishesIntrinsicNamesFromContextualResolution(): void
    {
        $t = new QualifiedName(new Name('t'));
        $qualified = new QualifiedName(new Name('t'), new Name('d'));
        $upper = new QualifiedName(new Name('T'));
        $empty = new QualifiedName(new Name('t'), new Name(''));
        self::assertNull((new TargetNames())->duplicate([$t, $qualified, $upper, $empty]));
        self::assertSame(2, (new TargetNames())->duplicate([$t, $qualified, new QualifiedName(new Name('t')), $qualified]));
        self::assertSame(1, (new TargetNames())->duplicate([$qualified, new QualifiedName(new Name('t'), new Name('d'))]));
    }
}
