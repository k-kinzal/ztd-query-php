<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Evidence;

use Closure;
use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evidence\EvidenceKind;
use Deriver\Deriver\Evaluator\Evidence\Node;
use Deriver\Deriver\Evaluator\Evidence\Provenance as Subject;
use Deriver\Deriver\Evaluator\Term;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ProvenanceTest extends TestCase
{
    /**
     * @param Closure(Subject, Term): Term $transform
     */
    #[DataProvider('providerProofOperations')]
    public function testEvidencePreservesSharedBranches(Term $value, Closure $transform): void
    {
        $proofs = new Subject();
        $wrapped = $transform($proofs, $value);
        self::assertCount(2, $wrapped->operands);
        self::assertSame($wrapped->operands[0]->operands[0], $wrapped->operands[1]->operands[0]);
        self::assertSame(['guard:branch:11' => true], $wrapped->operands[0]->attributes);
        self::assertSame(['guard:branch:11' => false], $wrapped->operands[1]->attributes);
        $leaf = array_reduce(range(0, 11), static fn (Term $node): Term => $node->operands[0]->operands[0], $wrapped);
        self::assertSame(7, $leaf->literal);
        self::assertNotNull($proofs->get($leaf));
        self::assertNull($proofs->get($value));
    }

    /**
     * @return iterable<string, array{Term, Closure(Subject, Term): Term}>
     */
    public static function providerProofOperations(): iterable
    {
        $value = Term::constant(7);
        for ($depth = 0; $depth < 12; $depth++) {
            $value = (new Choices())->make([[$value, ['branch:' . $depth => true]], [$value, ['branch:' . $depth => false]]]);
        }
        yield 'wrap' => [$value, static fn (Subject $proofs, Term $value): Term => $proofs->wrap($value, EvidenceKind::Operation)];
        yield 'operation' => [$value, static fn (Subject $proofs, Term $value): Term => $proofs->operation($value, [Term::constant(1)], 'selection')];
        yield 'evaluator' => [$value, static fn (Subject $proofs, Term $value): Term => $proofs->evaluator($value, new Node(EvidenceKind::EvaluatorApplication))];
    }

    public function testEqualValuesKeepTheirDistinctProofs(): void
    {
        $proofs = new Subject();
        $left = $proofs->wrap(Term::constant(7), EvidenceKind::SourceDefinition, attributes: ['definition' => 'left']);
        $right = $proofs->wrap(Term::constant(7), EvidenceKind::SourceDefinition, attributes: ['definition' => 'right']);
        $wrapped = $proofs->wrap((new Choices())->make([[$left, ['x' => true]], [$right, ['x' => false]]]), EvidenceKind::Operation);

        $leftResult = $wrapped->operands[0]->operands[0];
        $rightResult = $wrapped->operands[1]->operands[0];
        self::assertNotSame($leftResult, $rightResult);
        self::assertSame($proofs->get($left), $proofs->get($leftResult)?->inputs['value']);
        self::assertSame($proofs->get($right), $proofs->get($rightResult)?->inputs['value']);
    }

    public function testWrapPreservesAlternativeEvidence(): void
    {
        $proofs = new Subject();
        $value = (new Choices())->make([[Term::constant(1),['x' => true]],[Term::constant(1),['x' => false]]]);
        $wrapped = $proofs->wrap($value, EvidenceKind::Operation, attributes:['operation' => 'copy']);
        self::assertCount(2, $wrapped->operands);
        $evidence = $proofs->get($wrapped->operands[0]->operands[0]);
        self::assertNotNull($evidence);
        self::assertSame(EvidenceKind::Operation, $evidence->kind);
    }

    public function testAttachDoesNotChangeValueIdentity(): void
    {
        $proofs = new Subject();
        $value = Term::constant(3);
        $proof = new Node(EvidenceKind::Operation, attributes:['operation' => '+']);
        self::assertSame((new \Deriver\Deriver\Evaluator\Scalar\Identity())->key($value), (new \Deriver\Deriver\Evaluator\Scalar\Identity())->key($proofs->attach($value, $proof)));
    }

    public function testInputsRetainsOperandRoles(): void
    {
        $proofs = new Subject();
        self::assertSame(['operand:left'], array_keys($proofs->inputs(['left' => Term::constant(1)])));
    }

    public function testOperationRetainsTheResultAndItsInputs(): void
    {
        $proofs = new Subject();
        $input = $proofs->wrap(Term::constant(1), EvidenceKind::SourceDefinition);
        $result = $proofs->operation(Term::constant(2), [$input], '+');
        self::assertNotNull($proofs->get($result));
        self::assertSame($proofs->get($input), $proofs->get($result)->inputs['operand:0']);
    }

    public function testEvaluatorRetainsSelectionInputsAndOutput(): void
    {
        $proofs = new Subject();
        $evaluator = new Node(EvidenceKind::EvaluatorApplication, attributes:['id' => 'one','version' => '1','operation' => 'invoke','name' => 'f']);
        $value = $proofs->evaluator(Term::constant(7), $evaluator);
        self::assertNotNull($proofs->get($value));
        self::assertSame('one', $proofs->get($value)->attributes['id']);
        self::assertArrayHasKey('output', $proofs->get($value)->inputs);
    }

    public function testGetKeepsAnalysesIsolatedAndDoesNotMutateValues(): void
    {
        $first = new Subject();
        $second = new Subject();
        $value = Term::constant(1);
        $proof = new Node(EvidenceKind::SourceDefinition);
        $derived = $first->attach($value, $proof);
        self::assertSame($proof, $first->get($derived));
        self::assertNull($first->get($value));
        self::assertNull($second->get($derived));
        self::assertFalse(property_exists($derived, 'evidence'));
    }
}
