<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Evidence;

use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evidence\Forest as Subject;
use Deriver\Deriver\Evaluator\Evidence\Provenance;
use Deriver\Deriver\Evaluator\Term;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use WeakReference;

#[CoversNothing]
final class ForestTest extends TestCase
{
    public function testRootRetainsTheConditionsOfUnresolvedAlternatives(): void
    {
        $value = (new Choices())->make([
            [Term::constant(1), ['flag' => true]],
            [Term::constant(2), ['flag' => false]],
        ]);

        $root = (new Subject(new Provenance()))->root($value);

        self::assertSame(true, $root->inputs['operand:0']->attributes['guard:flag']);
        self::assertSame(false, $root->inputs['operand:1']->attributes['guard:flag']);
    }

    public function testRootSharesRepeatedOperands(): void
    {
        $value = Term::constant(3);
        $root = (new Subject(new Provenance()))->root(Term::array([$value,$value]));
        self::assertSame($root->inputs['operand:0'], $root->inputs['operand:1']);
    }

    public function testIndependentTraversalsReuseSharedSubgraphsWithinOneAnalysis(): void
    {
        $proofs = new Provenance();
        $value = Term::array([Term::constant(3)]);
        $first = (new Subject($proofs))->root($value);
        $second = (new Subject($proofs))->root(Term::array([$value, $value]));
        self::assertSame($first, $second->inputs['operand:0']);
        self::assertSame($first, $second->inputs['operand:1']);
        self::assertNotSame($first, (new Subject(new Provenance()))->root($value));
    }

    public function testCachingDoesNotReplaceAttachedProofsOrRetainValues(): void
    {
        $proofs = new Provenance();
        $value = Term::constant(3);
        $plain = (new Subject($proofs))->root($value);
        $attached = new \Deriver\Deriver\Evaluator\Evidence\Node(\Deriver\Deriver\Evaluator\Evidence\EvidenceKind::SourceDefinition, attributes: ['definition' => 'other']);
        $derived = $proofs->attach($value, $attached);
        self::assertSame($attached, (new Subject($proofs))->root($derived));
        self::assertSame($plain, (new Subject($proofs))->root($value));
        $reference = WeakReference::create($value);
        unset($value);
        self::assertNull($reference->get());
    }

}
