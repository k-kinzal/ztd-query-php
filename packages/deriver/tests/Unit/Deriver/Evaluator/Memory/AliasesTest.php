<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Memory;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Expression\Storage;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Memory\Aliases;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Scope\Context;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Providers\ProviderExpansion;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class AliasesTest extends TestCase
{
    public function testKeyTracksTheCellAssignedThroughAnotherName(): void
    {
        $source = 'function target(){$x=1;$y=&$x;$y=4;return $x;}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion(new Sources([new Source('candidate.php', '<?php ' . $source)]), array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $snapshotId = 'test';
        $input = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $program = new Index($snapshotId, $trees);
        $e = new Evaluator(new Context(new DefinitionIndex($program), $settings, $evaluators, $budget));
        $symbol = 'target';
        $graph = $e->context->index->graph($symbol);
        self::assertNotNull($graph);
        $alternatives = (new Choices())->alternatives($e->returns(new Frame($graph, 'source:' . $symbol), 64));
        $values = array_reduce(iterator_to_array($alternatives, false), static function (array $values, array $alternative): array {
            [$value, $_] = $alternative;
            $values[(new Identity())->key($value)] = $value;
            return $values;
        }, []);
        self::assertCount(1, $values);
        $resolvedValue = array_values($values)[0];
        self::assertInstanceOf(Term::class, $resolvedValue);
        self::assertSame(4, $resolvedValue->native());
    }
    public function testMapKeepsAnAliasBoundToItsCell(): void
    {
        $source = 'function target(){$x=1;$y=&$x;return $y;}';
        $configuration = new DeriverConfig();
        $budget = new Budget();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion(new Sources([new Source('candidate.php', '<?php ' . $source)]), array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $snapshotId = 'test';
        $input = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $program = new Index($snapshotId, $trees);
        $e = new Evaluator(new Context(new DefinitionIndex($program), $settings, $evaluators, $budget));
        $symbol = 'target';
        $graph = $e->context->index->graph($symbol);
        self::assertNotNull($graph);
        $f = new Frame($graph, 'source:' . $symbol);
        $s = new Storage($e);
        $map = (new Aliases())->map($s, $f, 0, count($f->graph->body->blocks[0]->instructions));
        self::assertSame('local:x', $map['local:y']);
    }
    public function testMergeMarksConflictingCellsInsteadOfUnitingThem(): void
    {
        self::assertSame(['x' => 'unresolved-alias:x'], (new Aliases())->merge([['x' => 'y'], []]));
    }

    public function testReturnedAliasesTheNamedReferenceArgument(): void
    {
        $source = new Source('subject.php', '<?php function &slot(&$first, &$second) { return $second; } function subject() { $a = 1; $b = 2; $alias =& slot(second: $b, first: $a); $alias = 17; return [$a, $b]; }');
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse($source));
        $index = new DefinitionIndex(new Index('test', [$tree]));
        $profile = new TargetProfile('8.3');
        $engine = new Evaluator(new Context($index, new EvaluatorConfig($profile), new Evaluators($profile), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $value = $engine->returns(new Frame($graph, 'subject'), 64);
        $alternatives = iterator_to_array((new Choices())->alternatives($value), false);
        self::assertCount(1, $alternatives);
        self::assertSame([1, 17], $alternatives[0][0]->native());
    }
}
