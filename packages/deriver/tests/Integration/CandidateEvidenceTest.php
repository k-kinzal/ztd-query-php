<?php

declare (strict_types=1);

namespace Tests\Integration;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\EvidenceKind;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
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
use Deriver\Deriver\Selection\ExpressionResolution;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class CandidateEvidenceTest extends TestCase
{
    /**
     * @return array<string, array{string, int}> Distinct outer caller selections
     */
    public static function providerCallers(): array
    {
        return ['first' => ['controllerA', 35], 'second' => ['controllerB', 65]];
    }
    #[DataProvider('providerCallers')]
    public function testE01E02OuterCallersDisambiguateAnIdenticalInnerSite(string $caller, int $expected): void
    {
        $source = '<?php function ttl(int $n){return $n+5;} function recipe(int $n){return ttl($n);} function controllerA(){return recipe(30);} function controllerB(){return recipe(60);}';
        $prepared1Input = new Sources([new Source('calls.php', $source)]);
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
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared1Program = new Index($snapshotId, $trees);
        $prepared1Snapshot = $prepared1Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared1Program->diagnostics()));
        $session = new CandidateQuery(new DefinitionIndex($prepared1Program), $prepared1Settings, $prepared1Evaluators, $prepared1Snapshot);
        $result = $session->derive(new ReturnQuery('ttl'));
        self::assertSame([35, 65], array_map(static fn ($candidate) => $candidate->result, iterator_to_array($result, false)));
        $selected = $result->forCaller($caller);
        self::assertCount(1, $selected);
        self::assertSame($expected, $selected[0]->result);
        $kinds = array_column($selected[0]->evidence[0]->nodes(), 'kind');
        self::assertContains(EvidenceKind::ArgumentBinding, $kinds);
        self::assertContains(EvidenceKind::SourceDefinition, $kinds);
        self::assertCount(0, $result->forCaller('absent'));
    }
    public function testE03EqualValuesKeepBothCallers(): void
    {
        $source = '<?php function ttl($n){return $n+5;}function a(){return ttl(30);}function b(){return ttl(30);}';
        $prepared2Input = new Sources([new Source('calls.php', $source)]);
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
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared2Program = new Index($snapshotId, $trees);
        $prepared2Snapshot = $prepared2Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared2Program->diagnostics()));
        $session = new CandidateQuery(new DefinitionIndex($prepared2Program), $prepared2Settings, $prepared2Evaluators, $prepared2Snapshot);
        $result = $session->derive(new ReturnQuery('ttl'));
        self::assertCount(1, $result);
        self::assertCount(2, $result[0]->evidence);
        self::assertCount(1, $result->forCaller('a'));
        self::assertCount(1, $result->forCaller('b'));
    }
    public function testE07E08IndependentQueriesKeepTheirOwnEvidence(): void
    {
        $source = '<?php function f($n){$x=5+$n;observe($x);return $x;}function caller(){return f(30);}';
        $prepared3Input = new Sources([new Source('calls.php', $source)]);
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
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared3Program = new Index($snapshotId, $trees);
        $prepared3Snapshot = $prepared3Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared3Program->diagnostics()));
        $session = new CandidateQuery(new DefinitionIndex($prepared3Program), $prepared3Settings, $prepared3Evaluators, $prepared3Snapshot);
        $query = new ReturnQuery('f');
        $first = iterator_to_array($session->derive($query));
        $start = strrpos($source, '$x');
        self::assertNotFalse($start);
        $session->derive(new ValueQuery((new ExpressionResolution())->expression($prepared3Program, 'calls.php', $start, $start + 2, 'value')));
        $repeated = $session->derive($query);
        self::assertEquals($first, iterator_to_array($repeated));
        unset($session);
        self::assertEquals($first, iterator_to_array($repeated));
    }
    public function testE09LiteralHasExactUtf8AndCrlfSourceCoordinates(): void
    {
        $source = "<?php\r\nfunction f(){ /* 日本語 */ observe(42);observe(65);}\r\n";
        $prepared4Input = new Sources([new Source('unicode.php', $source)]);
        $prepared4Configuration = new DeriverConfig();
        $prepared4Contributions = new Contributions();
        $prepared4Target = new TargetProfile($prepared4Configuration->phpVersion, $prepared4Configuration->integerBits, $prepared4Configuration->floatPrecision);
        $prepared4Providers = new ProviderExpansion($prepared4Input, array_map(Term::fromNative(...), $prepared4Configuration->environment), $prepared4Contributions);
        $contributions = $prepared4Providers->contributions;
        $prepared4Evaluators = new Evaluators(new TargetProfile($prepared4Configuration->phpVersion, $prepared4Configuration->integerBits, $prepared4Configuration->floatPrecision), new Registrations($contributions->evaluators, $contributions->providers, $contributions->domains, $contributions->intrinsics, $contributions->stateSlots), $prepared4Configuration->standardEvaluators);
        $prepared4Settings = new EvaluatorConfig($prepared4Target, $prepared4Providers->environment, $prepared4Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared4Configuration->evaluators));
        $prepared4Identity = new SnapshotIdentity($prepared4Providers->input, $prepared4Settings, $prepared4Evaluators, $prepared4Providers->manifest);
        $snapshotId = $prepared4Identity->id;
        $input = $prepared4Providers->input;
        $parser = new Parser(new ParserConfig($prepared4Target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $prepared4Program = new Index($snapshotId, $trees);
        $prepared4Snapshot = $prepared4Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared4Program->diagnostics()));
        $session = new CandidateQuery(new DefinitionIndex($prepared4Program), $prepared4Settings, $prepared4Evaluators, $prepared4Snapshot);
        $start = strpos($source, '65');
        self::assertNotFalse($start);
        $result = $session->derive(new ValueQuery((new ExpressionResolution())->expression($prepared4Program, 'unicode.php', $start, $start + 2, 'value')));
        self::assertSame(65, $result[0]->result);
        $literals = array_values(array_filter($result[0]->evidence[0]->nodes(), static fn ($node): bool => $node->kind === EvidenceKind::SourceDefinition));
        self::assertCount(1, $literals);
        self::assertNotNull($literals[0]->source);
        self::assertSame([$start, $start + 2, 2, $start - 6, 2, $start - 4], [$literals[0]->source->start, $literals[0]->source->end, $literals[0]->source->line, $literals[0]->source->column, $literals[0]->source->endLine, $literals[0]->source->endColumn]);
    }
}
