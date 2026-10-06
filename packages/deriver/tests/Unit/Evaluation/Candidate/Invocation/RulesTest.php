<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Invocation;

use Deriver\Evaluation\Candidate\Invocation\Rules as Subject;
use Deriver\Project\Configuration;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateApi as A;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class RulesTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testApplySkipsUndemandedInputs(): void
    {
        $rule = \Deriver\Model\Expansion\Rule::constantFunction('count-one', '1', 'count', Term::constant(1));
        $result = A::returns('function target(){return count(expensive());}', new Configuration(expansionRules:[$rule]));
        self::assertSame(1, $result->candidates[0]->result);
        self::assertSame(0, $result->statistics->bodyExpansions);
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testArgumentMatchesNamedInputs(): void
    {
        $engine = F::evaluator('function target(){call(name:42);}');
        $frame = F::frame($engine);
        $call = F::instruction($frame, 'invoke');
        self::assertSame($call->arguments[0]->register, (new Subject($engine))->argument($call, 'name'));
        self::assertNull((new Subject($engine))->argument($call, 'absent'));
    }

}
