<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Hint\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\ExecutionTimeHint;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;

#[CoversClass(ExecutionTimeHint::class)]
#[Small]
final class ExecutionTimeHintTest extends TestCase
{
    public function testNameAnswersMaxExecutionTime(): void
    {
        self::assertSame(HintName::MaxExecutionTime, (new ExecutionTimeHint('0'))->name());
    }

    public function testTextKeepsANumberBeyondAPhpInteger(): void
    {
        self::assertSame('MAX_EXECUTION_TIME(18446744073709551615)', (new ExecutionTimeHint('18446744073709551615'))->text());
    }

    public function testLeadingZerosAreRefused(): void
    {
        $this->expectExceptionMessage('The limit of MAX_EXECUTION_TIME is a decimal number without leading zeros.');

        new ExecutionTimeHint('007');
    }
}
