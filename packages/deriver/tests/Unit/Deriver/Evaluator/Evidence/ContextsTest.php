<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Evidence;

use Deriver\Deriver\Candidate\Alternative;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Evaluator\Evidence\Contexts as Subject;
use Deriver\Deriver\Evaluator\Evidence\EvidenceKind;
use Deriver\Deriver\Evaluator\Evidence\Node;
use Deriver\Deriver\Evaluator\TargetProfile;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ContextsTest extends TestCase
{
    public function testProjectRetainsAnOuterCaller(): void
    {
        $outer = new Node(EvidenceKind::ArgumentBinding, attributes: ['caller' => 'a', 'callee' => 'b']);
        $inner = new Node(EvidenceKind::Call, ['input' => $outer], attributes: ['caller' => 'b', 'callee' => 'c']);
        $context = (new Subject())->project($inner);
        self::assertTrue((new Alternative($inner, $context, new Snapshot('s', [], [], new TargetProfile(), hash('sha256', '[]'))))->matchesCaller('a'));
        self::assertFalse((new Alternative($inner, $context, new Snapshot('s', [], [], new TargetProfile(), hash('sha256', '[]'))))->matchesCaller('missing'));
    }
}
