<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Defaults;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSessionRole;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceGrantee;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceRight;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\UniversalGrantee;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults\SequenceDefaultPolicy;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(SequenceDefaultPolicy::class)]
final class SequenceDefaultPolicyTest extends TestCase
{
    public function testScopeSqlPreservesDetachedCreatorsAndSchemas(): void
    {
        $creator = new SequencePrincipal(SchemaSessionRole::Session);
        $schema = new Identifier('Log.Events');
        $policy = new SequenceDefaultPolicy([&$creator], [&$schema], [], [new SequenceGrantee(UniversalGrantee::Everyone)]);
        $creator = new SequencePrincipal(SchemaSessionRole::Effective);
        $schema = new Identifier('other');
        self::assertSame('ALTER DEFAULT PRIVILEGES FOR ROLE SESSION_USER IN SCHEMA "Log.Events"', $policy->scopeSql());
    }

    public function testProfileIdUsesTheSelectedRelease(): void
    {
        $policy = new SequenceDefaultPolicy([], [], [], [new SequenceGrantee(UniversalGrantee::Everyone)], GrammarRelease::PostgreSql166);
        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $policy->profileId());
    }

    public function testObjectSqlUsesTheCheckedRightsOrder(): void
    {
        $policy = new SequenceDefaultPolicy([], [], [SequenceRight::Update, SequenceRight::Usage], [new SequenceGrantee(UniversalGrantee::Everyone)]);
        self::assertSame('UPDATE, USAGE ON SEQUENCES', $policy->objectSql());
    }

    public function testRecipientsSqlSeparatesTheUniversalRoleFromAnIdentifier(): void
    {
        $policy = new SequenceDefaultPolicy([], [], [], [
            new SequenceGrantee(UniversalGrantee::Everyone),
            new SequenceGrantee(new SequencePrincipal(new Identifier('PUBLIC'))),
        ]);
        self::assertSame('PUBLIC, "PUBLIC"', $policy->recipientsSql());
    }

    public function testRejectsATemporaryAliasInTheDefaultScope(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('Default sequence privileges require named persistent schemas.');
        new SequenceDefaultPolicy([], [new Identifier('pg_temp')], [], [new SequenceGrantee(UniversalGrantee::Everyone)]);
    }
}
