<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyKeyColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyIndexPrefix;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(ForeignKeyIndexPrefix::class)]
#[Small]
final class ForeignKeyIndexPrefixTest extends TestCase
{
    public function testKeysUsesTheExactTableCoordinate(): void
    {
        $owner = NativeAdditionFixture::owner('parent');
        $other = NativeAdditionFixture::owner('child');
        $first = NativeAdditionFixture::key($owner, 'PRIMARY', IndexRole::Primary, 'id');
        $decoy = NativeAdditionFixture::key($other, 'PRIMARY', IndexRole::Primary, 'id');

        self::assertSame(['primary' => $first], (new ForeignKeyIndexPrefix())->keys($owner, [$first, $decoy]));
    }

    public function testChildUsesCanonicalCaseAndWrittenDirection(): void
    {
        $pair = NativeAdditionFixture::pair('Owner_Id', 'Id', descending: true);

        self::assertSame([['owner_id', true]], (new ForeignKeyIndexPrefix())->child([$pair]));
    }

    public function testPhysicalUsesOriginalLeadingFieldDirection(): void
    {
        $owner = NativeAdditionFixture::owner('parent');
        $key = NativeAdditionFixture::key($owner, 'Native', IndexRole::Ordinary, 'Id', true);

        self::assertSame([['id', true]], (new ForeignKeyIndexPrefix())->physical($key));
    }

    public function testColumnsPreservesTheOrderedDirectionalPrefix(): void
    {
        $parts = [new ForeignKeyKeyColumn(new Identifier('Y'), true), new ForeignKeyKeyColumn(new Identifier('X'))];

        self::assertSame([['y', true], ['x', false]], (new ForeignKeyIndexPrefix())->columns($parts));
    }

    public function testRequireParentAcceptsAnOriginalUniqueKey(): void
    {
        $owner = NativeAdditionFixture::owner('parent');
        $key = NativeAdditionFixture::key($owner, 'PRIMARY', IndexRole::Primary, 'id');
        (new ForeignKeyIndexPrefix())->requireParent(
            $owner,
            [NativeAdditionFixture::pair('owner_id', 'id')],
            [$key],
            GrammarRelease::MySql910
        );

        self::assertSame('id', $key->fields[0]->field->value);
    }

    public function testParentMatchRequiresExactUniquenessWhenSelected(): void
    {
        $key = NativeAdditionFixture::key(NativeAdditionFixture::owner('parent'), 'Loose', IndexRole::Ordinary, 'id');
        $rules = new ForeignKeyIndexPrefix();

        self::assertTrue($rules->parentMatch(['id'], $key, ['id'], [], false));
        self::assertFalse($rules->parentMatch(['id'], $key, ['id'], [], true));
    }
}
