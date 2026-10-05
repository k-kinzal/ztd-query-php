<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Evidence;

use Deriver\Evaluation\Candidate\Choices;
use Deriver\Evaluation\Candidate\Evidence\Provenance as Subject;
use Deriver\Result\Evidence\Node;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ProvenanceTest extends TestCase
{
    public function testWrapPreservesAlternativeEvidence(): void
    {
        $value = (new Choices())->make([[Term::constant(1),['x' => true]],[Term::constant(1),['x' => false]]]);
        $wrapped = Subject::wrap($value, 'operation', attributes:['operation' => 'copy']);
        self::assertCount(2, $wrapped->operands);
        $evidence = $wrapped->operands[0]->operands[0]->evidence;
        self::assertNotNull($evidence);
        self::assertSame('operation', $evidence->kind);
    }

    public function testAttachDoesNotChangeValueIdentity(): void
    {
        $value = Term::constant(3);
        $proof = new Node('operation', attributes:['operation' => '+']);
        self::assertSame((new \Deriver\Value\Identity())->key($value), (new \Deriver\Value\Identity())->key(Subject::attach($value, $proof)));
    }

    public function testInputsRetainsOperandRoles(): void
    {
        self::assertSame(['operand:left'], array_keys(Subject::inputs(['left' => Term::constant(1)])));
    }

    public function testOperationRetainsTheResultAndItsInputs(): void
    {
        $input = Subject::wrap(Term::constant(1), 'source-definition');
        $result = Subject::operation(Term::constant(2), [$input], '+');
        self::assertNotNull($result->evidence);
        self::assertSame($input->evidence, $result->evidence->inputs['operand:0']);
    }

    public function testModelRetainsSelectionInputsAndOutput(): void
    {
        $model = new Node('model-application', attributes:['id' => 'one','version' => '1','operation' => 'invoke','name' => 'f']);
        $value = Subject::model(Term::constant(7), $model);
        self::assertNotNull($value->evidence);
        self::assertSame('one', $value->evidence->attributes['id']);
        self::assertArrayHasKey('output', $value->evidence->inputs);
    }

}
