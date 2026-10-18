<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Control;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Extensions;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Call\StateRegistry;
use Deriver\Deriver\Evaluator\Domain\Lattice;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\Control\PathJoin;
use Deriver\Deriver\Evaluator\Execution\Control\StateJoin;
use Deriver\Deriver\Evaluator\Execution\EvaluatorConfig as ExecutionEvaluatorConfig;
use Deriver\Deriver\Evaluator\Execution\Havoc;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\Memory\Materialization;
use Deriver\Deriver\Evaluator\Execution\Memory\Memory;
use Deriver\Deriver\Evaluator\Execution\Offset\Address;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Scalar\StringPrefix;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Execution\QueryExecution;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Parser\TargetSyntax;
use Deriver\Deriver\Providers\ProviderExpansion;
use Deriver\Deriver\Resolver\AssignmentPatterns;
use Deriver\Deriver\Resolver\ClassScope;
use Deriver\Deriver\Resolver\ExpressionValidation;
use Deriver\Deriver\Resolver\MagicContext;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\LineMap;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\ParserException;
use Deriver\Exception\ResolutionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(PathJoin::class)]
#[UsesClass(Address::class)]
#[UsesClass(Completion::class)]
#[UsesClass(State::class)]
#[UsesClass(Location::class)]
#[UsesClass(Term::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(State::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Context::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Havoc::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(Evaluators::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(DeriverConfig::class)]
#[UsesClass(Sources::class)]
#[UsesClass(Path::class)]
#[UsesClass(Source::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(Frontier::class)]
#[UsesClass(Parser::class)]
#[UsesClass(File::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(Indexer::class)]
#[UsesClass(Index::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Lattice::class)]
#[UsesClass(StringPrefix::class)]
#[Small]
final class PathJoinTest extends TestCase
{
    public function testJoinWidensDifferingPlainValuesAndKeepsSharedFacts(): void
    {
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $join = new PathJoin(new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators));
        $first = Term::constant('SELECT a');
        $second = Term::constant('SELECT b');
        $base = new State();
        $base->block = 2;
        $base->previous = 1;
        $base->memory->write($base->local('table'), Term::constant('kept'));
        $base->local('sql');
        $a1 = $base->fork();
        $b1 = $base->fork();
        $a1->memory->write($a1->local('sql'), $first);
        $b1->memory->write($b1->local('sql'), $second);
        [$a, $b] = [$a1, $b1];
        $a->guard = ['shared' => true, 'left' => true];
        $b->guard = ['shared' => true, 'left' => false];
        $a->visits = [3 => 2];
        $b->visits = [3 => 5];
        $a->evidence = ['x'];
        $b->evidence = ['y'];
        $b->memory->sequence = 9;
        $joined = $join->join([$a, $b]);
        self::assertNotNull($joined);
        $value = $joined->memory->read($joined->locals['sql']);
        self::assertSame('SELECT ', $value->operands[0]->native());
        self::assertTrue((new Lattice())->contains($value, Term::constant('SELECT a')));
        self::assertTrue((new Lattice())->contains($value, Term::constant('SELECT b')));
        self::assertSame('kept', $joined->memory->read($joined->locals['table'])->native());
        self::assertSame(['shared' => true], $joined->guard);
        self::assertSame([3 => 5], $joined->visits);
        self::assertSame(['x', 'y'], $joined->evidence);
        self::assertSame(9, $joined->memory->sequence);
        self::assertSame([], $joined->constraints);
        self::assertSame(CompletionKind::Normal, $joined->completion->kind);
        self::assertSame('SELECT a', $a->memory->read($a->locals['sql'])->native());
    }
    public function testJoinRejectsDifferentPredecessors(): void
    {
        $first = Term::constant(1);
        $second = Term::constant(2);
        $base = new State();
        $base->block = 2;
        $base->previous = 1;
        $base->memory->write($base->local('table'), Term::constant('kept'));
        $base->local('sql');
        $a1 = $base->fork();
        $b1 = $base->fork();
        $a1->memory->write($a1->local('sql'), $first);
        $b1->memory->write($b1->local('sql'), $second);
        [$a, $b] = [$a1, $b1];
        $b->previous = 4;
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        self::assertNull((new PathJoin(new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators)))->join([$a, $b]));
    }
    public function testJoinRejectsDifferentAliasesAndObjects(): void
    {
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $join = new PathJoin(new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators));
        $first = Term::constant(1);
        $second = new Term(TermKind::Cell, 'cell:9');
        $base = new State();
        $base->block = 2;
        $base->previous = 1;
        $base->memory->write($base->local('table'), Term::constant('kept'));
        $base->local('sql');
        $a1 = $base->fork();
        $b1 = $base->fork();
        $a1->memory->write($a1->local('sql'), $first);
        $b1->memory->write($b1->local('sql'), $second);
        [$a, $b] = [$a1, $b1];
        self::assertNull($join->join([$a, $b]));
        $first1 = new Term(TermKind::Object, 'object:1', attributes: ['class' => 'A']);
        $second1 = new Term(TermKind::Object, 'object:2', attributes: ['class' => 'A']);
        $base1 = new State();
        $base1->block = 2;
        $base1->previous = 1;
        $base1->memory->write($base1->local('table'), Term::constant('kept'));
        $base1->local('sql');
        $a2 = $base1->fork();
        $b2 = $base1->fork();
        $a2->memory->write($a2->local('sql'), $first1);
        $b2->memory->write($b2->local('sql'), $second1);
        [$a, $b] = [$a2, $b2];
        self::assertNull($join->join([$a, $b]));
        $first2 = Term::constant(1);
        $second2 = Term::constant(2);
        $base2 = new State();
        $base2->block = 2;
        $base2->previous = 1;
        $base2->memory->write($base2->local('table'), Term::constant('kept'));
        $base2->local('sql');
        $a3 = $base2->fork();
        $b3 = $base2->fork();
        $a3->memory->write($a3->local('sql'), $first2);
        $b3->memory->write($b3->local('sql'), $second2);
        [$a, $b] = [$a3, $b3];
        $b->locals['other'] = new Location('other');
        self::assertNull($join->join([$a, $b]));
    }
    public function testJoinKeepsRegisterMetadataOnlyWhenEveryPathAgrees(): void
    {
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $join = new PathJoin(new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators));
        $first = Term::constant(1);
        $second = Term::constant(2);
        $base = new State();
        $base->block = 2;
        $base->previous = 1;
        $base->memory->write($base->local('table'), Term::constant('kept'));
        $base->local('sql');
        $a1 = $base->fork();
        $b1 = $base->fork();
        $a1->memory->write($a1->local('sql'), $first);
        $b1->memory->write($b1->local('sql'), $second);
        [$a, $b] = [$a1, $b1];
        $a->registers = ['r1' => Term::constant(1), 'r2' => Term::constant('only a')];
        $b->registers = ['r1' => Term::constant(1)];
        $a->addresses = ['r1' => new Location('x'), 'r2' => new Location('y')];
        $b->addresses = ['r1' => new Location('x')];
        $joined = $join->join([$a, $b]);
        self::assertNotNull($joined);
        self::assertSame(['r1'], array_keys($joined->registers));
        self::assertSame(['r1'], array_keys($joined->addresses));
        $b->addresses = ['r1' => new Location('z')];
        self::assertNull($join->join([$a, $b]));
    }
    public function testJoinWidensCompletedReturnValuesWithoutComparingRegisters(): void
    {
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $join = new PathJoin(new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators));
        $first = Term::constant(1);
        $second = Term::constant(1);
        $base = new State();
        $base->block = 2;
        $base->previous = 1;
        $base->memory->write($base->local('table'), Term::constant('kept'));
        $base->local('sql');
        $a1 = $base->fork();
        $b1 = $base->fork();
        $a1->memory->write($a1->local('sql'), $first);
        $b1->memory->write($b1->local('sql'), $second);
        [$a, $b] = [$a1, $b1];
        $a->completion = new Completion(CompletionKind::Return, Term::constant('a'));
        $b->completion = new Completion(CompletionKind::Return, Term::constant(2));
        $a->block = 1;
        $b->block = 2;
        $a->registers = ['r1' => new Term(TermKind::Cell, 'cell:1')];
        $b->registers = ['r1' => Term::constant(1)];
        self::assertNull($join->join([$a, $b]));
        $joined = $join->join([$a, $b], true);
        self::assertNotNull($joined);
        self::assertSame(CompletionKind::Return, $joined->completion->kind);
        self::assertSame('int|string', (new Lattice())->type($joined->completion->value ?? Term::constant(null)));
    }
    public function testMergeAccumulatesBookkeepingConservatively(): void
    {
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $join = new PathJoin(new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators));
        $first = Term::constant(1);
        $second = Term::constant(2);
        $base = new State();
        $base->block = 2;
        $base->previous = 1;
        $base->memory->write($base->local('table'), Term::constant('kept'));
        $base->local('sql');
        $a1 = $base->fork();
        $b1 = $base->fork();
        $a1->memory->write($a1->local('sql'), $first);
        $b1->memory->write($b1->local('sql'), $second);
        [$a, $b] = [$a1, $b1];
        $a->observed = true;
        $b->observed = false;
        $a->producers = ['r1' => 'x', 'r2' => 'y'];
        $b->producers = ['r1' => 'x', 'r2' => 'z'];
        $a->registers = ['r1' => Term::constant(1), 'r2' => Term::constant(2)];
        $b->unknownLocals = 'EVAL';
        $b->memory->unknownShared = 'MISSING_CALL_MODEL';
        $b->memory->versions = ['cell:9' => 4];
        $join->merge($a, $b);
        self::assertFalse($a->observed);
        self::assertSame(['r1' => 'x'], $a->producers);
        self::assertSame('EVAL', $a->unknownLocals);
        self::assertSame('MISSING_CALL_MODEL', $a->memory->unknownShared);
        self::assertSame(4, $a->memory->versions['cell:9']);
    }
    public function testStructureIgnoresValuesButNotPositionsOrAliases(): void
    {
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $join = new PathJoin(new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators));
        $first = Term::constant(1);
        $second = Term::constant(2);
        $base = new State();
        $base->block = 2;
        $base->previous = 1;
        $base->memory->write($base->local('table'), Term::constant('kept'));
        $base->local('sql');
        $a1 = $base->fork();
        $b1 = $base->fork();
        $a1->memory->write($a1->local('sql'), $first);
        $b1->memory->write($b1->local('sql'), $second);
        [$a, $b] = [$a1, $b1];
        $b->guard = ['g' => true];
        $b->registers = ['r1' => Term::constant(3)];
        self::assertSame($join->structure($a), $join->structure($b));
        $b->block = 3;
        self::assertNotSame($join->structure($a), $join->structure($b));
        self::assertSame($join->structure($a, true), $join->structure($b, true));
        $b->memory->classes['object:1'] = 'A';
        self::assertNotSame($join->structure($a, true), $join->structure($b, true));
    }
    public function testCommonKeepsEqualMetadataPresentOnEveryPath(): void
    {
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $join = new PathJoin(new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators));
        self::assertEquals(['r1' => new Location('x')], $join->common([['r1' => new Location('x'), 'r2' => new Location('y')], ['r1' => new Location('x')]]));
        self::assertNull($join->common([['r1' => new Location('x')], ['r1' => new Location('y')]]));
    }
    public function testValuesWidensDifferencesAndRejectsIdentities(): void
    {
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $join = new PathJoin(new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators));
        $values = $join->values([['a' => Term::constant(1), 'b' => Term::constant('x')], ['a' => Term::constant(1), 'b' => Term::constant('y')]], false);
        self::assertNotNull($values);
        self::assertSame(1, $values['a']->native());
        self::assertSame('WIDENED', $values['b']->literal);
        self::assertSame(['a'], array_keys($join->values([['a' => Term::constant(1), 'b' => Term::constant(2)], ['a' => Term::constant(1)]], false) ?? []));
        self::assertSame(['a', 'b'], array_keys($join->values([['a' => Term::constant(1), 'b' => Term::constant(2)], ['a' => Term::constant(1)]], true) ?? []));
        self::assertNull($join->values([['a' => new Term(TermKind::Cell, 'cell:1')], ['a' => new Term(TermKind::Cell, 'cell:2')]], false));
    }
    public function testLocationsGeneralizeDifferingAddressesIntoOneRoot(): void
    {
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $join = new PathJoin(new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators));
        $locations = $join->locations([['r1' => new Location('cell:1', [0]), 'r2' => new Location('cell:2')], ['r1' => new Location('cell:1', [1]), 'r2' => new Location('cell:2')]]);
        self::assertNotNull($locations);
        self::assertEquals(new Location('cell:1', unknown: true), $locations['r1']);
        self::assertEquals(new Location('cell:2'), $locations['r2']);
        self::assertNull($join->locations([['r1' => new Location('cell:1')], ['r1' => new Location('cell:2')]]));
    }
    public function testOffsetsWidenKeysBelowOneParent(): void
    {
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $join = new PathJoin(new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators));
        $offsets = $join->offsets([['r1' => new Address('r0', Term::constant(0))], ['r1' => new Address('r0', Term::constant(1))]]);
        self::assertNotNull($offsets);
        self::assertSame('r0', $offsets['r1']->parent);
        self::assertTrue((new Lattice())->contains($offsets['r1']->key ?? Term::constant(null), Term::constant(1)));
        self::assertNull($join->offsets([['r1' => new Address('r0', null)], ['r1' => new Address('r0', Term::constant(1))]]));
        self::assertNull($join->offsets([['r1' => new Address('r0', null)], ['r1' => new Address('r9', null)]]));
    }
    public function testLoopsKeepOnlyBookkeepingBothPathsShare(): void
    {
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $join = new PathJoin(new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators));
        $first = Term::constant(1);
        $second = Term::constant(2);
        $base = new State();
        $base->block = 2;
        $base->previous = 1;
        $base->memory->write($base->local('table'), Term::constant('kept'));
        $base->local('sql');
        $a1 = $base->fork();
        $b1 = $base->fork();
        $a1->memory->write($a1->local('sql'), $first);
        $b1->memory->write($b1->local('sql'), $second);
        [$a, $b] = [$a1, $b1];
        $a->loopGuards = [5 => ['flag' => true, 'other' => true], 6 => ['x' => true]];
        $b->loopGuards = [5 => ['flag' => false, 'other' => true]];
        $a->approximations = [5 => ['cell:1' => Term::constant(1)]];
        $b->approximations = [5 => ['cell:1' => Term::constant(2)]];
        $a->stableHeader = 5;
        $join->loops($a, $b);
        self::assertSame([5 => ['other' => true]], $a->loopGuards);
        self::assertSame([], $a->approximations);
        self::assertNull($a->stableHeader);
    }
    public function testValuesKeepObjectRecordsExact(): void
    {
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $join = new PathJoin(new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators));
        self::assertNull($join->values([['object:1' => Term::array([])], ['object:1' => Term::array(['p' => Term::constant(5)])]], true, true));
        self::assertNotNull($join->values([['cell:1' => Term::array([])], ['cell:1' => Term::array(['p' => Term::constant(5)])]], true, true));
        self::assertNotNull($join->values([['object:1' => Term::array([])], ['object:1' => Term::array([])]], true, true));
    }
    public function testPlainRejectsNestedIdentities(): void
    {
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $join = new PathJoin(new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators));
        self::assertTrue($join->plain(Term::array([Term::constant(1), Term::parameter('x')])));
        self::assertFalse($join->plain(Term::array([Term::constant(1), new Term(TermKind::Cell, 'cell:1')])));
        self::assertTrue($join->plain(new Term(TermKind::Uninitialized)));
    }
}
