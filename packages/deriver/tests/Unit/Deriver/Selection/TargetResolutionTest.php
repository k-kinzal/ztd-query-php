<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Selection;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Projection;
use Deriver\Deriver\Evaluator\Request\ParameterQuery;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Request\StateQuery;
use Deriver\Deriver\Evaluator\Request\TupleQuery;
use Deriver\Deriver\Evaluator\Request\ValueQuery;
use Deriver\Deriver\Evaluator\TargetKind;
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
use Deriver\Deriver\Selection\TargetResolution;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\InvalidInputException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class TargetResolutionTest extends TestCase
{
    public function testResolveBindsEverySupportedQueryFormToItsOwnerAndSelection(): void
    {
        $prepared1Input = new Sources([new Source('candidate.php', '<?php ' . 'function target($x){$y = $x; observe($y, 2);}')]);
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
        $resolution = new TargetResolution($prepared1Program, $prepared1Snapshot->id);
        $site = (new CallObservations($prepared1Program, $prepared1Evaluators))->find('observe')[0];
        $return = $resolution->resolve(new ReturnQuery('target'));
        self::assertSame(['target', TargetKind::Return, 'target'], [$return->owner, $return->kind, $return->selector]);
        $parameter = $resolution->resolve(new ParameterQuery('target', 'x'));
        self::assertSame(['target', TargetKind::Parameter, 'x', 'x'], [$parameter->owner, $parameter->kind, $parameter->selector, $parameter->parameter]);
        $value = $resolution->resolve(new ValueQuery($site->argument(0), new Projection([0])));
        self::assertSame(['target', TargetKind::Value, $site->argument(0)->register], [$value->owner, $value->kind, $value->register]);
        self::assertSame($site->argument(0)->source, $value->source);
        self::assertSame([0], $value->projection?->path);
        $tuple = $resolution->resolve(new TupleQuery($site->beforeInvocation(), ['left' => $site->argument(0), 'right' => $site->argument(1)]));
        self::assertSame(TargetKind::Tuple, $tuple->kind);
        self::assertSame(['left' => $site->argument(0)->register, 'right' => $site->argument(1)->register], $tuple->registers);
        $state = $resolution->resolve(new StateQuery($site->beforeInvocation(), 'y'));
        self::assertSame(['target', TargetKind::State, 'y', 'y'], [$state->owner, $state->kind, $state->selector, $state->variable]);
        self::assertSame($site->beforeInvocation()->instruction, $state->point?->instruction);
    }
    public function testResolveRejectsAReferenceFromAnotherSnapshot(): void
    {
        $prepared2Input = new Sources([new Source('candidate.php', '<?php ' . 'function target($x){observe($x);}')]);
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
        $session = new CandidateQuery(new DefinitionIndex($prepared2Program), $prepared2Settings, $prepared2Evaluators, $prepared2Snapshot);
        $prepared3Input = new Sources([new Source('candidate.php', '<?php ' . 'function target($x){observe($x, 1);}')]);
        $prepared3Configuration = new DeriverConfig();
        $prepared3Contributions = new Contributions();
        $prepared3Target = new TargetProfile($prepared3Configuration->phpVersion, $prepared3Configuration->integerBits, $prepared3Configuration->floatPrecision);
        $prepared3Providers = new ProviderExpansion($prepared3Input, array_map(Term::fromNative(...), $prepared3Configuration->environment), $prepared3Contributions);
        $contributions1 = $prepared3Providers->contributions;
        $prepared3Evaluators = new Evaluators(new TargetProfile($prepared3Configuration->phpVersion, $prepared3Configuration->integerBits, $prepared3Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared3Configuration->standardEvaluators);
        $prepared3Settings = new EvaluatorConfig($prepared3Target, $prepared3Providers->environment, $prepared3Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared3Configuration->evaluators));
        $prepared3Identity = new SnapshotIdentity($prepared3Providers->input, $prepared3Settings, $prepared3Evaluators, $prepared3Providers->manifest);
        $snapshotId1 = $prepared3Identity->id;
        $input1 = $prepared3Providers->input;
        $parser1 = new Parser(new ParserConfig($prepared3Target->version));
        $trees1 = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $prepared3Program = new Index($snapshotId1, $trees1);
        $prepared3Snapshot = $prepared3Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared3Program->diagnostics()));
        $other = new CandidateQuery(new DefinitionIndex($prepared3Program), $prepared3Settings, $prepared3Evaluators, $prepared3Snapshot);
        $this->expectException(InvalidInputException::class);
        (new TargetResolution($prepared2Program, $prepared2Snapshot->id))->resolve(new ValueQuery((new CallObservations($prepared3Program, $prepared3Evaluators))->find('observe')[0]->argument(0)));
    }
}
