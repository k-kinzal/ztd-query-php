<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Notice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Notice\Completion;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseStop;

#[CoversClass(Completion::class)]
#[Small]
final class CompletionTest extends TestCase
{
    public function testRecordDistinguishesAContinuingErrorFromItsInstructionBoundary(): void
    {
        $state = new Completion();
        $error = new ParseFailure(new TooBigPrecision(7, 'CAST'));
        $state->record(30, $error);
        $state->record(40, Deprecated::BinaryOperator);

        self::assertSame([true, false, 30], [$state->failed, $state->aborted, $state->failedAt]);
        self::assertSame([[30, $error], [40, Deprecated::BinaryOperator]], $state->warnings);

        $state->record(42, ParseStop::ProgramStatement);
        self::assertTrue($state->aborted);
    }

    public function testRecordPreservesAnImmediateStop(): void
    {
        $state = new Completion();
        $state->record(30, new ParseFailure(new TooBigPrecision(7, 'CAST'), true));
        $state->record(31, Deprecated::National);

        self::assertTrue($state->aborted);
        self::assertSame(30, $state->failedAt);
    }

    public function testRecordContinuesGrammarNoticesWithoutEnteringContextualization(): void
    {
        $state = new Completion();
        $error = new ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet('AT LOCAL'));
        $state->record(30, $error);
        $state->record(40, Deprecated::AssignmentInExpression);
        $state->record(50, new ParseFailure(new TooBigPrecision(7, 'CAST'), true, \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization));

        self::assertSame([[30, $error], [40, Deprecated::AssignmentInExpression]], $state->warnings);
        self::assertTrue($state->syntaxFailed);
        self::assertFalse($state->aborted);
    }
}
