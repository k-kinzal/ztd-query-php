<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Authorization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSessionRole;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(SequencePrincipal::class)]
final class SequencePrincipalTest extends TestCase
{
    public function testToStringDistinguishesRoleSpellingFromTheSessionRole(): void
    {
        $role = new SequencePrincipal(new Identifier('SESSION_USER'));
        $session = new SequencePrincipal(SchemaSessionRole::Session);

        self::assertSame('OWNER TO "SESSION_USER"', $role->toString());
        self::assertSame('OWNER TO SESSION_USER', $session->toString());
    }

    public function testCheckReleaseRejectsAnUnknownReservedRole(): void
    {
        $role = new SequencePrincipal(new Identifier('pg_not_a_stock_grant'));
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('The stock sequence owner must exist in this release.');

        $role->checkRelease(GrammarRelease::PostgreSql166);
    }

    public function testReferenceQuotesANameEvenWhenItSpellsANativeRole(): void
    {
        self::assertSame('"CURRENT_USER"', (new SequencePrincipal(new Identifier('CURRENT_USER')))->reference());
        self::assertSame('CURRENT_USER', (new SequencePrincipal(SchemaSessionRole::Effective))->reference());
    }
}
