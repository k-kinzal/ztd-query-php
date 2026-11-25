<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Dependency;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexDependencyKeys;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\PhysicalIndexKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;

#[CoversClass(IndexDependencyKeys::class)]
#[Small]
final class IndexDependencyKeysTest extends TestCase
{
    public function testSupportRetainsAnOriginalKeyAndAQualifiedAlternate(): void
    {
        $a = new Name('a');
        $old = new IndexDeclaration(new Name('old_key'), PhysicalIndexKind::Unique, true, leadingFields: [$a]);
        $alternate = new IndexDeclaration(new Name('other_key'), PhysicalIndexKind::Index, true, leadingFields: [$a]);
        $keys = new IndexDependencyKeys();
        $early = $keys->support([$a], true, GrammarRelease::MySql8044, [$old, $alternate], [$alternate]);
        $later = $keys->support([$a], true, GrammarRelease::MySql847, [$old, $alternate], [$alternate]);

        self::assertCount(2, $early);
        self::assertCount(1, $later);
        self::assertSame('old_key', $later[0]->value);
    }

    public function testPolicyDistinguishesBothSidesAndNativeRelease(): void
    {
        $keys = new IndexDependencyKeys();

        self::assertSame([false, true], $keys->policy(false, GrammarRelease::MySql5744));
        self::assertSame([false, false], $keys->policy(false, GrammarRelease::MySql8044));
        self::assertSame([true, false], $keys->policy(true, GrammarRelease::MySql847));
    }
}
