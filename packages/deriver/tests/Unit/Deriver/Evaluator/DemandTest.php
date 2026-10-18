<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator;

use Deriver\Deriver\Candidate;
use Deriver\Deriver\Candidate\CandidateStatus;
use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Projection;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Request\TupleQuery;
use Deriver\Deriver\Evaluator\Request\ValueQuery;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Declaration\Index;
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
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use WeakMap;

#[CoversNothing]
final class DemandTest extends TestCase
{
    public function testFramesKeepExplicitBindingsOutOfOtherQueries(): void
    {
        $prepared1Input = new Sources([new Source('candidate.php', '<?php ' . 'function target($x){observe($x);}')]);
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
        $s = new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot);
        $site = (new CallObservations($prepared1Program, $prepared1Evaluators))->find('observe')[0];
        $scope = QueryScope::fromEntrypoints([new EntryPoint('target', [Term::constant(3)])]);
        $r = $s->derive(new ValueQuery($site->argument(0), scope: $scope));
        $slot = 'value';
        self::assertSame([3], array_map(static fn (Term $value) => $value->native(), array_map(static fn ($candidate): Term => $candidate->result instanceof Term ? $candidate->result : Term::fromNative($candidate->result), iterator_to_array($r, false))));
        $result = $s->derive(new ValueQuery((new CallObservations($prepared1Program, $prepared1Evaluators))->find('observe')[0]->argument(0)));
        $frontiers = array_reduce(iterator_to_array($result, false), static function (array $frontiers, Candidate $candidate1): array {
            if ($candidate1->result instanceof Term) {
                $graph = $candidate1->result;
                $snapshot = $candidate1->evidence[0]->snapshot;
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
        self::assertNotEmpty($frontiers);
    }
    public function testObserveProjectsADemandedArrayEntry(): void
    {
        $prepared2Input = new Sources([new Source('candidate.php', '<?php ' . 'function target(){observe([1,2]);}')]);
        $prepared2Configuration = new DeriverConfig();
        $prepared2Contributions = new Contributions();
        $prepared2Target = new TargetProfile($prepared2Configuration->phpVersion, $prepared2Configuration->integerBits, $prepared2Configuration->floatPrecision);
        $prepared2Providers = new ProviderExpansion($prepared2Input, array_map(Term::fromNative(...), $prepared2Configuration->environment), $prepared2Contributions);
        $contributions = $prepared2Providers->contributions;
        $prepared2Evaluators = new Evaluators(new TargetProfile($prepared2Configuration->phpVersion, $prepared2Configuration->integerBits, $prepared2Configuration->floatPrecision), new Registrations($contributions->evaluators, $contributions->providers, $contributions->domains, $contributions->intrinsics, $contributions->stateSlots), $prepared2Configuration->standardEvaluators);
        $prepared2Settings = new EvaluatorConfig($prepared2Target, $prepared2Providers->environment, $prepared2Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared2Configuration->evaluators));
        $prepared2Identity = new SnapshotIdentity($prepared2Providers->input, $prepared2Settings, $prepared2Evaluators, $prepared2Providers->manifest);
        $snapshotId = $prepared2Identity->id;
        $input = $prepared2Providers->input;
        $parser = new Parser(new ParserConfig($prepared2Target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input, false));
        $prepared2Program = new Index($snapshotId, $trees);
        $prepared2Snapshot = $prepared2Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared2Program->diagnostics()));
        $s = new CandidateQuery(new DefinitionIndex($prepared2Program), $prepared2Settings, $prepared2Evaluators, $prepared2Snapshot);
        $r = $s->derive(new ValueQuery((new CallObservations($prepared2Program, $prepared2Evaluators))->find('observe')[0]->argument(0), new Projection([1])));
        $slot = 'value';
        self::assertSame([2], array_map(static fn (Term $value) => $value->native(), array_map(static fn ($candidate): Term => $candidate->result instanceof Term ? $candidate->result : Term::fromNative($candidate->result), iterator_to_array($r, false))));
    }
    public function testTupleKeepsTheSelectedNames(): void
    {
        $prepared3Input = new Sources([new Source('candidate.php', '<?php ' . 'function target(){observe(1,2);}')]);
        $prepared3Configuration = new DeriverConfig();
        $prepared3Contributions = new Contributions();
        $prepared3Target = new TargetProfile($prepared3Configuration->phpVersion, $prepared3Configuration->integerBits, $prepared3Configuration->floatPrecision);
        $prepared3Providers = new ProviderExpansion($prepared3Input, array_map(Term::fromNative(...), $prepared3Configuration->environment), $prepared3Contributions);
        $contributions = $prepared3Providers->contributions;
        $prepared3Evaluators = new Evaluators(new TargetProfile($prepared3Configuration->phpVersion, $prepared3Configuration->integerBits, $prepared3Configuration->floatPrecision), new Registrations($contributions->evaluators, $contributions->providers, $contributions->domains, $contributions->intrinsics, $contributions->stateSlots), $prepared3Configuration->standardEvaluators);
        $prepared3Settings = new EvaluatorConfig($prepared3Target, $prepared3Providers->environment, $prepared3Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared3Configuration->evaluators));
        $prepared3Identity = new SnapshotIdentity($prepared3Providers->input, $prepared3Settings, $prepared3Evaluators, $prepared3Providers->manifest);
        $snapshotId = $prepared3Identity->id;
        $input = $prepared3Providers->input;
        $parser = new Parser(new ParserConfig($prepared3Target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input, false));
        $prepared3Program = new Index($snapshotId, $trees);
        $prepared3Snapshot = $prepared3Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared3Program->diagnostics()));
        $s = new CandidateQuery(new DefinitionIndex($prepared3Program), $prepared3Settings, $prepared3Evaluators, $prepared3Snapshot);
        $site = (new CallObservations($prepared3Program, $prepared3Evaluators))->find('observe')[0];
        $r = $s->derive(new TupleQuery($site->beforeInvocation(), ['left' => $site->argument(0), 'right' => $site->argument(1)]));
        self::assertIsArray($r[0]->result);
        self::assertSame(['left', 'right'], array_keys($r[0]->result));
    }
    public function testReturnsRetainsConditionalImplementations(): void
    {
        $source = 'if($flag){function target(){return 1;}}else{function target(){return 2;}}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions();
        $prepared7Input = new Sources([new Source('candidate.php', '<?php ' . $source)]);
        $prepared7Configuration = $configuration;
        $prepared7Contributions = $contributions;
        $prepared7Target = new TargetProfile($prepared7Configuration->phpVersion, $prepared7Configuration->integerBits, $prepared7Configuration->floatPrecision);
        $prepared7Providers = new ProviderExpansion($prepared7Input, array_map(Term::fromNative(...), $prepared7Configuration->environment), $prepared7Contributions);
        $contributions1 = $prepared7Providers->contributions;
        $prepared7Evaluators = new Evaluators(new TargetProfile($prepared7Configuration->phpVersion, $prepared7Configuration->integerBits, $prepared7Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared7Configuration->standardEvaluators);
        $prepared7Settings = new EvaluatorConfig($prepared7Target, $prepared7Providers->environment, $prepared7Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared7Configuration->evaluators));
        $prepared7Identity = new SnapshotIdentity($prepared7Providers->input, $prepared7Settings, $prepared7Evaluators, $prepared7Providers->manifest);
        $snapshotId = $prepared7Identity->id;
        $input = $prepared7Providers->input;
        $parser = new Parser(new ParserConfig($prepared7Target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared7Program = new Index($snapshotId, $trees);
        $prepared7Snapshot = $prepared7Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared7Program->diagnostics()));
        $result = (new CandidateQuery(new DefinitionIndex($prepared7Program), $prepared7Settings, $prepared7Evaluators, $prepared7Snapshot))->derive(new ReturnQuery('target', budget: $budget));
        self::assertSame([1, 2], array_column(iterator_to_array($result, false), 'result'));
    }
    public function testProjectAppliesThePathOfTheTargetToTheSelectedValue(): void
    {
        $prepared4Input = new Sources([new Source('candidate.php', '<?php ' . 'function target(){observe([\'a\' => [1, 2]]);}')]);
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
        $s = new CandidateQuery(new DefinitionIndex($prepared4Program), $prepared4Settings, $prepared4Evaluators, $prepared4Snapshot);
        $r = $s->derive(new ValueQuery((new CallObservations($prepared4Program, $prepared4Evaluators))->find('observe')[0]->argument(0), new Projection(['a', 1])));
        $slot = 'value';
        self::assertSame([2], array_map(static fn (Term $value) => $value->native(), array_map(static fn ($candidate): Term => $candidate->result instanceof Term ? $candidate->result : Term::fromNative($candidate->result), iterator_to_array($r, false))));
    }
    public function testSelectKeepsAMissingSymbolAsAnUnexpandedReference(): void
    {
        $prepared5Input = new Sources([new Source('candidate.php', '<?php ' . 'function target(){return 1;}')]);
        $prepared5Configuration = new DeriverConfig();
        $prepared5Contributions = new Contributions();
        $prepared5Target = new TargetProfile($prepared5Configuration->phpVersion, $prepared5Configuration->integerBits, $prepared5Configuration->floatPrecision);
        $prepared5Providers = new ProviderExpansion($prepared5Input, array_map(Term::fromNative(...), $prepared5Configuration->environment), $prepared5Contributions);
        $contributions = $prepared5Providers->contributions;
        $prepared5Evaluators = new Evaluators(new TargetProfile($prepared5Configuration->phpVersion, $prepared5Configuration->integerBits, $prepared5Configuration->floatPrecision), new Registrations($contributions->evaluators, $contributions->providers, $contributions->domains, $contributions->intrinsics, $contributions->stateSlots), $prepared5Configuration->standardEvaluators);
        $prepared5Settings = new EvaluatorConfig($prepared5Target, $prepared5Providers->environment, $prepared5Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared5Configuration->evaluators));
        $prepared5Identity = new SnapshotIdentity($prepared5Providers->input, $prepared5Settings, $prepared5Evaluators, $prepared5Providers->manifest);
        $snapshotId = $prepared5Identity->id;
        $input = $prepared5Providers->input;
        $parser = new Parser(new ParserConfig($prepared5Target->version));
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input, false));
        $prepared5Program = new Index($snapshotId, $trees);
        $prepared5Snapshot = $prepared5Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared5Program->diagnostics()));
        $r = (new CandidateQuery(new DefinitionIndex($prepared5Program), $prepared5Settings, $prepared5Evaluators, $prepared5Snapshot))->derive(new ReturnQuery('target'));
        $slot = 'value';
        self::assertSame([1], array_map(static fn (Term $value) => $value->native(), array_map(static fn ($candidate): Term => $candidate->result instanceof Term ? $candidate->result : Term::fromNative($candidate->result), iterator_to_array($r, false))));
        $prepared6Input = new Sources([new Source('candidate.php', '<?php ' . 'function target(){return missing();}')]);
        $prepared6Configuration = new DeriverConfig();
        $prepared6Contributions = new Contributions();
        $prepared6Target = new TargetProfile($prepared6Configuration->phpVersion, $prepared6Configuration->integerBits, $prepared6Configuration->floatPrecision);
        $prepared6Providers = new ProviderExpansion($prepared6Input, array_map(Term::fromNative(...), $prepared6Configuration->environment), $prepared6Contributions);
        $contributions1 = $prepared6Providers->contributions;
        $prepared6Evaluators = new Evaluators(new TargetProfile($prepared6Configuration->phpVersion, $prepared6Configuration->integerBits, $prepared6Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared6Configuration->standardEvaluators);
        $prepared6Settings = new EvaluatorConfig($prepared6Target, $prepared6Providers->environment, $prepared6Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared6Configuration->evaluators));
        $prepared6Identity = new SnapshotIdentity($prepared6Providers->input, $prepared6Settings, $prepared6Evaluators, $prepared6Providers->manifest);
        $snapshotId1 = $prepared6Identity->id;
        $input1 = $prepared6Providers->input;
        $parser1 = new Parser(new ParserConfig($prepared6Target->version));
        $trees1 = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $prepared6Program = new Index($snapshotId1, $trees1);
        $prepared6Snapshot = $prepared6Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared6Program->diagnostics()));
        $missing = (new CandidateQuery(new DefinitionIndex($prepared6Program), $prepared6Settings, $prepared6Evaluators, $prepared6Snapshot))->derive(new ReturnQuery('target'));
        self::assertSame(CandidateStatus::Partials, $missing[0]->type);
    }    public function testPathObservationPrunesAnExcludedArgumentSite(): void
    {
        $source = 'function foo($x){return $x;}function bar(){if(false){foo(7);}}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('fixture', [$tree]));
        $settings = new EvaluatorConfig(evaluators: array_map((new MicroEvaluatorConversion())->convert(...), \Deriver\Deriver\BranchEvaluators::truth()));
        $engine = new \Deriver\Deriver\Evaluator\Evaluator(new \Deriver\Deriver\Evaluator\Scope\Context($index, $settings, new Evaluators($settings->target), new Budget()));
        [$caller, $call] = (new \Deriver\Deriver\Evaluator\Invocation\Path\Callers($index))->sites('foo', 'bar')[0];
        $engine->context->callPath = new \Deriver\Deriver\Evaluator\Request\CallPath(['bar'], true);
        $target = new \Deriver\Deriver\Evaluator\Target('bar', \Deriver\Deriver\Evaluator\TargetKind::Value, $call->arguments[0]->register, register: $call->arguments[0]->register);
        $value = (new \Deriver\Deriver\Evaluator\Demand($engine))->pathObservation(new \Deriver\Deriver\Evaluator\Scope\Frame($caller, 'bar'), $target);
        self::assertSame(\Deriver\Deriver\Evaluator\TermKind::Choice, $value->kind);
        self::assertSame([], $value->operands);
    }


}
