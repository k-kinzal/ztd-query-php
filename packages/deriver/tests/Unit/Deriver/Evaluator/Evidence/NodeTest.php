<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Evidence;

use Deriver\Deriver\Evaluator\Evidence\Node as Subject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class NodeTest extends TestCase
{
    public function testIdentityIncludesTheInputRole(): void
    {
        $leaf = new Subject(\Deriver\Deriver\Evaluator\Evidence\EvidenceKind::ContextAny);
        $left = new Subject(\Deriver\Deriver\Evaluator\Evidence\EvidenceKind::Operation, ['left' => $leaf]);
        $right = new Subject(\Deriver\Deriver\Evaluator\Evidence\EvidenceKind::Operation, ['right' => $leaf]);
        self::assertNotSame($left->id, $right->id);
    }

}
