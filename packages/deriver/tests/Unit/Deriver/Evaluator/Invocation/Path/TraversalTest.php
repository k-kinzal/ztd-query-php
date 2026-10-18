<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Invocation\Path;

use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Invocation\Path\Traversal;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\CallPath;
use Deriver\Deriver\Evaluator\Scope\Context;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Source;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class TraversalTest extends TestCase
{
    public function testSelectBindsEverySelectedContext(): void
    {
        $source = 'function foo($x){return $x;}function bar(){foo(7);}function other(){foo(9);}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('fixture', [$tree]));
        $settings = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $settings, new Evaluators($settings->target), new Budget()));
        $graph = $index->graph('foo');
        self::assertNotNull($graph);
        $value = (new Traversal($engine, new CallPath(['bar'])))->select($graph, fn (Frame $frame): Term => $engine->returns($frame, 32));
        self::assertSame(7, iterator_to_array((new Choices())->alternatives($value))[0][0]->native());
    }

    public function testWalkLeavesTheOuterPrefixOpen(): void
    {
        $source = 'function foo($x){return $x;}function bar(){foo(7);}function other(){foo(9);}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('fixture', [$tree]));
        $settings = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $settings, new Evaluators($settings->target), new Budget()));
        $graph = $index->graph('bar');
        self::assertNotNull($graph);
        $value = (new Traversal($engine, new CallPath(['bar'])))->walk($graph, -1, static fn (Frame $frame): Term => Term::constant($frame->invocation), 32);
        self::assertFalse(iterator_to_array((new Choices())->alternatives($value))[0][0]->native());
    }

    public function testResidualKeepsUnfinishedAncestryOnTheObservedValue(): void
    {
        $source = 'function foo($x){return $x;}function bar(){foo(7);}function other(){foo(9);}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('fixture', [$tree]));
        $settings = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $settings, new Evaluators($settings->target), new Budget()));
        $graph = $index->graph('foo');
        self::assertNotNull($graph);
        $value = (new Traversal($engine, new CallPath(['bar'])))->residual($graph, static fn (): Term => Term::constant(7), 'DEPTH_LIMIT');
        self::assertSame(TermKind::ControlledValue, $value->kind);
        self::assertSame('DEPTH_LIMIT', $value->attributes['reason']);
        self::assertSame(7, $value->operands[0]->native());
    }

    public function testJoinPreservesIndependentAlternatives(): void
    {
        $source = 'function foo($x){return $x;}function bar(){foo(7);}function other(){foo(9);}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('fixture.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('fixture', [$tree]));
        $settings = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $settings, new Evaluators($settings->target), new Budget()));
        $value = (new Traversal($engine, new CallPath(['bar'])))->join([static fn (): Term => Term::constant(7), static fn (): Term => Term::constant(9)]);
        self::assertSame([7,9], array_map(static fn (array $row) => $row[0]->native(), iterator_to_array((new Choices())->alternatives($value))));
    }

}
