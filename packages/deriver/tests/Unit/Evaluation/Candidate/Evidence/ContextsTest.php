<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Evidence;

use Deriver\Evaluation\Candidate\Evidence\Contexts as Subject;
use Deriver\Result\Evidence\Node;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateApi as A;

#[CoversNothing]
final class ContextsTest extends TestCase
{
    public function testProjectRetainsAnOuterCaller(): void
    {
        $outer = new Node('argument-binding', attributes:['caller' => 'a','callee' => 'b']);
        $inner = new Node('call', ['input' => $outer], attributes:['caller' => 'b','callee' => 'c']);
        $context = (new Subject())->project($inner);
        self::assertTrue(A::proof($inner, $context)->matchesCaller('a'));
        self::assertFalse(A::proof($inner, $context)->matchesCaller('missing'));
    }

}
