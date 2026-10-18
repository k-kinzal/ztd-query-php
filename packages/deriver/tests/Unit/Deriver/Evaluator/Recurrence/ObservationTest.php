<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Recurrence;

use Deriver\Deriver\BranchEvaluators;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Recurrence\Observation;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Scope\Context;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Source;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ObservationTest extends TestCase
{
    public function testFrameUsesEntryBindingsOnlyInsideOnePassBodies(): void
    {
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php function subject(){for($i=0;$i<1;$i++){$v=$i;}return $v;}')));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig(evaluators: array_map((new MicroEvaluatorConversion())->convert(...), BranchEvaluators::truth()));
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $header = array_values(array_filter($graph->body->blocks, static fn ($block): bool => $block->loopHeader))[0];
        $observation = new Observation($engine);
        self::assertSame([$header->id => 0], $observation->frame($frame, $header->terminator->targets[0], 32)->iterations);
        self::assertSame($frame, $observation->frame($frame, $header->terminator->targets[1], 32));
    }

    public function testConditionSeparatesEntryAndExitObservations(): void
    {
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php function subject(){for($i=0;$i<1;$i++){$v=$i;}return $v;}')));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig(evaluators: array_map((new MicroEvaluatorConversion())->convert(...), BranchEvaluators::truth()));
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $header = array_values(array_filter($graph->body->blocks, static fn ($block): bool => $block->loopHeader))[0];
        $observation = new Observation($engine);
        self::assertSame([$header->id => 0], $observation->condition($frame, $header->id, true, 32)->iterations);
        self::assertSame([$header->id => 1], $observation->condition($frame, $header->id, false, 32)->iterations);
    }

    public function testBodiesSharesStructuralMembershipAcrossObservations(): void
    {
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php function subject(){for($i=0;$i<10000;$i++){$v=$i;}return $v;}')));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $header = array_values(array_filter($graph->body->blocks, static fn ($block): bool => $block->loopHeader))[0];
        $observation = new Observation($engine);
        $bodies = $observation->bodies($frame);
        self::assertArrayHasKey($header->terminator->targets[0], $bodies[$header->id]);
        self::assertArrayNotHasKey($header->terminator->targets[1], $bodies[$header->id]);
        self::assertSame($bodies, $graph->loopBodies);
        self::assertSame($bodies, $observation->bodies($frame));
        self::assertSame($frame, $observation->frame($frame, $header->terminator->targets[0], 32));
    }
}
