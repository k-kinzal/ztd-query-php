<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Predicate;

use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Micro\Request;
use Deriver\Deriver\Evaluator\Predicate\Branch;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Scope\Context;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Source;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class BranchTest extends TestCase
{
    public function testConditionDefaultsToBothBranchesWithoutOpeningItsInput(): void
    {
        $source = 'function subject(){return missing();}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $result = (new Branch($engine))->condition($frame, $graph->returns()[0][1], 32);
        self::assertCount(2, iterator_to_array((new Choices())->alternatives($result)));
        self::assertSame(0, $engine->context->referenceExpansions);
    }

    public function testEvaluateRequiresNoInstructionOrInputForDefaultSelection(): void
    {
        $source = 'function subject(){return 42;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $site = new \Deriver\Deriver\Evaluator\Micro\Site('branch', '', $graph->body->source, static fn (): Term => Term::constant(99), static fn (): Term => Term::constant(false));
        $result = (new Branch($engine))->evaluate(new Request($engine, $frame, null, 32, site: $site));
        self::assertSame([true,false], array_map(static fn (array $row) => $row[0]->native(), iterator_to_array((new Choices())->alternatives($result))));
    }
    public function testInputRejectsAnAbsentConditionOperand(): void
    {
        $source = 'function subject(){return 42;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $this->expectException(\Deriver\Exception\EvaluationContractException::class);
        (new Branch($engine))->input($frame, $graph->returns()[0][1], 1, 32, true);
    }

}
