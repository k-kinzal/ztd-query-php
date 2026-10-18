<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Predicate;

use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Micro\Request;
use Deriver\Deriver\Evaluator\Predicate\Truth;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Scope\Context;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Source;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class TruthTest extends TestCase
{
    public function testValueKeepsTheBooleanCacheSeparateFromFullValueCandidates(): void
    {
        $source = 'function subject($x){return $x?1:2;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $register = $graph->returns()[0][1];
        self::assertSame(true, (new Truth($engine))->value($frame, $register, 32)->native());
        $values = array_map(static fn (array $row) => $row[0]->native(), iterator_to_array((new Choices())->alternatives($engine->value($frame, $register, 32))));
        self::assertSame([1,2], $values);
    }

    public function testEvaluatePropagatesNegationToTheBooleanDemand(): void
    {
        $source = 'function subject(){return !0;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $instruction = $graph->definitions[$graph->returns()[0][1]];
        self::assertSame(true, (new Truth($engine))->evaluate(new Request($engine, $frame, $instruction, 32))->native());
    }
    public function testDefinitionRetainsUnknownPredicates(): void
    {
        $source = 'function subject($x){return $x;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $value = (new Truth($engine))->definition($frame, $graph->returns()[0][1], 32);
        self::assertFalse($value->isConcrete());
        self::assertNotEmpty($value->operands);
    }

    public function testIterationExcludesTheBodyOfAKnownEmptyIterable(): void
    {
        $source = 'function subject(){foreach([] as $v){}return 42;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $instruction = array_values(array_filter($graph->definitions, static fn ($definition): bool => $definition->operation === 'iterate'))[0];
        self::assertSame(false, (new Truth($engine))->iteration(new Request($engine, $frame, $instruction, 32))->native());
    }

}
