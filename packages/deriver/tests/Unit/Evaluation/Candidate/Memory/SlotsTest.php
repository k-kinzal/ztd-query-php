<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Memory;

use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class SlotsTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testReadRetainsAnExternalStateReference(): void
    {
        $e = F::evaluator('function target($x){return $x;}');
        $f = F::frame($e);
        $local = F::instruction($f, 'read');
        $a = new \Deriver\ControlFlow\Instruction('slot', 'model-state-address', $f->graph->body->source, operands:[$local->result], name:'example.state');
        $v = (new \Deriver\Evaluation\Candidate\Memory\Slots($e))->read($f, $a, 64);
        self::assertSame('state-read', $v->kind);
        self::assertSame('EXTERNAL_STATE', $v->operands[1]->attributes['reason']);
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testBeforeUsesOnlyTheRequestedAllocatedDefault(): void
    {
        $e = F::evaluator('function target(){return 1;}', new \Deriver\Project\Configuration(stateSlots:[new \Deriver\Model\State\StateSlot('example.count', 'int', Term::constant(3))]));
        $v = (new \Deriver\Evaluation\Candidate\Memory\Slots($e))->before(F::frame($e), new Term('object', 'allocation'), 'example.count', 0, 0, 64);
        self::assertSame(3, $v->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testEffectIgnoresUnmodeledUnrelatedSlots(): void
    {
        $e = F::evaluator('function target($x){return $x->missing();}');
        $f = F::frame($e);
        self::assertNull((new \Deriver\Evaluation\Candidate\Memory\Slots($e))->effect($f, F::instruction($f, 'invoke-method'), 'example.slot', 64));
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testInitialRetainsTheExternalReceiver(): void
    {
        $e = F::evaluator();
        $receiver = Term::parameter('external', 'Box');
        $v = (new \Deriver\Evaluation\Candidate\Memory\Slots($e))->initial(F::frame($e), $receiver, 'example.slot');
        self::assertSame($receiver, $v->operands[0]);
    }
}
