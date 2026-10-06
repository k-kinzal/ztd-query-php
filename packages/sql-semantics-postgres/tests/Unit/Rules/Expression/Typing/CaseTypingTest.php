<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\CaseTyping;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\CaseBranch;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\CaseExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\OperandMismatch;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(CaseTyping::class)]
#[Small]
final class CaseTypingTest extends TestCase
{
    public function testDeriveReportsANonBooleanCondition(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $case = new CaseExpression(null, [new CaseBranch(new Constant(new IntegerConstant('1')), new Constant(new IntegerConstant('2')))]);
        $fact = (new CaseTyping())->derive($derivation, $derivation->environment(), $case);
        self::assertEquals(new Known(Builtin::Int4), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
        self::assertEquals([new OperandMismatch('CASE/WHEN', 'boolean', 'integer')], $derivation->facts()->diagnostics);
    }
}
