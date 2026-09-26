<?php

declare(strict_types=1);

namespace Tests\Integration;

use Deriver\Analyzer;
use Deriver\Api\Project\ProjectInput;
use Deriver\Api\Project\SourceFile;
use Deriver\Api\Query\ReturnQuery;
use Deriver\Api\Query\ValueQuery;
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
        $signature = new \Deriver\Model\Signature\Signature([
            new \Deriver\Model\Signature\Parameter('X', default: \Deriver\Value\Term::constant(1)),
            new \Deriver\Model\Signature\Parameter('x', default: \Deriver\Value\Term::constant(2)),
        ]);
        $plan = new \Deriver\Model\Plan\SemanticPlan([\Deriver\Model\Plan\Action::returns(\Deriver\Model\Plan\Expression::binary('-', \Deriver\Model\Plan\Expression::parameter('X'), \Deriver\Model\Plan\Expression::parameter('x')))]);
        $model = new \Tests\Fake\PlanModel(new \Deriver\Model\ModelDescriptor('example.remote', '1', '\\REMOTE', $signature), $plan);
        $session = \Tests\Fake\Analysis::session('<?php function target(){$f="\\\\remote";return [remote(),\\remote(),$f()];}', new \Deriver\Api\Project\Configuration(models:[$model]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame([-1,-1,-1], $result->normalOutcomes[0]->values['return']->native());
    }
}
