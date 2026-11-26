<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Exchange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyConstraintIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyNativeKey;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\ForeignKeyExchangeSupport;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexAutoIncrement;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\ForeignKeyIndexSide;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexRemovalTarget;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
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

    public function testClusterPromotesTheFirstEligibleUniqueKeyAfterRemovingPrimary(): void
    {
        $owner = NativeAdditionFixture::owner('child');
        $primary = NativeAdditionFixture::key($owner, 'PRIMARY', IndexRole::Primary, 'id');
        $nullable = NativeAdditionFixture::key($owner, 'nullable', IndexRole::Unique, 'optional');
        $required = NativeAdditionFixture::key($owner, 'required', IndexRole::Unique, 'id');
        $candidate = new ForeignKeyNativeKey($required->identity, $required->fields, true, clusterEligible: true);

        [$layout, $selected] = (new ForeignKeyExchangeSupport())->cluster(
            [$nullable, $candidate],
            [new IndexRemovalTarget($primary->identity, null)]
        );

        self::assertSame($nullable, $layout[0]);
        self::assertSame($selected, $layout[1]->identity);
        self::assertSame(IndexRole::ClusteredUnique, $selected?->role);
        self::assertSame('required', $selected->name->value);
    }

    public function testOriginalAcceptsOneClusteredIdentityAndEligibleSecondaryKeys(): void
    {
        $owner = NativeAdditionFixture::owner('child');
        $primary = NativeAdditionFixture::key($owner, 'PRIMARY', IndexRole::Primary, 'id');
        $unique = NativeAdditionFixture::key($owner, 'required', IndexRole::Unique, 'owner_id');
        $candidate = new ForeignKeyNativeKey($unique->identity, $unique->fields, true, clusterEligible: true);
        (new ForeignKeyExchangeSupport())->original([$primary, $candidate]);

        self::assertTrue($candidate->clusterEligible);
    }

    public function testGeneratingRequiresTheAlternateToLeadWithTheSameField(): void
    {
        $owner = NativeAdditionFixture::owner('child');
        $first = NativeAdditionFixture::key($owner, 'old', IndexRole::Ordinary, 'seq');
        $retained = NativeAdditionFixture::key($owner, 'retained', IndexRole::Ordinary, 'seq', true);
        $sequence = new IndexAutoIncrement(new Identifier('seq'), [new Identifier('retained')]);
        $target = new IndexRemovalTarget($first->identity, $sequence);
        (new ForeignKeyExchangeSupport())->generating($target, $first, ['retained' => $retained], new NativeIdentifierCase());

        self::assertSame('seq', $sequence->field->value);
    }

    public function testSelfParentsAllowsOneExactUniqueReplacement(): void
    {
        $owner = NativeAdditionFixture::owner('parent');
        $new = NativeAdditionFixture::key($owner, 'promoted', IndexRole::Unique, 'id', true);
        $edge = NativeAdditionFixture::addition('self');
        (new ForeignKeyExchangeSupport())->selfParents($owner, [$edge], [$new], GrammarRelease::MySql910);

        self::assertSame('id', $edge->pairs[0]->parent->value);
    }
}
