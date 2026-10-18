<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Expression;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
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
final class GuardsTest extends TestCase
{
    public function testEdgeDoesNotEnumerateAnIrrelevantCondition(): void
    {
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('guard.php', '<?php function target($flag) { if ($flag) { return 1; } return 2; }')));
        $context = new Context(new DefinitionIndex(new Index('guard', [$tree])), new EvaluatorConfig(), new Evaluators(), new Budget(partitions: 2));
        $graph = $context->index->graph('target');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:target');
        $branch = $graph->body->blocks[0]->terminator;
        $condition = (new Choices())->make([
            [Term::parameter('a'), ['origin' => 'a']],
            [Term::parameter('b'), ['origin' => 'b']],
            [Term::parameter('c'), ['origin' => 'c']],
        ]);
        $context->values[$frame->identity . ':' . $branch->operand] = $condition;
        $value = Term::constant(9);
        $result = (new \Deriver\Deriver\Evaluator\Expression\Guards(new Evaluator($context)))->edge($frame, 0, $branch->targets[0], $value, 64);
        $rows = iterator_to_array((new Choices())->alternatives($result), false);

        self::assertCount(1, $rows);
        self::assertSame(9, $rows[0][0]->native());
        self::assertSame([], $context->recorded);
        self::assertSame([$frame->identity . ':condition:' . $branch->operand => true], $rows[0][1]);
    }

    public function testAtKeepsBothSymbolicBranchIdentities(): void
    {
        $source = 'function target($x){if($x){return 1;}return 2;}';
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
        $r = $e->returns(new Frame($graph, 'source:' . $symbol), 64);
        self::assertCount(2, iterator_to_array((new Choices())->alternatives($r), false));
    }
    public function testEdgeRetainsBothBranchesOfAConstantPredicate(): void
    {
        $source = 'function target(){if(false){return missing();}return 2;}';
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
        self::assertCount(2, $values);
        $values = array_values($values);
        self::assertInstanceOf(Term::class, $values[0]);
        self::assertInstanceOf(Term::class, $values[1]);
        self::assertSame(\Deriver\Deriver\Evaluator\TermKind::Call, $values[0]->kind);
        self::assertSame(2, $values[1]->native());
    }
    public function testWalkKeepsAnExcludedBlockEmpty(): void
    {
        $source = 'function subject(){if(false)return 1;return 2;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig(evaluators: array_map((new MicroEvaluatorConversion())->convert(...), \Deriver\Deriver\BranchEvaluators::truth()));
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $value = (new \Deriver\Deriver\Evaluator\Expression\Guards($engine))->walk($frame, $graph->returns()[0][0], Term::constant(7), 32);
        self::assertSame([], iterator_to_array((new Choices())->alternatives($value)));
    }
}
