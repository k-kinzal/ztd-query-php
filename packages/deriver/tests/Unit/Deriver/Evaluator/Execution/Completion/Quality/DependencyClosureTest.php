<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Completion\Quality;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\Completion\Quality\DependencyClosure as Subject;
use Deriver\Deriver\Evaluator\Execution\EvaluatorConfig as ExecutionEvaluatorConfig;
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
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DependencyClosureTest extends TestCase
{
    public function testUnavailableDependenciesKeepTheResultOpen(): void
    {
        $source = '<?php function target(){return 1;}';
        $symbol = 'target';
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
        $closed = (new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics()))))->derive(new ReturnQuery($symbol));
        $source2 = '<?php function target(){return external();}';
        $symbol1 = 'target';
        $input2 = new Sources([new Source('fixture.php', $source2)]);
        $configuration1 = new DeriverConfig();
        $contributions2 = new Contributions();
        $target1 = new TargetProfile($configuration1->phpVersion, $configuration1->integerBits, $configuration1->floatPrecision);
        $providers1 = new ProviderExpansion($input2, array_map(Term::fromNative(...), $configuration1->environment), $contributions2);
        $contributions3 = $providers1->contributions;
        $evaluators1 = new Evaluators(new TargetProfile($configuration1->phpVersion, $configuration1->integerBits, $configuration1->floatPrecision), new Registrations($contributions3->evaluators, $contributions3->providers, $contributions3->domains, $contributions3->intrinsics, $contributions3->stateSlots), $configuration1->standardEvaluators);
        $settings1 = new EvaluatorConfig($target1, $providers1->environment, $configuration1->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration1->evaluators));
        $identity1 = new SnapshotIdentity($providers1->input, $settings1, $evaluators1, $providers1->manifest);
        $snapshotId1 = $identity1->id;
        $input3 = $providers1->input;
        $parser1 = new Parser(new ParserConfig($target1->version));
        $trees1 = array_map(static fn (Source $source3): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source3->path), $source3->contents, $source3->declarationsOnly))), iterator_to_array($input3, false));
        $program1 = new Index($snapshotId1, $trees1);
        $open = (new QueryExecution($program1, new ExecutionEvaluatorConfig($target1, $providers1->environment, $configuration1->standardEvaluators), $evaluators1, $identity1->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics()))))->derive(new ReturnQuery($symbol1));
        self::assertSame(Subject::Closed, $closed->assessment->closure);
        self::assertSame(Subject::Open, $open->assessment->closure);
        self::assertNotEmpty($open->frontiers);
    }
}
