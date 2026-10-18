<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\EvaluatorConfig as ExecutionEvaluatorConfig;
use Deriver\Deriver\Evaluator\Execution\Trace\Explanation;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Request\StateQuery;
use Deriver\Deriver\Evaluator\Request\TupleQuery;
use Deriver\Deriver\Evaluator\Request\ValueQuery;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Execution\ExecutionResultSet;
use Deriver\Deriver\Execution\QueryExecution;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Providers\ProviderExpansion;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Selection\CallObservations;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\InvalidInputException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Medium]
final class BatchObservationsTest extends TestCase
{
    #[DataProvider('providerObservationIndices')]
    public function testInstructionSharesExecutionPrefixesAndKeepsSeparateValues(int $index): void
    {
        $source = '<?php function sink($x){} function target(){$n=0;' . str_repeat('$n+=1;', 100) . 'sink($n);$n++;sink($n);$n++;sink($n);}';
        $input = new Sources([new Source('fixture.php', $source)]);
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
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $session = new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics())));
        $queries = array_map(static fn ($site) => new ValueQuery($site->argument(0)), (new CallObservations($session->program, $session->evaluators))->find('sink'));
        $independent = (new ExecutionResultSet(array_map($session->derive(...), $queries)))->results;
        $batch = $session->together($queries)->results;
        self::assertCount(3, $batch);
        $result = $batch[$index];
        self::assertNotNull($result->definite());
        self::assertSame(100 + $index, $result->definite()->values['value']->native());
        self::assertSame($independent[$index]->normalOutcomes[0]->values['value']->native(), $result->normalOutcomes[0]->values['value']->native());
        self::assertNotSame($independent[$index]->reference->id, $result->reference->id);
        self::assertSame($result->evidence, (new Explanation($result->evidence, $result->frontiers, $result->assumptions))->nodes);
        self::assertLessThan(array_sum(array_map(static fn ($result) => $result->statistics->transfers, $independent)) / 2, $batch[0]->statistics->transfers);
        self::assertSame($batch[0]->statistics->transfers, $batch[2]->statistics->transfers);
        $repeated = $session->derive($queries[0]);
        self::assertNotSame($independent[0], $repeated);
        self::assertEquals($independent[0]->normalOutcomes, $repeated->normalOutcomes);
    }
    public function testBatchPreservesTupleCorrelationStateAndReturn(): void
    {
        $input = new Sources([new Source('fixture.php', '<?php function sink($a,$b){} function target(bool $flag){$a=$flag?1:2;$b=$a+10;sink($a,$b);return $a;}')]);
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
        $site = (new CallObservations($session->program, $session->evaluators))->find('sink')[0];
        $results = $session->together([new TupleQuery($site->beforeInvocation(), ['a' => $site->argument(0), 'b' => $site->argument(1)]), new StateQuery($site->beforeInvocation(), 'a'), new ReturnQuery('target')])->results;
        $pairs = array_map(static fn ($outcome) => [$outcome->values['a']->native(), $outcome->values['b']->native()], $results[0]->normalOutcomes);
        sort($pairs);
        self::assertSame([[1, 11], [2, 12]], $pairs);
        self::assertCount(2, $results[1]->normalOutcomes);
        self::assertCount(2, $results[2]->normalOutcomes);
        self::assertSame([], $results[0]->frontiers);
    }
    /**
     * @param string $body Body under symbolic inputs
     */
    #[DataProvider('providerControlFlow')]
    public function testCompletionAgreesWithIndependentControlFlow(string $body, int $index): void
    {
        $input = new Sources([new Source('fixture.php', '<?php function sink($x){} function target(bool $flag){' . $body . '}')]);
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
        $queries = array_map(static fn ($site) => new ValueQuery($site->argument(0)), (new CallObservations($session->program, $session->evaluators))->find('sink'));
        $independent = (new ExecutionResultSet(array_map($session->derive(...), $queries)))->results;
        $batch = $session->together($queries)->results;
        $result = $batch[$index];
        $expected = array_map(static fn ($outcome) => $outcome->values['value']->native(), $independent[$index]->normalOutcomes);
        $actual = array_map(static fn ($outcome) => $outcome->values['value']->native(), $result->normalOutcomes);
        sort($expected);
        sort($actual);
        self::assertSame($expected, $actual);
        self::assertSame(count($independent[$index]->exceptionalOutcomes), count($result->exceptionalOutcomes));
        self::assertSame($independent[$index]->reachability, $result->reachability);
    }
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerControlFlow(): iterable
    {
        $cases = ['branch' => ['if($flag){sink(1);}else{sink(2);}sink(3);', 3], 'throw after first' => ['sink(1);throw new Exception;sink(2);', 2], 'branch exception' => ['if($flag){sink(1);}throw new Exception;sink(2);', 2], 'loop' => ['for($i=0;$i<3;$i++){sink($i);}sink(9);', 2], 'finally' => ['try{sink(1);if($flag){throw new Exception;}}finally{sink(2);}sink(3);', 3], 'unreachable' => ['return;sink(1);sink(2);', 2]];
        foreach ($cases as $name => [$body, $count]) {
            for ($i = 0; $i < $count; $i++) {
                yield $name . ':' . $i => [$body, $i];
            }
        }
    }
    /**
     * @return list<array{int}>
     */
    public static function providerObservationIndices(): array
    {
        return [[0], [1], [2]];
    }
    #[DataProvider('providerInterruptedIndices')]
    public function testSynchronizeKeepsSharedInterruptionAndRecoveredTypes(int $index): void
    {
        $input = new Sources([new Source('fixture.php', '<?php function target(PDO $pdo){heavy();$pdo->query("SELECT 1");$pdo->query("SELECT 2");}')]);
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
        $queries = array_map(static function ($site): TupleQuery {
            self::assertNotNull($site->receiver);
            return new TupleQuery($site->beforeInvocation(), ['sql' => $site->argument(0), 'receiver' => $site->receiver], budget: new Budget(transfers: 1));
        }, (new CallObservations($session->program, $session->evaluators))->find('query'));
        $result = $session->together($queries)->results[$index];
        self::assertSame('SELECT ' . ($index + 1), $result->normalOutcomes[0]->values['sql']->native());
        self::assertSame('PDO', $result->normalOutcomes[0]->values['receiver']->attributes['type']);
        self::assertContains('BUDGET_EXCEEDED', array_column($result->frontiers, 'code'));
        self::assertNull($result->definite());
    }
    /**
     * @return list<array{int}>
     */
    public static function providerInterruptedIndices(): array
    {
        return [[0], [1]];
    }
    /**
     * @param ReturnQuery $other Incompatible query
     */
    #[DataProvider('providerMismatches')]
    public function testMismatchedExecutionContractsAreRejected(ReturnQuery $other): void
    {
        $input = new Sources([new Source('fixture.php', '<?php function first(){return 1;}function second(){return 2;}')]);
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
        $this->expectException(InvalidInputException::class);
        $session->together([new ReturnQuery('first'), $other]);
    }
    /**
     * @return list<array{ReturnQuery}>
     */
    public static function providerMismatches(): array
    {
        return [[new ReturnQuery('second')], [new ReturnQuery('first', QueryScope::fromEntrypoints([new EntryPoint('first')]))], [new ReturnQuery('first', budget: new Budget(transfers: 1))]];
    }
    public function testEmptyAndDuplicateBatchesKeepRequestedOrdering(): void
    {
        $input = new Sources([new Source('fixture.php', '<?php function target(){return 3;}')]);
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
        self::assertSame([], $session->together([])->results);
        $result = $session->together([new ReturnQuery('target'), new ReturnQuery('target')])->results;
        self::assertCount(2, $result);
        self::assertEquals($result[0]->normalOutcomes, $result[1]->normalOutcomes);
        self::assertSame(3, $result[0]->normalOutcomes[0]->values['return']->native());
    }
}
