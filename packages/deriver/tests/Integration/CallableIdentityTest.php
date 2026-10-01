<?php

declare(strict_types=1);

namespace Tests\Integration;

use Deriver\Analyzer;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use Deriver\Reference\ExpressionRef;
use Deriver\Reference\PointRef;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class CallableIdentityTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testScriptQueriesKeepCaseSensitiveCapturedPathsDistinct(): void
    {
        $session = (new Analyzer())->open(new ProjectInput([new SourceFile('A.php', '<?php return 1;'),new SourceFile('a.php', '<?php return 2;')]));
        $upper = $session->derive(new ReturnQuery('script:A.php'));
        $lower = $session->derive(new ReturnQuery('script:a.php'));
        self::assertSame([], $upper->projectDiagnostics);
        self::assertSame([], $lower->projectDiagnostics);
        self::assertSame([], $upper->frontiers);
        self::assertSame([], $lower->frontiers);
        self::assertCount(1, $upper->normalOutcomes);
        self::assertCount(1, $lower->normalOutcomes);
        self::assertSame(1, $upper->normalOutcomes[0]->values['return']->native());
        self::assertSame(2, $lower->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testClosuresAtTheSameOffsetInDistinctPathsDoNotShareBodies(): void
    {
        $input = new ProjectInput([
            new SourceFile('A.php', '<?php function first(){return (fn()=>1)();}'),
            new SourceFile('a.php', '<?php function other(){return (fn()=>2)();}'),
            new SourceFile('main.php', '<?php function target(){return [first(),other()];}'),
        ]);
        $analyzer = new Analyzer();
        $first = $analyzer->open($input)->derive(new ReturnQuery('target'));
        $second = $analyzer->open($input)->derive(new ReturnQuery('target'));
        self::assertSame([], $first->projectDiagnostics);
        self::assertSame([], $second->projectDiagnostics);
        self::assertSame([], $first->frontiers);
        self::assertSame([], $second->frontiers);
        self::assertSame([1,2], $first->normalOutcomes[0]->values['return']->native());
        self::assertSame([1,2], $second->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testFullyQualifiedReturnSelectorsObserveTheirResolvedFunction(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php namespace N;function target(){return 3;}');
        $result = $session->derive(new ReturnQuery('\\N\\TARGET'));
        self::assertSame('may-reach', $result->reachability);
        self::assertSame([], $result->frontiers);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(3, $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCallSelectorsFindClosuresInEveryCaseSensitivePath(): void
    {
        $session = (new Analyzer())->open(new ProjectInput([
            new SourceFile('A.php', '<?php function first(){return fn()=>sink(1);}'),
            new SourceFile('a.php', '<?php function other(){return fn()=>sink(2);}'),
        ]));
        $calls = $session->callsTo('\\SINK');
        self::assertCount(2, $calls);
        self::assertSame('A.php', $calls[0]->source->path);
        self::assertSame('a.php', $calls[1]->source->path);
        $upper = $session->derive(new ValueQuery($calls[0]->argument(0)));
        $lower = $session->derive(new ValueQuery($calls[1]->argument(0)));
        self::assertSame(1, $upper->normalOutcomes[0]->values['value']->native());
        self::assertSame(2, $lower->normalOutcomes[0]->values['value']->native());
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testModelCallsNormalizeQualifiedNamesWithoutMergingParameterDefaults(): void
    {
        $signature = new Signature([
            new Parameter('X', default: Term::constant(1)),
            new Parameter('x', default: Term::constant(2)),
        ]);
        $plan = new SemanticPlan([Action::returns(Expression::binary('-', Expression::parameter('X'), Expression::parameter('x')))]);
        $model = new \Tests\Fake\PlanModel(new ModelDescriptor('example.remote', '1', '\\REMOTE', $signature), $plan);
        $session = \Tests\Fake\Analysis::session('<?php function target(){$f="\\\\remote";return [remote(),\\remote(),$f()];}', new Configuration(models:[$model]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame([-1,-1,-1], $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerObservationOwners')]
    public function testAllObservationKindsUseTheResolvedCallableIdentity(string $owner): void
    {
        $session = \Tests\Fake\Analysis::session('<?php namespace N;function sink($x){return $x;}function target(){$x=3;return \N\sink($x);}');
        $call = $session->callsTo('N\\sink')[0];
        $argument = $call->argument(0);
        $expression = new ExpressionRef($argument->source, $owner, $argument->register);
        $point = new PointRef($call->source, $owner, $call->instruction, 'invocation');
        $value = $session->derive(new ValueQuery($expression));
        $state = $session->derive(new StateQuery($point, 'x'));
        $tuple = $session->derive(new TupleQuery($point, ['argument' => $argument]));
        self::assertSame('may-reach', $value->reachability);
        self::assertSame('may-reach', $state->reachability);
        self::assertSame('may-reach', $tuple->reachability);
        self::assertSame([], $value->frontiers);
        self::assertSame([], $state->frontiers);
        self::assertSame([], $tuple->frontiers);
        self::assertSame(3, $value->normalOutcomes[0]->values['value']->native());
        self::assertSame(3, $state->normalOutcomes[0]->values['state']->native());
        self::assertSame(3, $tuple->normalOutcomes[0]->values['argument']->native());
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function providerObservationOwners(): iterable
    {
        yield 'declaration spelling' => ['N\\target'];
        yield 'case variant' => ['n\\TARGET'];
        yield 'fully qualified variant' => ['\\N\\TARGET'];
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testNamespacedCallSelectorsUseTheResolvedSourceName(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php namespace N;function sink($x){return $x;}function target(){return sink(3);}');
        $calls = $session->callsTo('\\N\\SINK');
        self::assertCount(1, $calls);
        self::assertSame('N\\sink', $calls[0]->target);
        self::assertSame('N\\target', $calls[0]->callable);
        $result = $session->derive(new ValueQuery($calls[0]->argument(0)));
        self::assertSame([], $result->frontiers);
        self::assertSame(3, $result->normalOutcomes[0]->values['value']->native());
    }
}
