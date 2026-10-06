<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Memory;

use Deriver\Evaluation\Candidate\Memory\Statics as Subject;
use Deriver\Project\Configuration;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class StaticsTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testIncomingPreservesUnknownHistory(): void
    {
        $engine = F::evaluator('function target(){static $n=0;return ++$n;}');
        $frame = F::frame($engine);
        $value = (new Subject())->incoming($engine, $frame, F::instruction($frame, 'local'), 64);
        self::assertSame('UNKNOWN_STATIC_HISTORY', $value->attributes['reason']);
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testPreviousFollowsTheFirstCallInAKnownPrefix(): void
    {
        $engine = F::evaluator('function nextValue(){static $n=0;return ++$n;}function target(){nextValue();return nextValue();}');
        self::assertSame(2, F::value($engine)->native());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testInitializedHonorsSuppliedStorage(): void
    {
        $engine = F::evaluator('function target(){static $n=0;return ++$n;}', new Configuration(environment:['static:target:n' => Term::constant(4)]));
        $frame = F::frame($engine);
        self::assertTrue((new Subject())->initialized($engine, $frame, F::instruction($frame, 'local'), 64)->native());
    }

}
