<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\MemberPurpose;

#[CoversClass(MemberPurpose::class)]
#[Small]
final class MemberPurposeTest extends TestCase
{
    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new MemberPurpose())->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesSearchOrOrdering(): void
    {
        $search = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new MemberPurpose())->render($search);
        $order = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new MemberPurpose(new DottedName([new Name('integer_ops')])))->render($order);
        self::assertSame(['FOR SEARCH', 'FOR ORDER BY integer_ops'], [(new Lexical())->join($search->pieces()), (new Lexical())->join($order->pieces())]);
    }
}
