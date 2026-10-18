<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Invocation\Path;

use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Invocation\Path\Callers;
use Deriver\Deriver\Evaluator\Invocation\Path\Reachability;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\CallPath;
use Deriver\Deriver\Evaluator\Scope\Context;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Source;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ReachabilityTest extends TestCase
{
    public function testAllowsRejectsOnlyUnreachableStructuralCycles(): void
    {
        $source = 'function foo($x){return $x;}function recur($x){recur($x+1);foo($x);}function bar(){recur(1);}function entry(){return 7;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('fixture', [$tree]));
        $settings = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $settings, new Evaluators($settings->target), new Budget()));
        $graph = $index->graph('foo');
        self::assertNotNull($graph);
        $callers = new Callers($index);
        self::assertFalse((new Reachability($callers, new CallPath(['entry','...']), $engine->context))->allows($graph, 1));
        self::assertTrue((new Reachability($callers, new CallPath(['bar','...']), $engine->context))->allows($graph, 1));
    }

}
