<?php

declare(strict_types=1);

namespace Tests\Unit\Result;

use Deriver\Evidence\Node;
use Deriver\Result\Candidate as Subject;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateApi as A;

#[CoversNothing]
final class CandidateTest extends TestCase
{
    public function testClassifiesResidualExpressionsWithoutGuessingAValue(): void
    {
        $proof = A::proof(new Node('unexpanded'));
        $candidate = new Subject(new Term('concat', operands:[Term::constant('prefix'),Term::parameter('x')]), [$proof]);
        self::assertSame('partials', $candidate->type);
        self::assertSame('string', $candidate->type_name);
    }

}
