<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSessionRole;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\RevokeSequenceAccess;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceAccess;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceGrantDependency;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceGrantee;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequencesInSchemas;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\UniversalGrantee;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(RevokeSequenceAccess::class)]
final class RevokeSequenceAccessTest extends TestCase
{
    public function testToStringRendersCascadeAfterExplicitGrantor(): void
    {
        $target = new SequencesInSchemas([new Identifier('a'), new Identifier('b')]);
        $grantor = new SequencePrincipal(SchemaSessionRole::Effective);
        $access = new SequenceAccess($target, [], [new SequenceGrantee(UniversalGrantee::Everyone)], $grantor);
        $value = new RevokeSequenceAccess($access, true, SequenceGrantDependency::Cascade);
        self::assertSame('REVOKE GRANT OPTION FOR ALL ON ALL SEQUENCES IN SCHEMA "a", "b" FROM PUBLIC GRANTED BY CURRENT_USER CASCADE', $value->toString());
    }

    public function testProfileIdComesFromTheCheckedOperands(): void
    {
        $semantics = new Semantics('pg-16.6');
        $value = $semantics->analyze('REVOKE USAGE ON ALL SEQUENCES IN SCHEMA public FROM PUBLIC');
        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $value->profileId());
    }
}
