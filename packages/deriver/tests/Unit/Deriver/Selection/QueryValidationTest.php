<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Selection;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\BoundInstruction;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Extensions;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Call\StateRegistry;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\EvaluatorConfig as ExecutionEvaluatorConfig;
use Deriver\Deriver\Evaluator\Projection;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\Query;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Request\StateQuery;
use Deriver\Deriver\Evaluator\Request\TupleQuery;
use Deriver\Deriver\Evaluator\Request\ValueQuery;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Execution\QueryExecution;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\ExpressionLowering;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\ExpressionRef;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\ObservationPhase;
use Deriver\Deriver\Lowerer\Graph\PointRef;
use Deriver\Deriver\Lowerer\Graph\Program;
use Deriver\Deriver\Lowerer\Graph\Terminator;
use Deriver\Deriver\Lowerer\Graph\TerminatorKind;
use Deriver\Deriver\Lowerer\InstructionEmitter;
use Deriver\Deriver\Lowerer\Lowerer;
use Deriver\Deriver\Lowerer\LoweringScope;
use Deriver\Deriver\Lowerer\StatementLowering;
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
use Deriver\Deriver\Selection\QueryValidation;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\LineMap;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\InvalidInputException;
use Deriver\Exception\ParserException;
use Deriver\Exception\ResolutionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Selection\QueryValidation
 */
#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(QueryValidation::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Context::class)]
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
#[UsesClass(TupleQuery::class)]
#[UsesClass(ValueQuery::class)]
#[UsesClass(ExpressionRef::class)]
#[UsesClass(PointRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Parser::class)]
#[UsesClass(File::class)]
#[UsesClass(Lowerer::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(InstructionEmitter::class)]
#[UsesClass(LoweringScope::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(Indexer::class)]
#[UsesClass(Index::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Projection::class)]
#[UsesClass(Term::class)]
#[Small]
final class QueryValidationTest extends TestCase
{
    public function testOwnerRejectsAnEmptyReturnSymbol(): void
    {
        $this->expectException(InvalidInputException::class);
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
        (new QueryValidation((new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators))->program, 'test'))->owner(new ReturnQuery(''));
    }
    public function testReferenceRejectsForeignSnapshots(): void
    {
        $reference = new ExpressionRef(new SourceRef('foreign', 'fixture.php', 0, 1), 'target', 'r0');
        $this->expectException(InvalidInputException::class);
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
        (new QueryValidation((new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators))->program, 'test'))->reference($reference);
    }
    public function testReferenceAcceptsAnActualCapturedInstruction(): void
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
        $program2 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program2, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program2->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program1 = new Index($snapshotId1, $trees1);
        $program = (new Context($program1, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators))->program;
        $body = $program->callable('target');
        self::assertNotNull($body);
        $instruction = $body->blocks[0]->instructions[0];
        $reference = new ExpressionRef($instruction->source, 'target', $instruction->result);
        $validation = new QueryValidation($program, 'test');
        $validation->reference($reference);
        self::assertSame('target', $validation->owner(new ValueQuery($reference)));
    }
    #[DataProvider('providerInvalidReferences')]
    public function testReferenceRejectsEveryMismatchedIdentityComponent(ExpressionRef|PointRef $reference): void
    {
        $source = new SourceRef('snapshot', 'fixture.php', 10, 20);
        $body = new CallableGraph('target', [], [new BasicBlock(0, [new BoundInstruction('instruction', 'constant', $source, 'register')], new Terminator(TerminatorKind::Return))], $source);
        $program = self::createStub(Program::class);
        $program->method('callable')->willReturn($body);
        $this->expectException(InvalidInputException::class);
        (new QueryValidation($program, 'snapshot'))->reference($reference);
    }
    /**
     * @return array<string,array{ExpressionRef|PointRef}>
     */
    public static function providerInvalidReferences(): array
    {
        return ['expression path' => [new ExpressionRef(new SourceRef('snapshot', 'other.php', 10, 20), 'target', 'register')], 'expression start' => [new ExpressionRef(new SourceRef('snapshot', 'fixture.php', 9, 20), 'target', 'register')], 'expression end' => [new ExpressionRef(new SourceRef('snapshot', 'fixture.php', 10, 21), 'target', 'register')], 'expression register' => [new ExpressionRef(new SourceRef('snapshot', 'fixture.php', 10, 20), 'target', 'other')], 'point path' => [new PointRef(new SourceRef('snapshot', 'other.php', 10, 20), 'target', 'instruction', ObservationPhase::After)], 'point start' => [new PointRef(new SourceRef('snapshot', 'fixture.php', 9, 20), 'target', 'instruction', ObservationPhase::Before)], 'point end' => [new PointRef(new SourceRef('snapshot', 'fixture.php', 10, 21), 'target', 'instruction', ObservationPhase::Invocation)], 'point instruction' => [new PointRef(new SourceRef('snapshot', 'fixture.php', 10, 20), 'target', 'register', ObservationPhase::After)], 'point snapshot' => [new PointRef(new SourceRef('foreign', 'fixture.php', 10, 20), 'target', 'instruction', ObservationPhase::After)]];
    }
    #[DataProvider('providerValidQueries')]
    public function testOwnerResolvesEachSupportedObservationForm(Query $query): void
    {
        $source = new SourceRef('snapshot', 'fixture.php', 10, 20);
        $body = new CallableGraph('target', [], [new BasicBlock(0, [new BoundInstruction('instruction', 'constant', $source, 'register')], new Terminator(TerminatorKind::Return))], $source);
        $program = self::createStub(Program::class);
        $program->method('callable')->willReturn($body);
        self::assertSame('target', (new QueryValidation($program, 'snapshot'))->owner($query));
    }
    /**
     * @return array<string,array{Query}>
     */
    public static function providerValidQueries(): array
    {
        $source = new SourceRef('snapshot', 'fixture.php', 10, 20);
        $expression = new ExpressionRef($source, 'target', 'register');
        return ['return' => [new ReturnQuery('target')], 'value' => [new ValueQuery($expression)], 'state before' => [new StateQuery(new PointRef($source, 'target', 'instruction', ObservationPhase::Before), 'value')], 'state after' => [new StateQuery(new PointRef($source, 'target', 'instruction', ObservationPhase::After), 'value')], 'state invocation' => [new StateQuery(new PointRef($source, 'target', 'instruction', ObservationPhase::Invocation), 'value')], 'tuple' => [new TupleQuery(new PointRef($source, 'target', 'instruction', ObservationPhase::After), ['value' => $expression])]];
    }
    public function testOwnerRejectsUnknownQueryImplementations(): void
    {
        $program = self::createStub(Program::class);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Unsupported query implementation.');
        (new QueryValidation($program, 'snapshot'))->owner(self::createStub(Query::class));
    }
    public function testReferenceRejectsMissingCallableBodies(): void
    {
        $program = self::createStub(Program::class);
        $program->method('callable')->willReturn(null);
        $this->expectException(InvalidInputException::class);
        (new QueryValidation($program, 'snapshot'))->reference(new ExpressionRef(new SourceRef('snapshot', 'fixture.php', 10, 20), 'missing', 'register'));
    }
    public function testOwnerRejectsAnEmptyTupleAtAnOtherwiseValidPoint(): void
    {
        $source = new SourceRef('snapshot', 'fixture.php', 10, 20);
        $body = new CallableGraph('target', [], [new BasicBlock(0, [new BoundInstruction('instruction', 'constant', $source, 'register')], new Terminator(TerminatorKind::Return))], $source);
        $program = self::createStub(Program::class);
        $program->method('callable')->willReturn($body);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Tuple queries require at least one expression.');
        (new QueryValidation($program, 'snapshot'))->owner(new TupleQuery(new PointRef($source, 'target', 'instruction', ObservationPhase::After), []));
    }
    public function testOwnerCannotCombineExpressionsFromDifferentInvocations(): void
    {
        $source = new SourceRef('snapshot', 'fixture.php', 10, 20);
        $block = new BasicBlock(0, [new BoundInstruction('instruction', 'constant', $source, 'register')], new Terminator(TerminatorKind::Return));
        $program = self::createStub(Program::class);
        $program->method('callable')->willReturnMap([['target', new CallableGraph('target', [], [$block], $source)], ['other', new CallableGraph('other', [], [$block], $source)]]);
        $query = new TupleQuery(new PointRef($source, 'target', 'instruction', ObservationPhase::After), ['foreign' => new ExpressionRef($source, 'other', 'register')]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Tuple expressions must belong to the observation callable.');
        (new QueryValidation($program, 'snapshot'))->owner($query);
    }
    public function testOwnerAllowsEquivalentNamedOwnersWithinACorrelatedTuple(): void
    {
        $source = new SourceRef('snapshot', 'fixture.php', 10, 20);
        $body = new CallableGraph('N\target', [], [new BasicBlock(0, [new BoundInstruction('instruction', 'constant', $source, 'register')], new Terminator(TerminatorKind::Return))], $source);
        $program = self::createStub(Program::class);
        $program->method('callable')->willReturn($body);
        $query = new TupleQuery(new PointRef($source, '\N\TARGET', 'instruction', ObservationPhase::After), ['value' => new ExpressionRef($source, 'n\target', 'register')]);
        self::assertSame('\N\TARGET', (new QueryValidation($program, 'snapshot'))->owner($query));
    }
    public function testOwnerCannotFoldDifferentScriptPathsIntoTheSameTuple(): void
    {
        $source = new SourceRef('snapshot', 'fixture.php', 10, 20);
        $block = new BasicBlock(0, [new BoundInstruction('instruction', 'constant', $source, 'register')], new Terminator(TerminatorKind::Return));
        $program = self::createStub(Program::class);
        $program->method('callable')->willReturnMap([['script:A.php', new CallableGraph('script:A.php', [], [$block], $source)], ['script:a.php', new CallableGraph('script:a.php', [], [$block], $source)]]);
        $query = new TupleQuery(new PointRef($source, 'script:A.php', 'instruction', ObservationPhase::After), ['foreign' => new ExpressionRef($source, 'script:a.php', 'register')]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Tuple expressions must belong to the observation callable.');
        (new QueryValidation($program, 'snapshot'))->owner($query);
    }
}
