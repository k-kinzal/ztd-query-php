<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Evidence;

use Deriver\Deriver\Evaluator\Evidence\EvidenceKind as Subject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class EvidenceKindTest extends TestCase
{
    public function testContextProjectionDistinguishesAlternativesFromCombinedDependencies(): void
    {
        $left = new \Deriver\Deriver\Evaluator\Evidence\Node(Subject::Call, attributes: ['caller' => 'App\\Left']);
        $right = new \Deriver\Deriver\Evaluator\Evidence\Node(Subject::Call, attributes: ['caller' => 'App\\Right']);
        $contexts = new \Deriver\Deriver\Evaluator\Evidence\Contexts();
        $choice = $contexts->project(new \Deriver\Deriver\Evaluator\Evidence\Node(Subject::Choice, ['left' => $left, 'right' => $right]));
        $operation = $contexts->project(new \Deriver\Deriver\Evaluator\Evidence\Node(Subject::Operation, ['left' => $left, 'right' => $right], attributes: ['operation' => 'vendor.custom-operation']));
        self::assertSame(Subject::ContextOr, $choice->kind);
        self::assertSame(Subject::ContextAnd, $operation->kind);
        self::assertNotSame($choice->id, $operation->id);
    }
}
