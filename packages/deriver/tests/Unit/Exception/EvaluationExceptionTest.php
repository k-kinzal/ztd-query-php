<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use Deriver\Exception\EvaluationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \Deriver\Exception\EvaluationException
 */
#[CoversClass(EvaluationException::class)]
#[Small]
final class EvaluationExceptionTest extends TestCase
{
    public function testGetMessagePreservesTheModelFailureCause(): void
    {
        $cause = new RuntimeException('source failure');
        $exception = new EvaluationException('evaluator failure', previous: $cause);
        self::assertSame('evaluator failure', $exception->getMessage());
        self::assertSame($cause, $exception->getPrevious());
    }
}
