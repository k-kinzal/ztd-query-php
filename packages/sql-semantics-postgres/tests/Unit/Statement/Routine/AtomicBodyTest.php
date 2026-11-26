<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\AtomicBody;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\ReturnStatement;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(AtomicBody::class)]
#[Medium]
final class AtomicBodyTest extends TestCase
{
    public function testDeriveClauseDerivesEachStatement(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $value = new BooleanLiteral(true);
        (new AtomicBody([new ReturnStatement($value)]))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($value));
    }

    public function testRenderEndsEachStatementWithASemicolon(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new AtomicBody([new ReturnStatement(new BooleanLiteral(true))]))->render($out);
        self::assertSame('BEGIN ATOMIC RETURN TRUE; END', (new Lexical())->join($out->pieces()));
    }

    public function testDropsEmptyStatements(): void
    {
        $operation = (new Inspection())->inspect('CREATE FUNCTION f() RETURNS int4 BEGIN ATOMIC ; SELECT 1; ; END');
        self::assertSame('CREATE FUNCTION f () RETURNS int4 BEGIN ATOMIC SELECT 1; END', $operation->toString());
    }

    public function testRejectsAnotherNode(): void
    {
        $this->expectExceptionMessage('A block holds statements and RETURN statements.');
        new AtomicBody([new DottedName([new Name('a')])]);
    }
}
