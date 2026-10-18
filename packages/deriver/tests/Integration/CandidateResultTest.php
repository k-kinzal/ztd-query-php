<?php

declare (strict_types=1);

namespace Tests\Integration;

use Deriver\Deriver\Candidate\CandidateStatus;
use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\ParameterQuery;
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
use Deriver\Deriver\Selection\CallObservations;
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
final class CandidateResultTest extends TestCase
{
    /**
     * @return list<array{string}> Equivalent statement and expression forms
     */
    public static function providerThrows(): array
    {
        return [['throw new RuntimeException();'], ['return throw new RuntimeException();']];
    }
    public function testT06UncalledParameterDoesNotTakeItsDefault(): void
    {
        $prepared1Input = new Sources([new Source('defaults.php', '<?php function f($x=30){return $x;}')]);
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
        $result = $session->derive(new ParameterQuery('f', 'x'));
        self::assertSame(CandidateStatus::Partials, $result[0]->type);
        self::assertInstanceOf(Term::class, $result[0]->result);
        self::assertSame('$x', $result[0]->result->literal);
        $prepared2Input = new Sources([new Source('defaults.php', '<?php function f($x=30){return $x;}function caller(){return f();}')]);
        $prepared2Configuration = new DeriverConfig();
        $prepared2Contributions = new Contributions();
        $prepared2Target = new TargetProfile($prepared2Configuration->phpVersion, $prepared2Configuration->integerBits, $prepared2Configuration->floatPrecision);
        $prepared2Providers = new ProviderExpansion($prepared2Input, array_map(Term::fromNative(...), $prepared2Configuration->environment), $prepared2Contributions);
        $contributions1 = $prepared2Providers->contributions;
        $prepared2Evaluators = new Evaluators(new TargetProfile($prepared2Configuration->phpVersion, $prepared2Configuration->integerBits, $prepared2Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared2Configuration->standardEvaluators);
        $prepared2Settings = new EvaluatorConfig($prepared2Target, $prepared2Providers->environment, $prepared2Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared2Configuration->evaluators));
        $prepared2Identity = new SnapshotIdentity($prepared2Providers->input, $prepared2Settings, $prepared2Evaluators, $prepared2Providers->manifest);
        $snapshotId1 = $prepared2Identity->id;
        $input1 = $prepared2Providers->input;
        $parser1 = new Parser(new ParserConfig($prepared2Target->version));
        $trees1 = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $prepared2Program = new Index($snapshotId1, $trees1);
        $prepared2Snapshot = $prepared2Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared2Program->diagnostics()));
        $called = new CandidateQuery(new DefinitionIndex($prepared2Program), $prepared2Settings, $prepared2Evaluators, $prepared2Snapshot);
        self::assertSame(30, $called->derive(new ReturnQuery('caller'))[0]->result);
    }
    #[DataProvider('providerThrows')]
    public function testThrowOnlyDoesNotManufactureNull(string $body): void
    {
        $prepared3Input = new Sources([new Source('throw.php', '<?php function f(){' . $body . '}')]);
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
        $session = new CandidateQuery(new DefinitionIndex($prepared3Program), $prepared3Settings, $prepared3Evaluators, $prepared3Snapshot);
        $result = $session->derive(new ReturnQuery('f'));
        self::assertCount(1, $result);
        self::assertSame(CandidateStatus::Partials, $result[0]->type);
        self::assertSame('never', $result[0]->type_name);
        self::assertInstanceOf(Term::class, $result[0]->result);
        self::assertSame('NO_VALUE_DEFINITION', $result[0]->result->attributes['reason']);
    }
    public function testT12SqlExampleKeepsDefaultAndUnresolvedBranches(): void
    {
        $prepared4Input = new Sources([new Source('sql.php', '<?php function select($table){if($table===null){$table="default_table";}$sql="SELECT * FROM $table";observe($sql);}')]);
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
        $trees = array_map(static fn (Source $source): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source->path), $source->contents, $source->declarationsOnly))), iterator_to_array($input, false));
        $prepared4Program = new Index($snapshotId, $trees);
        $prepared4Snapshot = $prepared4Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared4Program->diagnostics()));
        $session = new CandidateQuery(new DefinitionIndex($prepared4Program), $prepared4Settings, $prepared4Evaluators, $prepared4Snapshot);
        $result = $session->derive(new ValueQuery((new CallObservations($prepared4Program, $prepared4Evaluators))->find('observe')[0]->argument(0)));
        self::assertCount(2, $result);
        self::assertSame('SELECT * FROM default_table', $result[0]->result);
        self::assertSame(CandidateStatus::Partials, $result[1]->type);
        $prepared5Input = new Sources([new Source('sql.php', '<?php function select($table){$table="SELECT * FROM $table";observe($sql);}')]);
        $prepared5Configuration = new DeriverConfig();
        $prepared5Contributions = new Contributions();
        $prepared5Target = new TargetProfile($prepared5Configuration->phpVersion, $prepared5Configuration->integerBits, $prepared5Configuration->floatPrecision);
        $prepared5Providers = new ProviderExpansion($prepared5Input, array_map(Term::fromNative(...), $prepared5Configuration->environment), $prepared5Contributions);
        $contributions1 = $prepared5Providers->contributions;
        $prepared5Evaluators = new Evaluators(new TargetProfile($prepared5Configuration->phpVersion, $prepared5Configuration->integerBits, $prepared5Configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $prepared5Configuration->standardEvaluators);
        $prepared5Settings = new EvaluatorConfig($prepared5Target, $prepared5Providers->environment, $prepared5Configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $prepared5Configuration->evaluators));
        $prepared5Identity = new SnapshotIdentity($prepared5Providers->input, $prepared5Settings, $prepared5Evaluators, $prepared5Providers->manifest);
        $snapshotId1 = $prepared5Identity->id;
        $input1 = $prepared5Providers->input;
        $parser1 = new Parser(new ParserConfig($prepared5Target->version));
        $trees1 = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $prepared5Program = new Index($snapshotId1, $trees1);
        $prepared5Snapshot = $prepared5Identity->snapshot(array_map(Frontier::fromDiagnostic(...), $prepared5Program->diagnostics()));
        $typo = new CandidateQuery(new DefinitionIndex($prepared5Program), $prepared5Settings, $prepared5Evaluators, $prepared5Snapshot);
        $unknown = $typo->derive(new ValueQuery((new CallObservations($prepared5Program, $prepared5Evaluators))->find('observe')[0]->argument(0)));
        self::assertInstanceOf(Term::class, $unknown[0]->result);
        self::assertSame('$sql', $unknown[0]->result->literal);
    }
}
