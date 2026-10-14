<?php

declare(strict_types=1);

namespace Tests\Unit\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;

#[CoversClass(RejectedInput::class)]
#[Small]
final class RejectedInputTest extends TestCase
{
    public function testRetainsTheTypedDiagnosticAndBoundary(): void
    {
        $problem = new ProgramProblem(ProgramRule::BadSqlState, '00');
        $failure = new RejectedInput($problem, 20);

        self::assertSame($problem, $failure->problem);
        self::assertSame(20, $failure->end);
        self::assertSame("Bad SQLSTATE: '00'", $failure->getMessage());
        self::assertNull($failure->getPrevious());
        self::assertTrue($failure->aborts);
    }

    public function testContinuingRefusalsRetainTheirCompletionPolicy(): void
    {
        $failure = new RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet('AT LOCAL'), 37, false);

        self::assertFalse($failure->aborts);
        self::assertSame(37, $failure->end);
    }

    public function testRejectsANegativeBoundary(): void
    {
        $this->expectExceptionMessage('An input operand ends at a nonnegative byte offset.');

        new RejectedInput(new ProgramProblem(ProgramRule::BadSqlState, '00'), -1);
    }
}
