<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class BoundInstructionTest extends TestCase
{
    public function testCapturedContract(): void
    {
        $source = new \Deriver\Deriver\Sources\SourceRef('s', 'a.php', 0, 1);
        $value = \Deriver\Deriver\Evaluator\Term::constant(3);
        $instruction = new \Deriver\Deriver\Evaluator\Call\BoundInstruction('i', 'constant', $source, constant: $value);
        self::assertSame($value, $instruction->bound);
        self::assertSame(3, $instruction->constant?->literal);
    }
}
