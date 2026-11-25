<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Exchange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyConstraintIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\ForeignKeyExchangeSupport;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexAutoIncrement;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\ForeignKeyIndexSide;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexRemovalTarget;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(ForeignKeyExchangeSupport::class)]
#[Small]
final class ForeignKeyExchangeSupportTest extends TestCase
{
    public function testSurvivorsKeepsAnIdenticallyNamedKeyOnAnotherOwner(): void
    {
        $owner = NativeAdditionFixture::owner('child');
        $original = NativeAdditionFixture::key($owner, 'retired', IndexRole::Ordinary, 'owner_id');
        $decoy = NativeAdditionFixture::key(NativeAdditionFixture::owner('parent'), 'retired', IndexRole::Ordinary, 'id');
        [$retained, $removed] = (new ForeignKeyExchangeSupport())->survivors(
            $owner,
            [$original, $decoy],
            [new IndexRemovalTarget($original->identity, null)],
            new IndexChangePolicy()
        );

        self::assertSame([$decoy], $retained);
        self::assertSame(['retired' => true], $removed);
    }

    public function testIntroducedUsesTheAllocatedKeyAndItsOrderedFields(): void
    {
        $owner = NativeAdditionFixture::owner('child');
        $addition = NativeAdditionFixture::addition('reference', false, 'reference');
        $keys = (new ForeignKeyExchangeSupport())->introduced($owner, [$addition], []);

        self::assertCount(1, $keys);
        self::assertTrue($keys[0]->implicitForeignKey);
        self::assertSame('reference', $keys[0]->identity->name->value);
        self::assertSame('owner_id', $keys[0]->fields[0]->field->value);
    }

    public function testCheckUsesNewAutomaticPhysicalSupport(): void
    {
        $owner = NativeAdditionFixture::owner('child');
        $original = NativeAdditionFixture::key($owner, 'retired', IndexRole::Ordinary, 'owner_id');
        $field = new Identifier('owner_id');
        $constraint = new ForeignKeyConstraintIdentity($owner, new Identifier('original'));
        $removal = new IndexRemovalTarget($original->identity, new IndexAutoIncrement($field, []));
        $support = new ForeignKeyExchangeSupport();
        $support->check(
            $owner,
            [],
            [],
            [NativeAdditionFixture::addition('reference', false, 'reference')],
            [],
            [$removal],
            ['retired' => true],
            [new ForeignKeyIndexSide($constraint, [$field], false)],
            GrammarRelease::MySql910
        );

        self::assertSame('retired', $removal->index->name->value);
    }

    public function testRetainsMatchesAnOriginalConstraintByItsOwningCoordinate(): void
    {
        $owner = NativeAdditionFixture::owner('child');
        $identity = new ForeignKeyConstraintIdentity($owner, new Identifier('approved'));
        $side = new ForeignKeyIndexSide($identity, [new Identifier('owner_id')], false);
        (new ForeignKeyExchangeSupport())->retains($owner, [$side], [$identity], []);

        self::assertSame('approved', $side->constraint->symbol->value);
    }
}
