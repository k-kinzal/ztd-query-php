<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceGrantee;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\UniversalGrantee;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(SequenceGrantee::class)]
final class SequenceGranteeTest extends TestCase
{
    public function testToStringDistinguishesUniversalAndNominalRoles(): void
    {
        self::assertSame('PUBLIC', (new SequenceGrantee(UniversalGrantee::Everyone))->toString());
        self::assertSame('"PUBLIC"', (new SequenceGrantee(new SequencePrincipal(new Identifier('PUBLIC'))))->toString());
    }

    public function testCheckReleaseRequiresTheStockRoleInTheSelectedRelease(): void
    {
        $grantee = new SequenceGrantee(new SequencePrincipal(new Identifier('pg_maintain')));
        $grantee->checkRelease(GrammarRelease::PostgreSql172);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('The stock sequence owner must exist in this release.');
        $grantee->checkRelease(GrammarRelease::PostgreSql166);
    }
}
