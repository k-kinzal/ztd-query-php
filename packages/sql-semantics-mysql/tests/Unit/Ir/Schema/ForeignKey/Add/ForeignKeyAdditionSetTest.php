<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAction;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAddition;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAdditionSet;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyEffects;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyMatch;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(ForeignKeyAdditionSet::class)]
#[Small]
final class ForeignKeyAdditionSetTest extends TestCase
{
    public function testParentUsesTheOriginalLookupForARebuiltSelfReference(): void
    {
        $owner = NativeAdditionFixture::owner('parent');
        $key = NativeAdditionFixture::key($owner, 'old', IndexRole::Ordinary, 'id');
        $addition = NativeAdditionFixture::addition('Self_reference');
        (new ForeignKeyAdditionSet())->parent($owner, $addition, [$key], GrammarRelease::MySql910, true);

        self::assertSame('id', $addition->pairs[0]->parent->value);
    }

    public function testCheckAcceptsTheSettledPhysicalKey(): void
    {
        $parent = NativeAdditionFixture::owner('parent');
        $key = NativeAdditionFixture::key($parent, 'PRIMARY', IndexRole::Primary, 'id');
        $addition = NativeAdditionFixture::addition('Named', false, 'Named');
        (new ForeignKeyAdditionSet())->check(
            NativeAdditionFixture::owner('child'),
            [$addition],
            [$key],
            [],
            GrammarRelease::MySql910,
            new IndexChangePolicy(),
            false
        );

        self::assertSame('Named', $addition->createdIndex?->value);
    }

    public function testReleaseAcceptsSettledLegacyEffects(): void
    {
        $pair = NativeAdditionFixture::pair('owner_id', 'id');
        $effects = new ForeignKeyEffects(ForeignKeyAction::Restrict, ForeignKeyAction::Restrict);
        $addition = new ForeignKeyAddition(NativeAdditionFixture::owner('parent'), new Identifier('Ref'), [$pair], $effects);

        (new ForeignKeyAdditionSet())->release($addition, true);

        self::assertSame(ForeignKeyMatch::None, $addition->effects->match);
    }
}
