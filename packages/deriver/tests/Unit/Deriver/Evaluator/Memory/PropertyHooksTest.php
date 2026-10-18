<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Memory;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PropertyHooksTest extends TestCase
{
    public function testResolveUsesAnInheritedGetter(): void
    {
        $source = new \Deriver\Deriver\Sources\Source('subject.php', '<?php class Base { public int $value { get => 17; } } class Child extends Base {} function subject() { return (new Child())->value; }');
        $tree = (new \Deriver\Deriver\Resolver\Resolver())->resolve((new \Deriver\Deriver\Parser\Parser(new \Deriver\Deriver\Parser\ParserConfig('8.4')))->parse($source));
        $index = new \Deriver\Deriver\Evaluator\Index\DefinitionIndex(new \Deriver\Deriver\Lowerer\Declaration\Index('test', [$tree]));
        $profile = new \Deriver\Deriver\Evaluator\TargetProfile('8.4');
        $engine = new \Deriver\Deriver\Evaluator\Evaluator(new \Deriver\Deriver\Evaluator\Scope\Context($index, new \Deriver\Deriver\Evaluator\EvaluatorConfig($profile), new \Deriver\Deriver\Evaluator\Call\Evaluators($profile), new \Deriver\Deriver\Evaluator\Request\Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $value = $engine->returns(new \Deriver\Deriver\Evaluator\Scope\Frame($graph, 'subject'), 64);
        $alternatives = iterator_to_array((new \Deriver\Deriver\Evaluator\Enumeration\Choices())->alternatives($value), false);
        self::assertCount(1, $alternatives);
        self::assertSame(17, $alternatives[0][0]->native());
    }

    public function testReadUsesTheBackingSlotInsideItsOwnGetter(): void
    {
        $source = new \Deriver\Deriver\Sources\Source('subject.php', '<?php class Box { public int $value = 8 { get => $this->value * 2; } } function subject() { return (new Box())->value; }');
        $tree = (new \Deriver\Deriver\Resolver\Resolver())->resolve((new \Deriver\Deriver\Parser\Parser(new \Deriver\Deriver\Parser\ParserConfig('8.4')))->parse($source));
        $index = new \Deriver\Deriver\Evaluator\Index\DefinitionIndex(new \Deriver\Deriver\Lowerer\Declaration\Index('test', [$tree]));
        $profile = new \Deriver\Deriver\Evaluator\TargetProfile('8.4');
        $engine = new \Deriver\Deriver\Evaluator\Evaluator(new \Deriver\Deriver\Evaluator\Scope\Context($index, new \Deriver\Deriver\Evaluator\EvaluatorConfig($profile), new \Deriver\Deriver\Evaluator\Call\Evaluators($profile), new \Deriver\Deriver\Evaluator\Request\Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $value = $engine->returns(new \Deriver\Deriver\Evaluator\Scope\Frame($graph, 'subject'), 64);
        $alternatives = iterator_to_array((new \Deriver\Deriver\Evaluator\Enumeration\Choices())->alternatives($value), false);
        self::assertCount(1, $alternatives);
        self::assertSame(16, $alternatives[0][0]->native());
    }

    public function testWritePassesTheAssignedValueToTheSetter(): void
    {
        $source = new \Deriver\Deriver\Sources\Source('subject.php', '<?php class Box { public int $value = 1 { set { $this->value = $value * 2; } } } function subject() { $box = new Box(); $box->value = 8; return $box->value; }');
        $tree = (new \Deriver\Deriver\Resolver\Resolver())->resolve((new \Deriver\Deriver\Parser\Parser(new \Deriver\Deriver\Parser\ParserConfig('8.4')))->parse($source));
        $index = new \Deriver\Deriver\Evaluator\Index\DefinitionIndex(new \Deriver\Deriver\Lowerer\Declaration\Index('test', [$tree]));
        $profile = new \Deriver\Deriver\Evaluator\TargetProfile('8.4');
        $engine = new \Deriver\Deriver\Evaluator\Evaluator(new \Deriver\Deriver\Evaluator\Scope\Context($index, new \Deriver\Deriver\Evaluator\EvaluatorConfig($profile), new \Deriver\Deriver\Evaluator\Call\Evaluators($profile), new \Deriver\Deriver\Evaluator\Request\Budget()));
        $graph = $index->graph('subject');
        self::assertNotNull($graph);
        $value = $engine->returns(new \Deriver\Deriver\Evaluator\Scope\Frame($graph, 'subject'), 64);
        $alternatives = iterator_to_array((new \Deriver\Deriver\Evaluator\Enumeration\Choices())->alternatives($value), false);
        self::assertCount(1, $alternatives);
        self::assertSame(16, $alternatives[0][0]->native());
    }
}
