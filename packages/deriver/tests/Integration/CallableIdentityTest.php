<?php

declare (strict_types=1);

namespace Tests\Integration;

use Deriver\Deriver\Candidate;
use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\CallDecision;
use Deriver\Deriver\Evaluator\Call\CallDescription;
use Deriver\Deriver\Evaluator\Call\CallDescriptor;
use Deriver\Deriver\Evaluator\Call\CallEvaluator;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Plan\Action;
use Deriver\Deriver\Evaluator\Call\Plan\Expression;
use Deriver\Deriver\Evaluator\Call\Plan\SemanticPlan;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Declaration\Parameter;
use Deriver\Deriver\Evaluator\Declaration\Signature;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\Completion\Reachability;
use Deriver\Deriver\Evaluator\Execution\EvaluatorConfig as ExecutionEvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Request\StateQuery;
use Deriver\Deriver\Evaluator\Request\TupleQuery;
use Deriver\Deriver\Evaluator\Request\ValueQuery;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Execution\QueryExecution;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Graph\ExpressionRef;
use Deriver\Deriver\Lowerer\Graph\ObservationPhase;
use Deriver\Deriver\Lowerer\Graph\PointRef;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Providers\ProviderExpansion;
use Deriver\Deriver\Publication\CandidateQuery;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Selection\CallObservations;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Deriver\Sources\Sources;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use WeakMap;

#[CoversNothing]
#[Small]
final class CallableIdentityTest extends TestCase
{
    public function testScriptQueriesKeepCaseSensitiveCapturedPathsDistinct(): void
    {
        $prepared1Input = new Sources([new Source('A.php', '<?php return 1;'), new Source('a.php', '<?php return 2;')]);
        $prepared1Configuration = new DeriverConfig();
        $prepared1Contributions = new Contributions();
        $prepared1Target = new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision);
        $prepared1Providers = new ProviderExpansion($prepared1Input, array_map(Term::fromNative(...), $prepared1Configuration->environment), $prepared1Contributions);
        $contributions = $prepared1Providers->contributions;
        $prepared1Evaluators = new Evaluators(new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision), new Registrations($contributions->evaluators, $contributions->providers, $contributions->domains, $contributions->intrinsics, $contributions->stateSlots), $prepared1Configuration->standardEvaluators);
        $prepared1Settings = new EvaluatorConfig($prepared1Target, $prepared1Providers->environment, $prepared1Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared1Configuration->evaluators));
        $prepared1Identity = new SnapshotIdentity($prepared1Providers->input, $prepared1Settings, $prepared1Evaluators, $prepared1Providers->manifest);
        $snapshotId = $prepared1Identity->id;
        $input = $prepared1Providers->input;
        $parser = new Parser(new ParserConfig($prepared1Target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input, false));
        $prepared1Program = new Index($snapshotId, $trees);
        $prepared1Snapshot = $prepared1Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared1Program->diagnostics()));
        $session = new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot);
        $upper = $session->derive(new ReturnQuery('script:A.php'));
        $lower = $session->derive(new ReturnQuery('script:a.php'));
        $frontiers = array_reduce(iterator_to_array($upper, false), static function (array $frontiers, Candidate $candidate): array {
            if ($candidate->result instanceof Term) {
                $graph = $candidate->result;
                $snapshot = $candidate->evidence[0]->snapshot;
                $pending = [$graph];
                $seen = new WeakMap();
                $frontiers1 = [];
                while ($pending !== []) {
                    $node = array_pop($pending);
                    if (isset($seen[$node])) {
                        continue;
                    }
                    $seen[$node] = true;
                    array_push($pending, ...array_values($node->operands));
                    $reason = $node->attributes['reason'] ?? null;
                    if (!is_string($reason)) {
                        continue;
                    }
                    $source1 = new SourceRef($snapshot->id, (string) ($node->attributes['source'] ?? ''), (int) ($node->attributes['start'] ?? 0), (int) ($node->attributes['end'] ?? $node->attributes['start'] ?? 0));
                    $identity = (string) ($node->attributes['identity'] ?? (new Identity())->key($node));
                    $frontiers1[$identity] = new Frontier($reason, $source1, (string) $node->literal, ['value'], [$identity], $node, $reason);
                }
                array_push($frontiers, ...array_values($frontiers1));
            }
            return $frontiers;
        }, []);
        self::assertSame([], $frontiers);
        $frontiers2 = array_reduce(iterator_to_array($lower, false), static function (array $frontiers2, Candidate $candidate1): array {
            if ($candidate1->result instanceof Term) {
                $graph1 = $candidate1->result;
                $snapshot1 = $candidate1->evidence[0]->snapshot;
                $pending1 = [$graph1];
                $seen1 = new WeakMap();
                $frontiers3 = [];
                while ($pending1 !== []) {
                    $node1 = array_pop($pending1);
                    if (isset($seen1[$node1])) {
                        continue;
                    }
                    $seen1[$node1] = true;
                    array_push($pending1, ...array_values($node1->operands));
                    $reason1 = $node1->attributes['reason'] ?? null;
                    if (!is_string($reason1)) {
                        continue;
                    }
                    $source2 = new SourceRef($snapshot1->id, (string) ($node1->attributes['source'] ?? ''), (int) ($node1->attributes['start'] ?? 0), (int) ($node1->attributes['end'] ?? $node1->attributes['start'] ?? 0));
                    $identity1 = (string) ($node1->attributes['identity'] ?? (new Identity())->key($node1));
                    $frontiers3[$identity1] = new Frontier($reason1, $source2, (string) $node1->literal, ['value'], [$identity1], $node1, $reason1);
                }
                array_push($frontiers2, ...array_values($frontiers3));
            }
            return $frontiers2;
        }, []);
        self::assertSame([], $frontiers2);
        self::assertCount(1, $upper);
        self::assertCount(1, $lower);
        self::assertSame(1, $upper[0]->result);
        self::assertSame(2, $lower[0]->result);
    }
    public function testClosuresAtTheSameOffsetInDistinctPathsDoNotShareBodies(): void
    {
        $input = new Sources([new Source('A.php', '<?php function first(){return (fn()=>1)();}'), new Source('a.php', '<?php function other(){return (fn()=>2)();}'), new Source('main.php', '<?php function target(){return [first(),other()];}')]);
        $prepared2Input = $input;
        $prepared2Configuration = new DeriverConfig();
        $prepared2Contributions = new Contributions();
        $prepared2Target = new TargetProfile($prepared2Configuration->phpVersion, $prepared2Configuration->integerBits, $prepared2Configuration->floatPrecision);
        $prepared2Providers = new ProviderExpansion($prepared2Input, array_map(Term::fromNative(...), $prepared2Configuration->environment), $prepared2Contributions);
        $contributions = $prepared2Providers->contributions;
        $prepared2Evaluators = new Evaluators(new TargetProfile($prepared2Configuration->phpVersion, $prepared2Configuration->integerBits, $prepared2Configuration->floatPrecision), new Registrations($contributions->evaluators, $contributions->providers, $contributions->domains, $contributions->intrinsics, $contributions->stateSlots), $prepared2Configuration->standardEvaluators);
        $prepared2Settings = new EvaluatorConfig($prepared2Target, $prepared2Providers->environment, $prepared2Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared2Configuration->evaluators));
        $prepared2Identity = new SnapshotIdentity($prepared2Providers->input, $prepared2Settings, $prepared2Evaluators, $prepared2Providers->manifest);
        $snapshotId = $prepared2Identity->id;
        $input1 = $prepared2Providers->input;
        $parser = new Parser(new ParserConfig($prepared2Target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input1, false));
        $prepared2Program = new Index($snapshotId, $trees);
        $prepared2Snapshot = $prepared2Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared2Program->diagnostics()));
        $first = (new CandidateQuery(new DefinitionIndex($prepared2Program), $prepared2Settings, $prepared2Evaluators, $prepared2Snapshot))->derive(new ReturnQuery('target'));
        $prepared3Input = $input;
        $prepared3Configuration = new DeriverConfig();
        $prepared3Contributions = new Contributions();
        $prepared3Target = new TargetProfile($prepared3Configuration->phpVersion, $prepared3Configuration->integerBits, $prepared3Configuration->floatPrecision);
        $prepared3Providers = new ProviderExpansion($prepared3Input, array_map(Term::fromNative(...), $prepared3Configuration->environment), $prepared3Contributions);
        $contributions1 = $prepared3Providers->contributions;
        $prepared3Evaluators = new Evaluators(new TargetProfile($prepared3Configuration->phpVersion, $prepared3Configuration->integerBits, $prepared3Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared3Configuration->standardEvaluators);
        $prepared3Settings = new EvaluatorConfig($prepared3Target, $prepared3Providers->environment, $prepared3Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared3Configuration->evaluators));
        $prepared3Identity = new SnapshotIdentity($prepared3Providers->input, $prepared3Settings, $prepared3Evaluators, $prepared3Providers->manifest);
        $snapshotId1 = $prepared3Identity->id;
        $input2 = $prepared3Providers->input;
        $parser1 = new Parser(new ParserConfig($prepared3Target->version));
        $trees1 = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input2, false));
        $prepared3Program = new Index($snapshotId1, $trees1);
        $prepared3Snapshot = $prepared3Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared3Program->diagnostics()));
        $second = (new CandidateQuery(new DefinitionIndex($prepared3Program), $prepared3Settings, $prepared3Evaluators, $prepared3Snapshot))->derive(new ReturnQuery('target'));
        $frontiers = array_reduce(iterator_to_array($first, false), static function (array $frontiers, Candidate $candidate): array {
            if ($candidate->result instanceof Term) {
                $graph = $candidate->result;
                $snapshot = $candidate->evidence[0]->snapshot;
                $pending = [$graph];
                $seen = new WeakMap();
                $frontiers1 = [];
                while ($pending !== []) {
                    $node = array_pop($pending);
                    if (isset($seen[$node])) {
                        continue;
                    }
                    $seen[$node] = true;
                    array_push($pending, ...array_values($node->operands));
                    $reason = $node->attributes['reason'] ?? null;
                    if (!is_string($reason)) {
                        continue;
                    }
                    $source2 = new SourceRef($snapshot->id, (string) ($node->attributes['source'] ?? ''), (int) ($node->attributes['start'] ?? 0), (int) ($node->attributes['end'] ?? $node->attributes['start'] ?? 0));
                    $identity = (string) ($node->attributes['identity'] ?? (new Identity())->key($node));
                    $frontiers1[$identity] = new Frontier($reason, $source2, (string) $node->literal, ['value'], [$identity], $node, $reason);
                }
                array_push($frontiers, ...array_values($frontiers1));
            }
            return $frontiers;
        }, []);
        self::assertSame([], $frontiers);
        $frontiers2 = array_reduce(iterator_to_array($second, false), static function (array $frontiers2, Candidate $candidate1): array {
            if ($candidate1->result instanceof Term) {
                $graph1 = $candidate1->result;
                $snapshot1 = $candidate1->evidence[0]->snapshot;
                $pending1 = [$graph1];
                $seen1 = new WeakMap();
                $frontiers3 = [];
                while ($pending1 !== []) {
                    $node1 = array_pop($pending1);
                    if (isset($seen1[$node1])) {
                        continue;
                    }
                    $seen1[$node1] = true;
                    array_push($pending1, ...array_values($node1->operands));
                    $reason1 = $node1->attributes['reason'] ?? null;
                    if (!is_string($reason1)) {
                        continue;
                    }
                    $source3 = new SourceRef($snapshot1->id, (string) ($node1->attributes['source'] ?? ''), (int) ($node1->attributes['start'] ?? 0), (int) ($node1->attributes['end'] ?? $node1->attributes['start'] ?? 0));
                    $identity1 = (string) ($node1->attributes['identity'] ?? (new Identity())->key($node1));
                    $frontiers3[$identity1] = new Frontier($reason1, $source3, (string) $node1->literal, ['value'], [$identity1], $node1, $reason1);
                }
                array_push($frontiers2, ...array_values($frontiers3));
            }
            return $frontiers2;
        }, []);
        self::assertSame([], $frontiers2);
        self::assertSame([1, 2], $first[0]->result);
        self::assertSame([1, 2], $second[0]->result);
    }
    public function testFullyQualifiedReturnSelectorsObserveTheirResolvedFunction(): void
    {
        $input = new Sources([new Source('fixture.php', '<?php namespace N;function target(){return 3;}')]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $session = new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics())));
        $result = $session->derive(new ReturnQuery('\N\TARGET'));
        self::assertSame(Reachability::MayReach, $result->reachability);
        self::assertSame([], $result->frontiers);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(3, $result->normalOutcomes[0]->values['return']->native());
    }
    public function testCallSelectorsFindClosuresInEveryCaseSensitivePath(): void
    {
        $prepared4Input = new Sources([new Source('A.php', '<?php function first(){return fn()=>sink(1);}'), new Source('a.php', '<?php function other(){return fn()=>sink(2);}')]);
        $prepared4Configuration = new DeriverConfig();
        $prepared4Contributions = new Contributions();
        $prepared4Target = new TargetProfile($prepared4Configuration->phpVersion, $prepared4Configuration->integerBits, $prepared4Configuration->floatPrecision);
        $prepared4Providers = new ProviderExpansion($prepared4Input, array_map(Term::fromNative(...), $prepared4Configuration->environment), $prepared4Contributions);
        $contributions = $prepared4Providers->contributions;
        $prepared4Evaluators = new Evaluators(new TargetProfile($prepared4Configuration->phpVersion, $prepared4Configuration->integerBits, $prepared4Configuration->floatPrecision), new Registrations($contributions->evaluators, $contributions->providers, $contributions->domains, $contributions->intrinsics, $contributions->stateSlots), $prepared4Configuration->standardEvaluators);
        $prepared4Settings = new EvaluatorConfig($prepared4Target, $prepared4Providers->environment, $prepared4Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared4Configuration->evaluators));
        $prepared4Identity = new SnapshotIdentity($prepared4Providers->input, $prepared4Settings, $prepared4Evaluators, $prepared4Providers->manifest);
        $snapshotId = $prepared4Identity->id;
        $input = $prepared4Providers->input;
        $parser = new Parser(new ParserConfig($prepared4Target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input, false));
        $prepared4Program = new Index($snapshotId, $trees);
        $prepared4Snapshot = $prepared4Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared4Program->diagnostics()));
        $session = new CandidateQuery(new DefinitionIndex($prepared4Program), $prepared4Settings, $prepared4Evaluators, $prepared4Snapshot);
        $calls = (new CallObservations($prepared4Program, $prepared4Evaluators))->find('\SINK');
        self::assertCount(2, $calls);
        self::assertSame('A.php', $calls[0]->source->path);
        self::assertSame('a.php', $calls[1]->source->path);
        $upper = $session->derive(new ValueQuery($calls[0]->argument(0)));
        $lower = $session->derive(new ValueQuery($calls[1]->argument(0)));
        self::assertSame(1, $upper[0]->result);
        self::assertSame(2, $lower[0]->result);
    }
    public function testEvaluatorCallsNormalizeQualifiedNamesWithoutMergingParameterDefaults(): void
    {
        $signature = new Signature([new Parameter('X', default: Term::constant(1)), new Parameter('x', default: Term::constant(2))]);
        $plan = new SemanticPlan([Action::returns(Expression::binary('-', Expression::parameter('X'), Expression::parameter('x')))]);
        $evaluator = new class (new CallDescriptor('example.remote', '1', '\REMOTE', $signature), $plan) implements CallEvaluator {
            /**
             * @param CallDescriptor $metadata Stable evaluator registration
             * @param SemanticPlan $plan Ordered API semantics
             */
            public function __construct(public readonly CallDescriptor $metadata, public readonly SemanticPlan $plan)
            {
            }
            /**
             * @return CallDescriptor Stable registration
             */
            #[Override]
            public function descriptor(): CallDescriptor
            {
                return $this->metadata;
            }
            /**
             * @param CallDescription $call Selected target
             * @return CallDecision Declarative fixture semantics
             */
            #[Override]
            public function evaluate(CallDescription $call): CallDecision
            {
                return CallDecision::handled($this->plan);
            }
        };
        $input = new Sources([new Source('fixture.php', '<?php function target(){$f="\\\\remote";return [remote(),\remote(),$f()];}')]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions(evaluators: [$evaluator]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $session = new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics())));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame([-1, -1, -1], $result->normalOutcomes[0]->values['return']->native());
    }
    #[DataProvider('providerObservationOwners')]
    public function testAllObservationKindsUseTheResolvedCallableIdentity(string $owner): void
    {
        $input = new Sources([new Source('fixture.php', '<?php namespace N;function sink($x){return $x;}function target(){$x=3;return \N\sink($x);}')]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $session = new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics())));
        $call = (new CallObservations($session->program, $session->evaluators))->find('N\sink')[0];
        $argument = $call->argument(0);
        $expression = new ExpressionRef($argument->source, $owner, $argument->register);
        $point = new PointRef($call->source, $owner, $call->instruction, ObservationPhase::Invocation);
        $value = $session->derive(new ValueQuery($expression));
        $state = $session->derive(new StateQuery($point, 'x'));
        $tuple = $session->derive(new TupleQuery($point, ['argument' => $argument]));
        self::assertSame(Reachability::MayReach, $value->reachability);
        self::assertSame(Reachability::MayReach, $state->reachability);
        self::assertSame(Reachability::MayReach, $tuple->reachability);
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
        yield 'declaration spelling' => ['N\target'];
        yield 'case variant' => ['n\TARGET'];
        yield 'fully qualified variant' => ['\N\TARGET'];
    }
    public function testNamespacedCallSelectorsUseTheResolvedSourceName(): void
    {
        $input = new Sources([new Source('fixture.php', '<?php namespace N;function sink($x){return $x;}function target(){return sink(3);}')]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $session = new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics())));
        $calls = (new CallObservations($session->program, $session->evaluators))->find('\N\SINK');
        self::assertCount(1, $calls);
        self::assertSame('N\sink', $calls[0]->target);
        self::assertSame('N\target', $calls[0]->callable);
        $result = $session->derive(new ValueQuery($calls[0]->argument(0)));
        self::assertSame([], $result->frontiers);
        self::assertSame(3, $result->normalOutcomes[0]->values['value']->native());
    }
}
