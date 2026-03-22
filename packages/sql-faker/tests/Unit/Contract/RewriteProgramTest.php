<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\RewriteProgram;
use SqlFaker\Contract\RewriteStep;

#[CoversClass(RewriteProgram::class)]
#[UsesClass(RewriteStep::class)]
final class RewriteProgramTest extends TestCase
{
    public function testExposesStepIdentifiersInOrder(): void
    {
        $program = new RewriteProgram([
            new RewriteStep('first', 'first step'),
            new RewriteStep('second', 'second step'),
        ]);

        self::assertSame(['first', 'second'], $program->stepIds());
    }

    public function testRejectsAnEmptyProgram(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rewrite program must contain at least one step.');

        new RewriteProgram([]);
    }
}
