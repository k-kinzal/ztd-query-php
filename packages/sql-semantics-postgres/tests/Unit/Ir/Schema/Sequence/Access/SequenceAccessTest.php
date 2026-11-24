<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceAccess;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceGrantee;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceRight;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequencesInSchemas;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\UniversalGrantee;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(SequenceAccess::class)]
final class SequenceAccessTest extends TestCase
{
    public function testObjectSqlPreservesDetachedRights(): void
    {
        $right = SequenceRight::Usage;
        $role = new SequenceGrantee(UniversalGrantee::Everyone);
        $target = new SequencesInSchemas([new Identifier('public')]);
        $value = new SequenceAccess($target, [&$right], [&$role]);
        $right = SequenceRight::Update;
        $role = new SequenceGrantee(new SequencePrincipal(new Identifier('app')));
        self::assertSame('USAGE ON ALL SEQUENCES IN SCHEMA "public"', $value->objectSql());
        self::assertSame('PUBLIC', $value->recipientsSql());
    }

    public function testProfileIdUsesTheSelectedRelease(): void
    {
        $target = new SequencesInSchemas([new Identifier('public')]);
        $access = new SequenceAccess($target, [], [new SequenceGrantee(UniversalGrantee::Everyone)], release: GrammarRelease::PostgreSql166);
        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $access->profileId());
    }

    public function testHasEffectiveRightsDistinguishesAllFromTheLegacyRight(): void
    {
        $target = new SequencesInSchemas([new Identifier('public')]);
        $recipients = [new SequenceGrantee(UniversalGrantee::Everyone)];
        self::assertTrue((new SequenceAccess($target, [], $recipients))->hasEffectiveRights());
        self::assertFalse((new SequenceAccess($target, [SequenceRight::LegacyRule], $recipients))->hasEffectiveRights());
    }

    public function testRecipientsSqlPreservesOrderedRoles(): void
    {
        $target = new SequencesInSchemas([new Identifier('public')]);
        $role = new SequenceGrantee(UniversalGrantee::Everyone);
        $access = new SequenceAccess($target, [], [$role, new SequenceGrantee(new SequencePrincipal(new Identifier('PUBLIC')))]);
        self::assertSame('PUBLIC, "PUBLIC"', $access->recipientsSql());
    }

    public function testGrantorSqlPreservesTheExplicitRole(): void
    {
        $target = new SequencesInSchemas([new Identifier('public')]);
        $access = new SequenceAccess($target, [], [new SequenceGrantee(UniversalGrantee::Everyone)], new SequencePrincipal(new Identifier('role')));
        self::assertSame(' GRANTED BY "role"', $access->grantorSql());
    }
}
