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
    public function testClassifiesConcreteNullAsAnalyzed(): void
    {
        $proof = A::proof(new Node('source-definition'));
        $candidate = new Subject(Term::constant(null), [$proof]);
        self::assertSame(\Deriver\Result\CandidateStatus::Analyzed, $candidate->type);
        self::assertSame('null', $candidate->type_name);
        self::assertNull($candidate->result);
    }

    public function testClassifiesResidualExpressionsWithoutGuessingAValue(): void
    {
        $proof = A::proof(new Node('unexpanded'));
        $candidate = new Subject(new Term('concat', operands:[Term::constant('prefix'),Term::parameter('x')]), [$proof]);
        self::assertSame(\Deriver\Result\CandidateStatus::Partials, $candidate->type);
        self::assertSame('string', $candidate->type_name);
    }

    public function testPreservesApplicationClassNamesAsTypeNames(): void
    {
        $proof = A::proof(new Node('unexpanded'));
        $candidate = new Subject(Term::parameter('repository', 'App\\Repository'), [$proof]);
        self::assertSame(\Deriver\Result\CandidateStatus::Partials, $candidate->type);
        self::assertSame('App\\Repository', $candidate->type_name);
    }

}
