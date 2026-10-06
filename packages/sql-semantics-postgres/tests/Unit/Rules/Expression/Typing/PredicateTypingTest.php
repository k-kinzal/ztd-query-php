<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\PredicateTyping;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\PatternOperator;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(PredicateTyping::class)]
#[Small]
final class PredicateTypingTest extends TestCase
{
    public function testPatternIsBooleanForStrings(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        self::assertEquals(new Known(Builtin::Bool), (new PredicateTyping())->pattern($context, PatternOperator::ILike, new Known(Builtin::Varchar), new Known(Builtin::Unknown)));
    }

    public function testZoneSwapsTheTimestampTypes(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        self::assertEquals(new Known(Builtin::Timestamptz), (new PredicateTyping())->zone($context, new Known(Builtin::Timestamp), new Known(Builtin::Text)));
    }


    public function testStringReadsVarcharAsText(): void
    {
        self::assertSame([Builtin::Text, Builtin::Name, null], [(new PredicateTyping())->string(new Known(Builtin::Varchar)), (new PredicateTyping())->string(new Known(Builtin::Name)), (new PredicateTyping())->string(new Known(Builtin::Bytea))]);
    }
}
