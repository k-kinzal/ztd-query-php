<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Evidence;

use Deriver\Evaluation\Candidate\Evidence\Expansion as Subject;
use Deriver\Result\Evidence\Node;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ExpansionTest extends TestCase
{
    public function testPreservesValueAndProofOwnership(): void
    {
        $proof = new Node('operation', attributes:['operation' => '+']);
        $value = new Term('constant', 3, evidence:$proof);
        $expansion = new Subject($value);
        self::assertSame($proof, $expansion->value->evidence);
    }

}
