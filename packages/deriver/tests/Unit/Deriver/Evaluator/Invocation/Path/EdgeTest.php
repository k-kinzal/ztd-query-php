<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Invocation\Path;

use Deriver\Deriver\BranchEvaluators;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Invocation\Path\Callers;
use Deriver\Deriver\Evaluator\Invocation\Path\Edge;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Scope\Context;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Source;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class EdgeTest extends TestCase
{
    public function testFollowBindsTheSelectedCallAndRetainsItsEvidence(): void
    {
        $source = 'function foo($x){return $x;}function bar(){foo(7);}function other(){foo(9);}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('fixture', [$tree]));
        $settings = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $settings, new Evaluators($settings->target), new Budget()));
        $graph = $index->graph('foo');
        self::assertNotNull($graph);
        [$caller, $call] = (new Callers($index))->sites('foo', 'bar')[0];
        $value = (new Edge($engine))->follow(new Frame($caller, 'bar'), $call, $graph, 32, fn (Frame $frame): Term => $engine->returns($frame, 32));
        self::assertSame(7, $value->native());
        self::assertNotNull($engine->context->provenance->get($value));
    }

    public function testDispatchRejectsAnotherResolvedCallee(): void
    {
        $source = 'function foo($x){return $x;}function bar(){foo(7);}function other(){foo(9);}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('fixture', [$tree]));
        $settings = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $settings, new Evaluators($settings->target), new Budget()));
        $graph = $index->graph('other');
        self::assertNotNull($graph);
        [$caller, $call] = (new Callers($index))->sites('foo', 'bar')[0];
        $opened = 0;
        $value = (new Edge($engine))->dispatch(new Frame($caller, 'bar'), $call, $graph, 32, static function () use (&$opened): Term {
            ++$opened;
            return Term::constant(99);
        });
        self::assertSame(TermKind::Choice, $value->kind);
        self::assertSame([], $value->operands);
        self::assertSame(0, $opened);
    }

    public function testObservePrunesBeforeOpeningTheValue(): void
    {
        $source = 'function foo($x){return $x;}function bar(){if(false){foo(7);}}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('fixture', [$tree]));
        $settings = new EvaluatorConfig(evaluators: array_map((new MicroEvaluatorConversion())->convert(...), BranchEvaluators::truth()));
        $engine = new Evaluator(new Context($index, $settings, new Evaluators($settings->target), new Budget()));
        [$caller, $call] = (new Callers($index))->sites('foo', 'bar')[0];
        $opened = 0;
        $value = (new Edge($engine))->observe(new Frame($caller, 'bar'), $caller->positions[$call->result][0], 32, static function () use (&$opened): Term {
            ++$opened;
            return Term::constant(99);
        });
        self::assertSame(TermKind::Choice, $value->kind);
        self::assertSame([], $value->operands);
        self::assertSame(0, $opened);
    }

}
