<?php

declare (strict_types=1);

namespace Tests\Integration;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class FairEvaluationTest extends TestCase
{
    #[DataProvider('providerBranches')]
    public function testShortAlternativesSurviveAnExpensiveSiblingAndKeepItsRemainder(string $body): void
    {
        $source = 'function expensive($n){return expensive($n-1)+1;} function target($flag){' . $body . '}';
        $configuration = new DeriverConfig();
        $budget = new Budget(transfers: 80);
        $contributions = new Contributions();
        $prepared2Input = new Sources([new Source('candidate.php', '<?php ' . $source)]);
        $prepared2Configuration = $configuration;
        $prepared2Contributions = $contributions;
        $prepared2Target = new TargetProfile($prepared2Configuration->phpVersion, $prepared2Configuration->integerBits, $prepared2Configuration->floatPrecision);
        $prepared2Providers = new ProviderExpansion($prepared2Input, array_map(Term::fromNative(...), $prepared2Configuration->environment), $prepared2Contributions);
        $contributions1 = $prepared2Providers->contributions;
        $prepared2Evaluators = new Evaluators(new TargetProfile($prepared2Configuration->phpVersion, $prepared2Configuration->integerBits, $prepared2Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared2Configuration->standardEvaluators);
        $prepared2Settings = new EvaluatorConfig($prepared2Target, $prepared2Providers->environment, $prepared2Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared2Configuration->evaluators));
        $prepared2Identity = new SnapshotIdentity($prepared2Providers->input, $prepared2Settings, $prepared2Evaluators, $prepared2Providers->manifest);
        $snapshotId = $prepared2Identity->id;
        $input = $prepared2Providers->input;
        $parser = new Parser(new ParserConfig($prepared2Target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared2Program = new Index($snapshotId, $trees);
        $prepared2Snapshot = $prepared2Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared2Program->diagnostics()));
        $result = (new CandidateQuery(new DefinitionIndex($prepared2Program), $prepared2Settings, $prepared2Evaluators, $prepared2Snapshot))->derive(new ReturnQuery('target', budget: $budget));
        $values = array_map(static fn ($candidate) => $candidate->result, iterator_to_array($result));
        $complete = array_values(array_filter($values, static fn ($value): bool => !$value instanceof Term));
        $partial = array_filter($values, static fn ($value): bool => $value instanceof Term);
        self::assertSame([4], $complete);
        self::assertNotEmpty($partial);
        self::assertTrue($result->interrupted);
    }
    /**
     * @return array<string, array{string}>
     */
    public static function providerBranches(): array
    {
        return ['return' => ['if($flag){return expensive(100);}return strlen("abcd");'], 'conditional expression' => ['return $flag ? expensive(100) : strlen("abcd");'], 'reversed branches' => ['return $flag ? strlen("abcd") : expensive(100);'], 'reaching assignment' => ['if($flag){$x=expensive(100);}else{$x=strlen("abcd");}return $x;']];
    }
    #[DataProvider('providerDependencies')]
    public function testShortDependencyAlternativesSurviveAnExpensiveSibling(string $source): void
    {
        $source1 = 'function expensive($n){return expensive($n-1)+1;}' . $source;
        $configuration = new DeriverConfig();
        $budget = new Budget(transfers: 80);
        $contributions = new Contributions();
        $prepared2Input = new Sources([new Source('candidate.php', '<?php ' . $source1)]);
        $prepared2Configuration = $configuration;
        $prepared2Contributions = $contributions;
        $prepared2Target = new TargetProfile($prepared2Configuration->phpVersion, $prepared2Configuration->integerBits, $prepared2Configuration->floatPrecision);
        $prepared2Providers = new ProviderExpansion($prepared2Input, array_map(Term::fromNative(...), $prepared2Configuration->environment), $prepared2Contributions);
        $contributions1 = $prepared2Providers->contributions;
        $prepared2Evaluators = new Evaluators(new TargetProfile($prepared2Configuration->phpVersion, $prepared2Configuration->integerBits, $prepared2Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared2Configuration->standardEvaluators);
        $prepared2Settings = new EvaluatorConfig($prepared2Target, $prepared2Providers->environment, $prepared2Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared2Configuration->evaluators));
        $prepared2Identity = new SnapshotIdentity($prepared2Providers->input, $prepared2Settings, $prepared2Evaluators, $prepared2Providers->manifest);
        $snapshotId = $prepared2Identity->id;
        $input = $prepared2Providers->input;
        $parser = new Parser(new ParserConfig($prepared2Target->version));
        $trees = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input, false));
        $prepared2Program = new Index($snapshotId, $trees);
        $prepared2Snapshot = $prepared2Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared2Program->diagnostics()));
        $result = (new CandidateQuery(new DefinitionIndex($prepared2Program), $prepared2Settings, $prepared2Evaluators, $prepared2Snapshot))->derive(new ReturnQuery('target', budget: $budget));
        $values = array_map(static fn ($candidate) => $candidate->result, iterator_to_array($result));
        self::assertSame([4], array_values(array_filter($values, static fn ($value): bool => !$value instanceof Term)));
        self::assertNotEmpty(array_filter($values, static fn ($value): bool => $value instanceof Term));
        self::assertTrue($result->interrupted);
    }
    /**
     * @return array<string, array{string}>
     */
    public static function providerDependencies(): array
    {
        return ['callers' => ['function target($x){return $x;}function a(){return target(expensive(100));}function b(){return target(strlen("abcd"));}'], 'dynamic dispatch' => ['function slow(){return expensive(100);}function fast(){return strlen("abcd");}function target($flag){$f=$flag?"slow":"fast";return $f();}'], 'conditional declarations' => ['if($flag){function target(){return expensive(100);}}else{function target(){return strlen("abcd");}}']];
    }
    public function testExplicitInputsReceiveWorkBeforeAnExpensiveInputFinishes(): void
    {
        $prepared1Input = new Sources([new Source('candidate.php', '<?php ' . 'function target($n){if($n<=0){return 1;}return target($n-1);}')]);
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
        $result = $session->derive(new ReturnQuery('target', scope: QueryScope::fromEntrypoints([new EntryPoint('target', [Term::constant(100)]), new EntryPoint('target', [Term::constant(0)])]), budget: new Budget(transfers: 80)));
        $values = array_map(static fn ($candidate) => $candidate->result, iterator_to_array($result));
        self::assertSame([1], array_values(array_filter($values, static fn ($value): bool => !$value instanceof Term)));
        self::assertNotEmpty(array_filter($values, static fn ($value): bool => $value instanceof Term));
        self::assertTrue($result->interrupted);
    }
    public function testAnUnresolvedBranchDoesNotPreventAConcreteSibling(): void
    {
        $source = 'function target($flag){return $flag ? external() : strlen("abcd");}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions();
        $prepared2Input = new Sources([new Source('candidate.php', '<?php ' . $source)]);
        $prepared2Configuration = $configuration;
        $prepared2Contributions = $contributions;
        $prepared2Target = new TargetProfile($prepared2Configuration->phpVersion, $prepared2Configuration->integerBits, $prepared2Configuration->floatPrecision);
        $prepared2Providers = new ProviderExpansion($prepared2Input, array_map(Term::fromNative(...), $prepared2Configuration->environment), $prepared2Contributions);
        $contributions1 = $prepared2Providers->contributions;
        $prepared2Evaluators = new Evaluators(new TargetProfile($prepared2Configuration->phpVersion, $prepared2Configuration->integerBits, $prepared2Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared2Configuration->standardEvaluators);
        $prepared2Settings = new EvaluatorConfig($prepared2Target, $prepared2Providers->environment, $prepared2Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared2Configuration->evaluators));
        $prepared2Identity = new SnapshotIdentity($prepared2Providers->input, $prepared2Settings, $prepared2Evaluators, $prepared2Providers->manifest);
        $snapshotId = $prepared2Identity->id;
        $input = $prepared2Providers->input;
        $parser = new Parser(new ParserConfig($prepared2Target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared2Program = new Index($snapshotId, $trees);
        $prepared2Snapshot = $prepared2Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared2Program->diagnostics()));
        $result = (new CandidateQuery(new DefinitionIndex($prepared2Program), $prepared2Settings, $prepared2Evaluators, $prepared2Snapshot))->derive(new ReturnQuery('target', budget: $budget));
        self::assertCount(2, $result);
        self::assertInstanceOf(Term::class, $result[0]->result);
        self::assertSame(4, $result[1]->result);
        self::assertFalse($result->interrupted);
    }
    public function testWaitingDependenciesRetainCorrelationAcrossRepeatedReads(): void
    {
        $source = 'function target($flag){$x=$flag?1:2;return $x+$x;}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions();
        $prepared2Input = new Sources([new Source('candidate.php', '<?php ' . $source)]);
        $prepared2Configuration = $configuration;
        $prepared2Contributions = $contributions;
        $prepared2Target = new TargetProfile($prepared2Configuration->phpVersion, $prepared2Configuration->integerBits, $prepared2Configuration->floatPrecision);
        $prepared2Providers = new ProviderExpansion($prepared2Input, array_map(Term::fromNative(...), $prepared2Configuration->environment), $prepared2Contributions);
        $contributions1 = $prepared2Providers->contributions;
        $prepared2Evaluators = new Evaluators(new TargetProfile($prepared2Configuration->phpVersion, $prepared2Configuration->integerBits, $prepared2Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared2Configuration->standardEvaluators);
        $prepared2Settings = new EvaluatorConfig($prepared2Target, $prepared2Providers->environment, $prepared2Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared2Configuration->evaluators));
        $prepared2Identity = new SnapshotIdentity($prepared2Providers->input, $prepared2Settings, $prepared2Evaluators, $prepared2Providers->manifest);
        $snapshotId = $prepared2Identity->id;
        $input = $prepared2Providers->input;
        $parser = new Parser(new ParserConfig($prepared2Target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared2Program = new Index($snapshotId, $trees);
        $prepared2Snapshot = $prepared2Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared2Program->diagnostics()));
        $result = (new CandidateQuery(new DefinitionIndex($prepared2Program), $prepared2Settings, $prepared2Evaluators, $prepared2Snapshot))->derive(new ReturnQuery('target', budget: $budget));
        $values = array_map(static fn ($candidate) => $candidate->result, iterator_to_array($result));
        sort($values);
        self::assertSame([2, 4], $values);
        self::assertFalse($result->interrupted);
    }
}
