<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Project\EntryPoint;
use Deriver\Query\QueryScope;
use Deriver\Query\StateQuery;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

/**
 * Preserves exceptional paths independently of reaching an observation point.
 */
#[CoversNothing]
#[Small]
final class ObservationSemanticsTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testUncaughtFailureBeforeAValueIsRetainedWithoutMakingThePointReachable(): void
    {
        $session = Analysis::session('<?php function fail(){throw new RuntimeException("stop");} function target(){ $x=2; fail(); sink($x); }');
        $call = $session->callsTo('sink')[0];
        $result = $session->derive(new ValueQuery($call->argument(0)));
        self::assertSame([], $result->normalOutcomes);
        self::assertCount(1, $result->exceptionalOutcomes);
        self::assertSame('RuntimeException', $result->exceptionalOutcomes[0]->exception->attributes['class']);
        self::assertSame(2, $result->exceptionalOutcomes[0]->state['x']->native());
        self::assertNotEmpty($result->exceptionalOutcomes[0]->evidence);
        self::assertSame('unreachable', $result->reachability);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testEntrypointFailureBeforeCallingTheOwnerIsRetained(): void
    {
        $session = Analysis::session('<?php function target(){ $x=3; sink($x); } function entry(){ $x=9; throw new Error("early"); target(); }');
        $call = $session->callsTo('sink')[0];
        $result = $session->derive(new StateQuery($call->beforeInvocation(), 'x', scope: QueryScope::fromEntrypoints([new EntryPoint('entry')])));
        self::assertSame([], $result->normalOutcomes);
        self::assertCount(1, $result->exceptionalOutcomes);
        self::assertSame(9, $result->exceptionalOutcomes[0]->state['x']->native());
        self::assertSame('unreachable', $result->reachability);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCaughtFailureDoesNotBecomeAnExceptionalQueryExit(): void
    {
        $session = Analysis::session('<?php function fail(){throw new Error("caught");} function sink($x){} function target(){try{fail();}catch(Throwable $e){} sink(4);}');
        $call = $session->callsTo('sink')[0];
        $result = $session->derive(new TupleQuery($call->beforeInvocation(), ['x' => $call->argument(0)]));
        self::assertSame(4, $result->normalOutcomes[0]->values['x']->native());
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertSame('may-reach', $result->reachability);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testFailureAfterANestedObservationDoesNotBecomeAPreObservationExit(): void
    {
        $session = Analysis::session('<?php function sink($x){} function target(){sink(7);} function entry(){target(); throw new Error("later");}');
        $call = $session->callsTo('sink')[0];
        $result = $session->derive(new ValueQuery($call->argument(0), scope: QueryScope::fromEntrypoints([new EntryPoint('entry')])));
        self::assertSame(7, $result->normalOutcomes[0]->values['value']->native());
        self::assertSame([], $result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testEntrypointBindingFailureIsSeparateFromReachability(): void
    {
        $session = Analysis::session('<?php function target(int $x){sink($x);}');
        $call = $session->callsTo('sink')[0];
        $result = $session->derive(new StateQuery($call->beforeInvocation(), 'x', scope: QueryScope::fromEntrypoints([new EntryPoint('target')])));
        self::assertSame([], $result->normalOutcomes);
        self::assertSame('ArgumentCountError', $result->exceptionalOutcomes[0]->exception->literal);
        self::assertSame('unreachable', $result->reachability);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testThrowingDefaultIsReportedOnceAtTheEntryBoundary(): void
    {
        $session = Analysis::session('<?php class B{function __construct(){throw new Error("default");}} function target($x=new B){sink($x);}');
        $call = $session->callsTo('sink')[0];
        $result = $session->derive(new ValueQuery($call->argument(0), scope: QueryScope::fromEntrypoints([new EntryPoint('target')])));
        self::assertCount(1, $result->exceptionalOutcomes);
        self::assertSame([], $result->normalOutcomes);
        self::assertSame('unreachable', $result->reachability);
    }
}
