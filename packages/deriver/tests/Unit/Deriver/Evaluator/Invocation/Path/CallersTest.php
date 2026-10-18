<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Invocation\Path;

use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Invocation\Path\Callers;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Scope\Context;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Source;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CallersTest extends TestCase
{
    public function testSitesFiltersBeforeLoweringUnrelatedCallers(): void
    {
        $source = 'function foo($x){return $x;}function bar(){foo(7);}function other(){foo(9);}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('fixture', [$tree]));
        $settings = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $settings, new Evaluators($settings->target), new Budget()));
        $callers = new Callers($index);
        $sites = $callers->sites('foo', 'bar');
        self::assertCount(1, $sites);
        self::assertSame('bar', $sites[0][0]->body->symbol);
        self::assertSame(1, $index->program->graphCount());
        self::assertSame($sites, $callers->sites('foo', 'bar'));
    }

    public function testWithinKeepsDynamicTargetsForDispatch(): void
    {
        $source = 'function foo($x){return $x;}function bar(){$f="foo";$f(7);other(9);}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('fixture', [$tree]));
        $settings = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $settings, new Evaluators($settings->target), new Budget()));
        $graph = $index->graph('bar');
        self::assertNotNull($graph);
        self::assertCount(1, (new Callers($index))->within($graph, 'foo'));
    }

}
