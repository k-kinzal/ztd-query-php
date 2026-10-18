<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Recurrence;

use Deriver\Deriver\BranchEvaluators;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Scope\Context;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Source;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class BoundsTest extends TestCase
{
    public function testOfReusesLoopBoundsForEveryStorageLocation(): void
    {
        $source = 'function subject(){for($i=0;$i<1;$i++){}return 1;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig(evaluators: array_map((new MicroEvaluatorConversion())->convert(...), BranchEvaluators::truth()));
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $header = array_values(array_filter($graph->body->blocks, static fn ($block): bool => $block->loopHeader))[0]->id;
        $bounds = new \Deriver\Deriver\Evaluator\Recurrence\Bounds($engine);
        $first = $bounds->of($frame, $header, 32);
        $work = $engine->context->workUnits;
        self::assertSame($first, $bounds->of($frame, $header, 32));
        self::assertSame($work, $engine->context->workUnits);
        self::assertSame(['minimum' => 1, 'maximum' => 1], $first->attributes);
    }

    public function testDeriveDoesNotCountAllIterations(): void
    {
        $source = 'function subject(){for($i=0;$i<10000;$i++){}return 1;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig(evaluators: array_map((new MicroEvaluatorConversion())->convert(...), BranchEvaluators::truth()));
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $header = array_values(array_filter($graph->body->blocks, static fn ($block): bool => $block->loopHeader))[0]->id;
        $bounds = (new \Deriver\Deriver\Evaluator\Recurrence\Bounds($engine))->derive($frame, $header, 32);
        self::assertSame(['minimum' => 1, 'maximum' => -1], $bounds->attributes);
        self::assertLessThan(30, $engine->context->constructedNodes);
    }

    public function testKnownDoesNotExcludeUnknownAlternatives(): void
    {
        $source = 'function subject(){return 1;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $bounds = new \Deriver\Deriver\Evaluator\Recurrence\Bounds($engine);
        $partial = (new Choices())->make([[Term::constant(false), []], [Term::parameter('x'), []]]);
        self::assertNull($bounds->known($partial));
        self::assertFalse($bounds->known(Term::constant(false)));
    }

    public function testResultRetainsTheEvidenceForPruning(): void
    {
        $source = 'function subject(){return 1;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $value = (new \Deriver\Deriver\Evaluator\Recurrence\Bounds($engine))->result(0, 0, [Term::constant(false)]);
        self::assertSame(['minimum' => 0, 'maximum' => 0], $value->attributes);
        self::assertNotNull($engine->context->provenance->get($value));
    }
}
