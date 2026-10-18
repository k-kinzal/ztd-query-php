<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Micro;

use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Micro\Request;
use Deriver\Deriver\Evaluator\Micro\ReturnEvaluator;
use Deriver\Deriver\Evaluator\Micro\Site;
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
final class ReturnEvaluatorTest extends TestCase
{
    public function testSelectFallsBackToTheCapturedReturn(): void
    {
        $source = 'function subject(){return 42;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $result = (new ReturnEvaluator())->select($engine, $frame, 32);
        self::assertSame([42], array_map(static fn (array $row) => $row[0]->native(), iterator_to_array((new Choices())->alternatives($result))));
    }

    public function testEvaluateKeepsConditionalDefinitionsInTheFallback(): void
    {
        $source = 'if(condition()){function subject(){return 1;}}else{function subject(){return 2;}}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $site = new Site('function', 'subject', $graph->body->source, static fn (): Term => Term::constant(99), static fn (): Term => Term::constant(true));
        $result = (new ReturnEvaluator())->evaluate(new Request($engine, $frame, null, 32, site: $site));
        self::assertSame([1,2], array_map(static fn (array $row) => $row[0]->native(), iterator_to_array((new Choices())->alternatives($result))));
    }
    public function testInputResolvesOnlyTheNamedFormalParameter(): void
    {
        $source = 'function subject($value){return missing();}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $frame = new Frame($graph, 'entry', ['value' => Term::constant(42)]);
        $evaluator = new ReturnEvaluator();
        self::assertSame(42, $evaluator->input($engine, $frame, 'value', 32)->native());
        self::assertSame(0, $engine->context->referenceExpansions);
    }

}
