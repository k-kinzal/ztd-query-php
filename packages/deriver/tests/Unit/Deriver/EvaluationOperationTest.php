<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver;

use Deriver\Deriver\EvaluationOperation;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class EvaluationOperationTest extends TestCase
{
    public function testCasesExposeSemanticOperationsWithoutInternalPhiInstructions(): void
    {
        self::assertSame(EvaluationOperation::Conditional, EvaluationOperation::from('conditional'));
        self::assertNotContains('phi', array_column(EvaluationOperation::cases(), 'value'));
        self::assertSame(EvaluationOperation::Function, EvaluationOperation::from('function'));
    }
}
