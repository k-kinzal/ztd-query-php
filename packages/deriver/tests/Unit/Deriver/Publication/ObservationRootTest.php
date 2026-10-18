<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Publication;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\EvidenceKind;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Evidence\Provenance;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Target;
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
use Deriver\Deriver\Publication\ObservationRoot;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ObservationRootTest extends TestCase
{
    public function testOfIdentifiesTheTargetAboveTheValueForest(): void
    {
        $prepared1Input = new Sources([new Source('candidate.php', '<?php ' . 'function target(){return 1;}')]);
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
        $query = new ReturnQuery('target');
        $root = (new ObservationRoot())->of($query, new Target('target', TargetKind::Return, 'target'), Term::constant(1), new DefinitionIndex($prepared1Program), new Provenance());
        self::assertSame(EvidenceKind::Observation, $root->kind);
        self::assertSame('target', $root->attributes['owner']);
        self::assertSame('target', $root->attributes['target']);
        self::assertSame(ReturnQuery::class, $root->attributes['role']);
        self::assertSame('candidate.php', $root->source?->path);
        self::assertArrayHasKey('value', $root->inputs);
    }
}
