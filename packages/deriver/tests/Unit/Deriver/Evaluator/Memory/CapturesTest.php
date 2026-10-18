<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Memory;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\ValueQuery;
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
use Deriver\Deriver\Sources\Sources;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CapturesTest extends TestCase
{
    public function testValueFindsALexicalCaptureWithoutInvokingTheCallback(): void
    {
        $prepared1Input = new Sources([new Source('candidate.php', '<?php ' . 'function owner(){$x="users";register(function()use($x){observe($x);});}')]);
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
        $result = $s->derive(new ValueQuery((new CallObservations($prepared1Program, $prepared1Evaluators))->find('observe')[0]->argument(0)));
        $slot = 'value';
        self::assertSame(['users'], array_map(static fn (Term $value) => $value->native(), array_map(static fn ($candidate): Term => $candidate->result instanceof Term ? $candidate->result : Term::fromNative($candidate->result), iterator_to_array($result, false))));
    }
    public function testAtRetainsTheDefinitionAtClosureCreation(): void
    {
        $prepared2Input = new Sources([new Source('candidate.php', '<?php ' . 'function owner(){$x=1;register(function()use($x){observe($x);});$x=2;}')]);
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
        $result = $s->derive(new ValueQuery((new CallObservations($prepared2Program, $prepared2Evaluators))->find('observe')[0]->argument(0)));
        $slot = 'value';
        self::assertSame([1], array_map(static fn (Term $value) => $value->native(), array_map(static fn ($candidate): Term => $candidate->result instanceof Term ? $candidate->result : Term::fromNative($candidate->result), iterator_to_array($result, false))));
    }

    public function testEffectWritesOnlyReferenceCaptures(): void
    {
        $source = new Source('subject.php', '<?php function subject() { $a = 1; $b = 2; $callback = function () use (&$a, $b) { $a = 17; $b = 19; }; $callback(); return [$a, $b]; }');
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse($source));
        $index = new DefinitionIndex(new Index('test', [$tree]));
        $profile = new TargetProfile('8.3');
        $engine = new \Deriver\Deriver\Evaluator\Evaluator(new \Deriver\Deriver\Evaluator\Scope\Context($index, new EvaluatorConfig($profile), new Evaluators($profile), new \Deriver\Deriver\Evaluator\Request\Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $value = $engine->returns(new \Deriver\Deriver\Evaluator\Scope\Frame($graph, 'subject'), 64);
        $alternatives = iterator_to_array((new \Deriver\Deriver\Evaluator\Enumeration\Choices())->alternatives($value), false);
        self::assertCount(1, $alternatives);
        self::assertSame([17, 2], $alternatives[0][0]->native());
    }
    public function testContainsDistinguishesValueAndReferenceCapture(): void
    {
        $source = new Source('subject.php', '<?php function subject() { $a = 1; $b = 2; $callback = function () use (&$a, $b) { $a = 17; $b = 19; }; $callback(); return [$a, $b]; }');
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse($source));
        $index = new DefinitionIndex(new Index('test', [$tree]));
        $profile = new TargetProfile('8.3');
        $engine = new \Deriver\Deriver\Evaluator\Evaluator(new \Deriver\Deriver\Evaluator\Scope\Context($index, new EvaluatorConfig($profile), new Evaluators($profile), new \Deriver\Deriver\Evaluator\Request\Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        self::assertTrue((new \Deriver\Deriver\Evaluator\Memory\Captures())->contains($engine, new \Deriver\Deriver\Evaluator\Scope\Frame($graph, 'subject'), 'a'));
        self::assertFalse((new \Deriver\Deriver\Evaluator\Memory\Captures())->contains($engine, new \Deriver\Deriver\Evaluator\Scope\Frame($graph, 'subject'), 'b'));
    }
}
