<?php

declare (strict_types=1);

namespace Tests\Integration;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\CallDecision;
use Deriver\Deriver\Evaluator\Call\CallDescriptor;
use Deriver\Deriver\Evaluator\Call\CallEvaluator;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Plan\Action;
use Deriver\Deriver\Evaluator\Call\Plan\Expression;
use Deriver\Deriver\Evaluator\Call\Plan\SemanticPlan;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\EvaluatorConfig as ExecutionEvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Execution\QueryExecution;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Providers\ProviderExpansion;
use Deriver\Deriver\Publication\CandidateQuery;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use WeakReference;

#[CoversNothing]
final class EvaluationLifetimeTest extends TestCase
{
    public function testIndependentQueriesEvaluateTheirDependenciesAgain(): void
    {
        $evaluator = self::createMock(CallEvaluator::class);
        $evaluator->method('descriptor')->willReturn(new CallDescriptor('fixture.provided', '1', 'provided'));
        $evaluator->expects(self::exactly(2))->method('evaluate')->willReturn(CallDecision::handled(new SemanticPlan([Action::returns(Expression::literal(Term::constant(7)))])));
        $prepared1Input = new Sources([new Source('app.php', '<?php function target(){return provided()+1;}')]);
        $prepared1Configuration = new DeriverConfig();
        $prepared1Contributions = new Contributions(evaluators: [$evaluator]);
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
        $analysis = new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot);
        $first = $analysis->derive(new ReturnQuery('target'));
        $second = $analysis->derive(new ReturnQuery('target'));
        self::assertNotSame($first, $second);
        self::assertSame(8, $first[0]->result);
        self::assertSame(8, $second[0]->result);
        self::assertSame(1, $first->statistics->evaluatorApplications);
        self::assertSame(1, $second->statistics->evaluatorApplications);
    }
    public function testOneQueryMemoizesItsSharedDependency(): void
    {
        $evaluator = self::createMock(CallEvaluator::class);
        $evaluator->method('descriptor')->willReturn(new CallDescriptor('fixture.provided', '1', 'provided'));
        $evaluator->expects(self::once())->method('evaluate')->willReturn(CallDecision::handled(new SemanticPlan([Action::returns(Expression::literal(Term::constant(7)))])));
        $prepared2Input = new Sources([new Source('app.php', '<?php function target(){$value=provided();return $value+$value;}')]);
        $prepared2Configuration = new DeriverConfig();
        $prepared2Contributions = new Contributions(evaluators: [$evaluator]);
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
        $analysis = new CandidateQuery(new DefinitionIndex($prepared2Program), $prepared2Settings, $prepared2Evaluators, $prepared2Snapshot);
        $result = $analysis->derive(new ReturnQuery('target'));
        self::assertSame(14, $result[0]->result);
        self::assertSame(1, $result->statistics->evaluatorApplications);
    }
    public function testCandidateResultsAreNotRetainedAfterTheCallerDropsThem(): void
    {
        $prepared3Input = new Sources([new Source('app.php', '<?php function target(){return 7;}')]);
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
        $analysis = new CandidateQuery(new DefinitionIndex($prepared3Program), $prepared3Settings, $prepared3Evaluators, $prepared3Snapshot);
        $result = $analysis->derive(new ReturnQuery('target'));
        $reference = WeakReference::create($result);
        unset($result);
        self::assertNull($reference->get());
        self::assertSame(7, $analysis->derive(new ReturnQuery('target'))[0]->result);
    }
    public function testOrderedResultsAreNotRetainedAfterTheCallerDropsThem(): void
    {
        $input = new Sources([new Source('app.php', '<?php function target(){return 7;}')]);
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
        $analysis = new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics())));
        $result = $analysis->derive(new ReturnQuery('target'));
        $reference = WeakReference::create($result);
        unset($result);
        self::assertNull($reference->get());
        self::assertSame(7, $analysis->derive(new ReturnQuery('target'))->normalOutcomes[0]->values['return']->native());
    }
}
