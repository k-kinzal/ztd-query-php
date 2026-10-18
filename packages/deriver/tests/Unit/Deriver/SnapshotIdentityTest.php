<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
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
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SnapshotIdentityTest extends TestCase
{
    public function testIdDependsOnlyOnSourcesEvaluatorsEnvironmentAndTarget(): void
    {
        $snapshotIdentity = static function (Sources $input, DeriverConfig $configuration): SnapshotIdentity {
            $prepared1Input = $input;
            $prepared1Configuration = $configuration;
            $prepared1Contributions = new Contributions();
            $prepared1Target = new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision);
            $prepared1Providers = new ProviderExpansion($prepared1Input, array_map(Term::fromNative(...), $prepared1Configuration->environment), $prepared1Contributions);
            $contributions = $prepared1Providers->contributions;
            $prepared1Evaluators = new Evaluators(new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision), new Registrations($contributions->evaluators, $contributions->providers, $contributions->domains, $contributions->intrinsics, $contributions->stateSlots), $prepared1Configuration->standardEvaluators);
            $prepared1Settings = new EvaluatorConfig($prepared1Target, $prepared1Providers->environment, $prepared1Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared1Configuration->evaluators));
            $prepared1Identity = new SnapshotIdentity($prepared1Providers->input, $prepared1Settings, $prepared1Evaluators, $prepared1Providers->manifest);
            $snapshotId = $prepared1Identity->id;
            $input1 = $prepared1Providers->input;
            $parser = new Parser(new ParserConfig($prepared1Target->version));
            $trees = [];
            $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input1, false));
            $prepared1Program = new Index($snapshotId, $trees);
            $prepared1Snapshot = $prepared1Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared1Program->diagnostics()));
            $analysis = new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot);
            return new SnapshotIdentity($prepared1Providers->input, $prepared1Settings, $prepared1Evaluators, $prepared1Providers->manifest);
        };
        $a = new Source('a.php', '<?php function a(){}');
        $b = new Source('b.php', '<?php function b(){}', true);
        $configuration = new DeriverConfig(environment: ['x' => 1]);
        $first = $snapshotIdentity(new Sources([$a, $b]), $configuration);
        $second = $snapshotIdentity(new Sources([$b, $a]), $configuration);
        self::assertSame($first->id, $second->id);
        self::assertSame(['a.php', 'b.php'], array_keys($first->sources));
        self::assertSame(['b.php' => 'declarations'], $first->sourceModes);
        self::assertNotSame($first->id, $snapshotIdentity(new Sources([$a]), $configuration)->id);
        self::assertNotSame($first->id, $snapshotIdentity(new Sources([$a, $b]), new DeriverConfig(environment: ['x' => 2]))->id);
        self::assertNotSame($first->id, $snapshotIdentity(new Sources([new Source('a.php', '<?php function a(){return 2;}'), $b]), $configuration)->id);
    }
    public function testSnapshotPublishesTheIdentityWithTheDeclarationDiagnostics(): void
    {
        $snapshotIdentity = static function (Sources $input, DeriverConfig $configuration): SnapshotIdentity {
            $prepared2Input = $input;
            $prepared2Configuration = $configuration;
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
            $trees = [];
            $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input1, false));
            $prepared2Program = new Index($snapshotId, $trees);
            $prepared2Snapshot = $prepared2Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared2Program->diagnostics()));
            $analysis = new CandidateQuery(new DefinitionIndex($prepared2Program), $prepared2Settings, $prepared2Evaluators, $prepared2Snapshot);
            return new SnapshotIdentity($prepared2Providers->input, $prepared2Settings, $prepared2Evaluators, $prepared2Providers->manifest);
        };
        $identity = $snapshotIdentity(new Sources([new Source('a.php', '<?php function a(){}')]), new DeriverConfig());
        $snapshot = $identity->snapshot([]);
        self::assertSame($identity->id, $snapshot->id);
        self::assertSame($identity->sources, $snapshot->sources);
        self::assertSame($identity->evaluators, $snapshot->evaluators);
        self::assertSame($identity->environmentHash, $snapshot->environmentHash);
        self::assertSame([], $snapshot->diagnostics);
    }
    public function testSnapshotHashesInputValuesWithoutDependingOnTopLevelKeyOrder(): void
    {
        $sources = new Sources([new Source('a.php', '<?php function a(){}')]);
        $prepared3Input = $sources;
        $prepared3Configuration = new DeriverConfig(environment: ['APP_ENV' => 'test', '$_GET' => ['a' => 1, 'b' => 2]]);
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
        $first = $prepared3Snapshot;
        $prepared4Input = $sources;
        $prepared4Configuration = new DeriverConfig(environment: ['$_GET' => ['a' => 1, 'b' => 2], 'APP_ENV' => 'test']);
        $prepared4Contributions = new Contributions();
        $prepared4Target = new TargetProfile($prepared4Configuration->phpVersion, $prepared4Configuration->integerBits, $prepared4Configuration->floatPrecision);
        $prepared4Providers = new ProviderExpansion($prepared4Input, array_map(Term::fromNative(...), $prepared4Configuration->environment), $prepared4Contributions);
        $contributions1 = $prepared4Providers->contributions;
        $prepared4Evaluators = new Evaluators(new TargetProfile($prepared4Configuration->phpVersion, $prepared4Configuration->integerBits, $prepared4Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared4Configuration->standardEvaluators);
        $prepared4Settings = new EvaluatorConfig($prepared4Target, $prepared4Providers->environment, $prepared4Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared4Configuration->evaluators));
        $prepared4Identity = new SnapshotIdentity($prepared4Providers->input, $prepared4Settings, $prepared4Evaluators, $prepared4Providers->manifest);
        $snapshotId1 = $prepared4Identity->id;
        $input1 = $prepared4Providers->input;
        $parser1 = new Parser(new ParserConfig($prepared4Target->version));
        $trees1 = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $prepared4Program = new Index($snapshotId1, $trees1);
        $prepared4Snapshot = $prepared4Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared4Program->diagnostics()));
        $reordered = $prepared4Snapshot;
        $prepared5Input = $sources;
        $prepared5Configuration = new DeriverConfig(environment: ['APP_ENV' => 'test', '$_GET' => ['a' => 1, 'b' => 3]]);
        $prepared5Contributions = new Contributions();
        $prepared5Target = new TargetProfile($prepared5Configuration->phpVersion, $prepared5Configuration->integerBits, $prepared5Configuration->floatPrecision);
        $prepared5Providers = new ProviderExpansion($prepared5Input, array_map(Term::fromNative(...), $prepared5Configuration->environment), $prepared5Contributions);
        $contributions2 = $prepared5Providers->contributions;
        $prepared5Evaluators = new Evaluators(new TargetProfile($prepared5Configuration->phpVersion, $prepared5Configuration->integerBits, $prepared5Configuration->floatPrecision), new Registrations($contributions2->evaluators, $contributions2->providers, $contributions2->domains, $contributions2->intrinsics, $contributions2->stateSlots), $prepared5Configuration->standardEvaluators);
        $prepared5Settings = new EvaluatorConfig($prepared5Target, $prepared5Providers->environment, $prepared5Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared5Configuration->evaluators));
        $prepared5Identity = new SnapshotIdentity($prepared5Providers->input, $prepared5Settings, $prepared5Evaluators, $prepared5Providers->manifest);
        $snapshotId2 = $prepared5Identity->id;
        $input2 = $prepared5Providers->input;
        $parser2 = new Parser(new ParserConfig($prepared5Target->version));
        $trees2 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser2->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $prepared5Program = new Index($snapshotId2, $trees2);
        $prepared5Snapshot = $prepared5Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared5Program->diagnostics()));
        $changed = $prepared5Snapshot;
        $prepared6Input = $sources;
        $prepared6Configuration = new DeriverConfig(environment: ['APP_ENV' => 'test', '$_GET' => ['b' => 2, 'a' => 1]]);
        $prepared6Contributions = new Contributions();
        $prepared6Target = new TargetProfile($prepared6Configuration->phpVersion, $prepared6Configuration->integerBits, $prepared6Configuration->floatPrecision);
        $prepared6Providers = new ProviderExpansion($prepared6Input, array_map(Term::fromNative(...), $prepared6Configuration->environment), $prepared6Contributions);
        $contributions3 = $prepared6Providers->contributions;
        $prepared6Evaluators = new Evaluators(new TargetProfile($prepared6Configuration->phpVersion, $prepared6Configuration->integerBits, $prepared6Configuration->floatPrecision), new Registrations($contributions3->evaluators, $contributions3->providers, $contributions3->domains, $contributions3->intrinsics, $contributions3->stateSlots), $prepared6Configuration->standardEvaluators);
        $prepared6Settings = new EvaluatorConfig($prepared6Target, $prepared6Providers->environment, $prepared6Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared6Configuration->evaluators));
        $prepared6Identity = new SnapshotIdentity($prepared6Providers->input, $prepared6Settings, $prepared6Evaluators, $prepared6Providers->manifest);
        $snapshotId3 = $prepared6Identity->id;
        $input3 = $prepared6Providers->input;
        $parser3 = new Parser(new ParserConfig($prepared6Target->version));
        $trees3 = array_map(static fn (Source $source3): File => (new Resolver())->resolve($parser3->parse(new Source(Path::normalize($source3->path), $source3->contents, $source3->declarationsOnly))), iterator_to_array($input3, false));
        $prepared6Program = new Index($snapshotId3, $trees3);
        $prepared6Snapshot = $prepared6Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared6Program->diagnostics()));
        $nestedOrder = $prepared6Snapshot;
        self::assertSame($first->environmentHash, $reordered->environmentHash);
        self::assertSame($first->id, $reordered->id);
        self::assertNotSame($first->environmentHash, $changed->environmentHash);
        self::assertNotSame($first->id, $changed->id);
        self::assertNotSame($first->environmentHash, $nestedOrder->environmentHash);
        self::assertNotSame($first->id, $nestedOrder->id);
    }
}
