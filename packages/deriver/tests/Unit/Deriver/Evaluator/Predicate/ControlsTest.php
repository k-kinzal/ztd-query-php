<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Predicate;

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
final class ControlsTest extends TestCase
{
    public function testApplySharesConditionsWithoutSharingTheObservedValue(): void
    {
        $source = 'function subject($x){if($x)return 1;return 2;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig(evaluators: array_map((new MicroEvaluatorConversion())->convert(...), BranchEvaluators::truth()));
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $block = $graph->returns()[0][0];
        $controls = new \Deriver\Deriver\Evaluator\Predicate\Controls($engine);
        $first = $controls->apply($frame, $block, Term::constant(7), 32);
        $work = $engine->context->workUnits;
        $second = $controls->apply($frame, $block, Term::constant(9), 32);
        self::assertSame($work, $engine->context->workUnits);
        self::assertSame([7], array_map(static fn (array $row) => $row[0]->native(), iterator_to_array((new Choices())->alternatives($first))));
        self::assertSame([9], array_map(static fn (array $row) => $row[0]->native(), iterator_to_array((new Choices())->alternatives($second))));
    }
}
