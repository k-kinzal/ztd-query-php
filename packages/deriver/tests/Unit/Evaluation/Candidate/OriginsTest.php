<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;
use Tests\Semantic\CandidateContractTest as C;

#[CoversNothing]
final class OriginsTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testParameterPreservesAnUnboundInput(): void
    {
        $e = F::evaluator('function target(int $x){return $x;}');
        $r = $e->returns(F::frame($e), 64);
        self::assertSame('$x', $r->literal);
        self::assertSame('int', $r->attributes['type']);
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testCallersKeepsActualsCorrelated(): void
    {
        $s = C::session('function target($x){observe($x);}function caller(){target(1);target(2);}');
        self::assertSame([1,2], C::native(C::argument($s)));
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testPropertyCollectsOrdinaryWrites(): void
    {
        $s = C::session('class B{public $x=1;function set(){$this->x=2;}function get(){observe($this->x);}}');
        self::assertSame([1,2], C::native(C::argument($s)));
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testUnboundKeepsTheDeclaredInputType(): void
    {
        $e = F::evaluator('function target(int $x){return $x;}');
        $f = F::frame($e);
        $v = (new \Deriver\Evaluation\Candidate\Origins($e))->unbound($f, F::instruction($f, 'local'), 'int', true);
        self::assertSame('int', $v->attributes['type']);
        self::assertSame('EXTERNAL_INPUT', $v->attributes['reason']);
    }
}
