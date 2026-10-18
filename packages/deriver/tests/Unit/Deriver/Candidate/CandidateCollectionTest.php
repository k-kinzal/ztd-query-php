<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Candidate;

use Deriver\Deriver\Candidate\CandidateCollection;
use Deriver\Deriver\Candidate\CandidateStatus;
use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\Budget;
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
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversNothing]
final class CandidateCollectionTest extends TestCase
{
    public function testOffsetGetReturnsTheSameCandidatesAsIteration(): void
    {
        $source = 'function target($x){return $x?35:65;}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions();
        $prepared1Input = new Sources([new Source('candidate.php', '<?php ' . $source)]);
        $prepared1Configuration = $configuration;
        $prepared1Contributions = $contributions;
        $prepared1Target = new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision);
        $prepared1Providers = new ProviderExpansion($prepared1Input, array_map(Term::fromNative(...), $prepared1Configuration->environment), $prepared1Contributions);
        $contributions1 = $prepared1Providers->contributions;
        $prepared1Evaluators = new Evaluators(new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared1Configuration->standardEvaluators);
        $prepared1Settings = new EvaluatorConfig($prepared1Target, $prepared1Providers->environment, $prepared1Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared1Configuration->evaluators));
        $prepared1Identity = new SnapshotIdentity($prepared1Providers->input, $prepared1Settings, $prepared1Evaluators, $prepared1Providers->manifest);
        $snapshotId = $prepared1Identity->id;
        $input = $prepared1Providers->input;
        $parser = new Parser(new ParserConfig($prepared1Target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared1Program = new Index($snapshotId, $trees);
        $prepared1Snapshot = $prepared1Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared1Program->diagnostics()));
        $collection = (new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot))->derive(new ReturnQuery('target', budget: $budget));
        self::assertSame(35, $collection[0]->result);
        self::assertSame(65, $collection[1]->result);
        self::assertSame([$collection[0], $collection[1]], iterator_to_array($collection));
        self::assertArrayNotHasKey('candidates', get_object_vars($collection));
    }
    #[DataProvider('providerMissingOffsets')]
    public function testOffsetGetRejectsMissingAndNonIntegerOffsets(int|string|null $offset): void
    {
        $source = 'function target(){return 42;}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions();
        $prepared1Input = new Sources([new Source('candidate.php', '<?php ' . $source)]);
        $prepared1Configuration = $configuration;
        $prepared1Contributions = $contributions;
        $prepared1Target = new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision);
        $prepared1Providers = new ProviderExpansion($prepared1Input, array_map(Term::fromNative(...), $prepared1Configuration->environment), $prepared1Contributions);
        $contributions1 = $prepared1Providers->contributions;
        $prepared1Evaluators = new Evaluators(new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared1Configuration->standardEvaluators);
        $prepared1Settings = new EvaluatorConfig($prepared1Target, $prepared1Providers->environment, $prepared1Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared1Configuration->evaluators));
        $prepared1Identity = new SnapshotIdentity($prepared1Providers->input, $prepared1Settings, $prepared1Evaluators, $prepared1Providers->manifest);
        $snapshotId = $prepared1Identity->id;
        $input = $prepared1Providers->input;
        $parser = new Parser(new ParserConfig($prepared1Target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared1Program = new Index($snapshotId, $trees);
        $prepared1Snapshot = $prepared1Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared1Program->diagnostics()));
        $collection = (new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot))->derive(new ReturnQuery('target', budget: $budget));
        self::assertFalse($collection->offsetExists($offset));
        $this->expectException(OutOfBoundsException::class);
        $collection->offsetGet($offset);
    }
    /**
     * @return iterable<string, array{int|string|null}>
     */
    public static function providerMissingOffsets(): iterable
    {
        yield 'negative' => [-1];
        yield 'past the end' => [1];
        yield 'numeric string' => ['0'];
        yield 'string' => ['missing'];
        yield 'null' => [null];
    }
    public function testOffsetGetRejectsAnEmptyCollection(): void
    {
        $collection = new CandidateCollection([], new ResultRef('empty'));
        self::assertCount(0, $collection);
        self::assertFalse(isset($collection[0]));
        self::assertNull($collection[0] ?? null);
        $this->expectException(OutOfBoundsException::class);
        $collection[0];
    }
    public function testOffsetExistsIncludesCandidatesWithNullValues(): void
    {
        $source = 'function target(){return null;}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions();
        $prepared1Input = new Sources([new Source('candidate.php', '<?php ' . $source)]);
        $prepared1Configuration = $configuration;
        $prepared1Contributions = $contributions;
        $prepared1Target = new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision);
        $prepared1Providers = new ProviderExpansion($prepared1Input, array_map(Term::fromNative(...), $prepared1Configuration->environment), $prepared1Contributions);
        $contributions1 = $prepared1Providers->contributions;
        $prepared1Evaluators = new Evaluators(new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared1Configuration->standardEvaluators);
        $prepared1Settings = new EvaluatorConfig($prepared1Target, $prepared1Providers->environment, $prepared1Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared1Configuration->evaluators));
        $prepared1Identity = new SnapshotIdentity($prepared1Providers->input, $prepared1Settings, $prepared1Evaluators, $prepared1Providers->manifest);
        $snapshotId = $prepared1Identity->id;
        $input = $prepared1Providers->input;
        $parser = new Parser(new ParserConfig($prepared1Target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared1Program = new Index($snapshotId, $trees);
        $prepared1Snapshot = $prepared1Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared1Program->diagnostics()));
        $collection = (new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot))->derive(new ReturnQuery('target', budget: $budget));
        $candidate = $collection[0] ?? null;
        self::assertNotNull($candidate);
        self::assertSame($collection[0], $candidate);
        self::assertTrue(isset($collection[0]));
        self::assertNull($candidate->result);
        self::assertFalse(isset($collection[1]));
        self::assertNull($collection[1] ?? null);
    }
    public function testOffsetSetRejectsReplacement(): void
    {
        $source = 'function target(){return 42;}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions();
        $prepared1Input = new Sources([new Source('candidate.php', '<?php ' . $source)]);
        $prepared1Configuration = $configuration;
        $prepared1Contributions = $contributions;
        $prepared1Target = new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision);
        $prepared1Providers = new ProviderExpansion($prepared1Input, array_map(Term::fromNative(...), $prepared1Configuration->environment), $prepared1Contributions);
        $contributions1 = $prepared1Providers->contributions;
        $prepared1Evaluators = new Evaluators(new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared1Configuration->standardEvaluators);
        $prepared1Settings = new EvaluatorConfig($prepared1Target, $prepared1Providers->environment, $prepared1Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared1Configuration->evaluators));
        $prepared1Identity = new SnapshotIdentity($prepared1Providers->input, $prepared1Settings, $prepared1Evaluators, $prepared1Providers->manifest);
        $snapshotId = $prepared1Identity->id;
        $input = $prepared1Providers->input;
        $parser = new Parser(new ParserConfig($prepared1Target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared1Program = new Index($snapshotId, $trees);
        $prepared1Snapshot = $prepared1Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared1Program->diagnostics()));
        $collection = (new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot))->derive(new ReturnQuery('target', budget: $budget));
        $original = $collection[0];
        $source2 = 'function target(){return 7;}';
        $configuration1 = new DeriverConfig();
        $budget1 = new Budget();
        $contributions2 = new Contributions();
        $prepared1Input1 = new Sources([new Source('candidate.php', '<?php ' . $source2)]);
        $prepared1Configuration1 = $configuration1;
        $prepared1Contributions1 = $contributions2;
        $prepared1Target1 = new TargetProfile($prepared1Configuration1->phpVersion, $prepared1Configuration1->integerBits, $prepared1Configuration1->floatPrecision);
        $prepared1Providers1 = new ProviderExpansion($prepared1Input1, array_map(Term::fromNative(...), $prepared1Configuration1->environment), $prepared1Contributions1);
        $contributions3 = $prepared1Providers1->contributions;
        $prepared1Evaluators1 = new Evaluators(new TargetProfile($prepared1Configuration1->phpVersion, $prepared1Configuration1->integerBits, $prepared1Configuration1->floatPrecision), new Registrations($contributions3->evaluators, $contributions3->providers, $contributions3->domains, $contributions3->intrinsics, $contributions3->stateSlots), $prepared1Configuration1->standardEvaluators);
        $prepared1Settings1 = new EvaluatorConfig($prepared1Target1, $prepared1Providers1->environment, $prepared1Configuration1->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared1Configuration1->evaluators));
        $prepared1Identity1 = new SnapshotIdentity($prepared1Providers1->input, $prepared1Settings1, $prepared1Evaluators1, $prepared1Providers1->manifest);
        $snapshotId1 = $prepared1Identity1->id;
        $input1 = $prepared1Providers1->input;
        $parser1 = new Parser(new ParserConfig($prepared1Target1->version));
        $trees1 = array_map(static fn (Source $source3): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source3->path), $source3->contents, $source3->declarationsOnly))), iterator_to_array($input1, false));
        $prepared1Program1 = new Index($snapshotId1, $trees1);
        $prepared1Snapshot1 = $prepared1Identity1->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared1Program1->diagnostics()));
        $replacement = (new CandidateQuery(new DefinitionIndex($prepared1Program1), $prepared1Settings1, $prepared1Evaluators1, $prepared1Snapshot1))->derive(new ReturnQuery('target', budget: $budget1))[0];
        $this->expectException(RuntimeException::class);
        try {
            $collection[0] = $replacement;
        } finally {
            self::assertCount(1, $collection);
            self::assertSame($original, $collection[0]);
        }
    }
    public function testOffsetSetRejectsAppend(): void
    {
        $source = 'function target(){return 42;}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions();
        $prepared1Input = new Sources([new Source('candidate.php', '<?php ' . $source)]);
        $prepared1Configuration = $configuration;
        $prepared1Contributions = $contributions;
        $prepared1Target = new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision);
        $prepared1Providers = new ProviderExpansion($prepared1Input, array_map(Term::fromNative(...), $prepared1Configuration->environment), $prepared1Contributions);
        $contributions1 = $prepared1Providers->contributions;
        $prepared1Evaluators = new Evaluators(new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared1Configuration->standardEvaluators);
        $prepared1Settings = new EvaluatorConfig($prepared1Target, $prepared1Providers->environment, $prepared1Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared1Configuration->evaluators));
        $prepared1Identity = new SnapshotIdentity($prepared1Providers->input, $prepared1Settings, $prepared1Evaluators, $prepared1Providers->manifest);
        $snapshotId = $prepared1Identity->id;
        $input = $prepared1Providers->input;
        $parser = new Parser(new ParserConfig($prepared1Target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared1Program = new Index($snapshotId, $trees);
        $prepared1Snapshot = $prepared1Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared1Program->diagnostics()));
        $collection = (new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot))->derive(new ReturnQuery('target', budget: $budget));
        $this->expectException(RuntimeException::class);
        try {
            $collection[] = $collection[0];
        } finally {
            self::assertCount(1, $collection);
            self::assertFalse(isset($collection[1]));
        }
    }
    public function testOffsetUnsetRejectsRemoval(): void
    {
        $source = 'function target(){return 42;}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions();
        $prepared1Input = new Sources([new Source('candidate.php', '<?php ' . $source)]);
        $prepared1Configuration = $configuration;
        $prepared1Contributions = $contributions;
        $prepared1Target = new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision);
        $prepared1Providers = new ProviderExpansion($prepared1Input, array_map(Term::fromNative(...), $prepared1Configuration->environment), $prepared1Contributions);
        $contributions1 = $prepared1Providers->contributions;
        $prepared1Evaluators = new Evaluators(new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared1Configuration->standardEvaluators);
        $prepared1Settings = new EvaluatorConfig($prepared1Target, $prepared1Providers->environment, $prepared1Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared1Configuration->evaluators));
        $prepared1Identity = new SnapshotIdentity($prepared1Providers->input, $prepared1Settings, $prepared1Evaluators, $prepared1Providers->manifest);
        $snapshotId = $prepared1Identity->id;
        $input = $prepared1Providers->input;
        $parser = new Parser(new ParserConfig($prepared1Target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared1Program = new Index($snapshotId, $trees);
        $prepared1Snapshot = $prepared1Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared1Program->diagnostics()));
        $collection = (new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot))->derive(new ReturnQuery('target', budget: $budget));
        $original = $collection[0];
        $this->expectException(RuntimeException::class);
        try {
            unset($collection[0]);
        } finally {
            self::assertCount(1, $collection);
            self::assertSame($original, $collection[0]);
        }
    }
    public function testGetIteratorCannotChangeTheCollection(): void
    {
        $source = 'function target(){return 42;}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions();
        $prepared1Input = new Sources([new Source('candidate.php', '<?php ' . $source)]);
        $prepared1Configuration = $configuration;
        $prepared1Contributions = $contributions;
        $prepared1Target = new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision);
        $prepared1Providers = new ProviderExpansion($prepared1Input, array_map(Term::fromNative(...), $prepared1Configuration->environment), $prepared1Contributions);
        $contributions1 = $prepared1Providers->contributions;
        $prepared1Evaluators = new Evaluators(new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared1Configuration->standardEvaluators);
        $prepared1Settings = new EvaluatorConfig($prepared1Target, $prepared1Providers->environment, $prepared1Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared1Configuration->evaluators));
        $prepared1Identity = new SnapshotIdentity($prepared1Providers->input, $prepared1Settings, $prepared1Evaluators, $prepared1Providers->manifest);
        $snapshotId = $prepared1Identity->id;
        $input = $prepared1Providers->input;
        $parser = new Parser(new ParserConfig($prepared1Target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared1Program = new Index($snapshotId, $trees);
        $prepared1Snapshot = $prepared1Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared1Program->diagnostics()));
        $collection = (new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot))->derive(new ReturnQuery('target', budget: $budget));
        $original = $collection[0];
        $iterator = $collection->getIterator();
        unset($iterator[0]);
        self::assertCount(1, $collection);
        self::assertSame($original, $collection[0]);
    }
    public function testCountIncludesPartialCandidates(): void
    {
        $source = 'function target($x){return $x;}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions();
        $prepared1Input = new Sources([new Source('candidate.php', '<?php ' . $source)]);
        $prepared1Configuration = $configuration;
        $prepared1Contributions = $contributions;
        $prepared1Target = new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision);
        $prepared1Providers = new ProviderExpansion($prepared1Input, array_map(Term::fromNative(...), $prepared1Configuration->environment), $prepared1Contributions);
        $contributions1 = $prepared1Providers->contributions;
        $prepared1Evaluators = new Evaluators(new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared1Configuration->standardEvaluators);
        $prepared1Settings = new EvaluatorConfig($prepared1Target, $prepared1Providers->environment, $prepared1Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared1Configuration->evaluators));
        $prepared1Identity = new SnapshotIdentity($prepared1Providers->input, $prepared1Settings, $prepared1Evaluators, $prepared1Providers->manifest);
        $snapshotId = $prepared1Identity->id;
        $input = $prepared1Providers->input;
        $parser = new Parser(new ParserConfig($prepared1Target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared1Program = new Index($snapshotId, $trees);
        $prepared1Snapshot = $prepared1Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared1Program->diagnostics()));
        self::assertSame(1, (new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot))->derive(new ReturnQuery('target', budget: $budget))->count());
    }
    public function testGetIteratorReturnsCandidateRecords(): void
    {
        $source = 'function target(){return 42;}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions();
        $prepared1Input = new Sources([new Source('candidate.php', '<?php ' . $source)]);
        $prepared1Configuration = $configuration;
        $prepared1Contributions = $contributions;
        $prepared1Target = new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision);
        $prepared1Providers = new ProviderExpansion($prepared1Input, array_map(Term::fromNative(...), $prepared1Configuration->environment), $prepared1Contributions);
        $contributions1 = $prepared1Providers->contributions;
        $prepared1Evaluators = new Evaluators(new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared1Configuration->standardEvaluators);
        $prepared1Settings = new EvaluatorConfig($prepared1Target, $prepared1Providers->environment, $prepared1Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared1Configuration->evaluators));
        $prepared1Identity = new SnapshotIdentity($prepared1Providers->input, $prepared1Settings, $prepared1Evaluators, $prepared1Providers->manifest);
        $snapshotId = $prepared1Identity->id;
        $input = $prepared1Providers->input;
        $parser = new Parser(new ParserConfig($prepared1Target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared1Program = new Index($snapshotId, $trees);
        $prepared1Snapshot = $prepared1Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared1Program->diagnostics()));
        $candidate = (new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot))->derive(new ReturnQuery('target', budget: $budget))->getIterator()[0];
        self::assertNotNull($candidate);
        self::assertSame(CandidateStatus::Analyzed, $candidate->type);
    }
    public function testForCallerRetainsSameContextAmbiguity(): void
    {
        $source = 'function target($x){return $x?35:65;}function caller($x){return target($x);}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions();
        $prepared1Input = new Sources([new Source('candidate.php', '<?php ' . $source)]);
        $prepared1Configuration = $configuration;
        $prepared1Contributions = $contributions;
        $prepared1Target = new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision);
        $prepared1Providers = new ProviderExpansion($prepared1Input, array_map(Term::fromNative(...), $prepared1Configuration->environment), $prepared1Contributions);
        $contributions1 = $prepared1Providers->contributions;
        $prepared1Evaluators = new Evaluators(new TargetProfile($prepared1Configuration->phpVersion, $prepared1Configuration->integerBits, $prepared1Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared1Configuration->standardEvaluators);
        $prepared1Settings = new EvaluatorConfig($prepared1Target, $prepared1Providers->environment, $prepared1Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared1Configuration->evaluators));
        $prepared1Identity = new SnapshotIdentity($prepared1Providers->input, $prepared1Settings, $prepared1Evaluators, $prepared1Providers->manifest);
        $snapshotId = $prepared1Identity->id;
        $input = $prepared1Providers->input;
        $parser = new Parser(new ParserConfig($prepared1Target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared1Program = new Index($snapshotId, $trees);
        $prepared1Snapshot = $prepared1Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared1Program->diagnostics()));
        $result = (new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot))->derive(new ReturnQuery('target', budget: $budget));
        self::assertCount(2, $result->forCaller('caller'));
    }
}
