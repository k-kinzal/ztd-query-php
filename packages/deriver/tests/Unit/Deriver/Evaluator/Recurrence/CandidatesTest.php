<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Recurrence;

use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Recurrence\Candidates;
use Deriver\Deriver\Evaluator\Request\Budget;
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
final class CandidatesTest extends TestCase
{
    public function testValueKeepsAnInitialValueAndAnIndependentWrite(): void
    {
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('loop.php', '<?php function subject(){$x=1;while(missing()){$x=7;}return $x;}')));
        $engine = new Evaluator(new Context(new DefinitionIndex(new Index('loop', [$tree])), new EvaluatorConfig(), new Evaluators(), new Budget()));
        $graph = $engine->context->index->graph('subject');
        self::assertNotNull($graph);
        $frame = new Frame($graph, 'source:subject');
        $value = $engine->returns($frame, 64);
        $values = array_map(static fn (array $alternative) => $alternative[0]->native(), iterator_to_array((new Choices())->alternatives($value), false));
        self::assertSame([1, 7], $values);
        self::assertSame(['subject:$x' => 1], $engine->context->references);
    }

    public function testUpdateRetainsThePreviousValueInADependentUpdate(): void
    {
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('loop.php', '<?php function subject(){$x=1;while(missing()){$x+=2;}return $x;}')));
        $engine = new Evaluator(new Context(new DefinitionIndex(new Index('loop', [$tree])), new EvaluatorConfig(), new Evaluators(), new Budget()));
        $graph = $engine->context->index->graph('subject');
        self::assertNotNull($graph);
        $value = $engine->returns(new Frame($graph, 'source:subject'), 64);
        $alternatives = iterator_to_array((new Choices())->alternatives($value), false);
        self::assertCount(1, $alternatives);
        $recurrence = $alternatives[0][0];
        self::assertSame(TermKind::Recurrence, $recurrence->kind);
        self::assertSame(1, $recurrence->operands[0]->native());
        self::assertSame(TermKind::Binary, $recurrence->operands[1]->kind);
        self::assertSame($recurrence->operands[2]->attributes['identity'], $recurrence->operands[1]->operands[0]->attributes['identity']);
        self::assertSame(2, $recurrence->operands[1]->operands[1]->native());
        self::assertSame(['subject:$x' => 1], $engine->context->references);
    }

    public function testDependsOnRecognizesCopiedReferencesByIdentity(): void
    {
        $engine = new Evaluator(new Context(new DefinitionIndex(new Index('loop', [])), new EvaluatorConfig(), new Evaluators(), new Budget()));
        $reference = new Term(TermKind::Recursive, attributes: ['identity' => 'loop:x']);
        $copy = new Term(TermKind::Recursive, attributes: ['identity' => 'loop:x']);
        $update = new Term(TermKind::Binary, '+', [$copy, Term::constant(2)]);
        self::assertTrue((new Candidates($engine))->dependsOn($update, $reference));
        self::assertFalse((new Candidates($engine))->dependsOn($update, new Term(TermKind::Recursive, attributes: ['identity' => 'loop:y'])));
    }

    public function testResolveKeepsACycleBetweenDifferentStorageLocationsPartial(): void
    {
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('loop.php', '<?php function subject(){$x=1;$y=2;while(missing()){$x=$y;$y=$x+1;}return $x;}')));
        $engine = new Evaluator(new Context(new DefinitionIndex(new Index('loop', [$tree])), new EvaluatorConfig(), new Evaluators(), new Budget()));
        $graph = $engine->context->index->graph('subject');
        self::assertNotNull($graph);
        $value = $engine->returns(new Frame($graph, 'source:subject'), 64);
        $alternatives = iterator_to_array((new Choices())->alternatives($value), false);
        self::assertCount(3, $alternatives);
        self::assertSame([1, 2], [$alternatives[0][0]->native(), $alternatives[1][0]->native()]);
        self::assertSame(TermKind::Binary, $alternatives[2][0]->kind);
        self::assertSame(TermKind::Recursive, $alternatives[2][0]->operands[0]->kind);
        self::assertSame('CYCLE', $alternatives[2][0]->operands[0]->attributes['reason']);
        self::assertSame(1, $alternatives[2][0]->operands[1]->native());
        self::assertNull($engine->context->stopReason);
    }
}
