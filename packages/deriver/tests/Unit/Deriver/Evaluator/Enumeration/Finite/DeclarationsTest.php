<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Enumeration\Finite;

use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
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
final class DeclarationsTest extends TestCase
{
    public function testDomainRecognizesNullableEnumsWithoutOpeningAnyBody(): void
    {
        $source = 'enum State{case A;case B;}function subject():?State{return State::A;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $domain = (new \Deriver\Deriver\Evaluator\Enumeration\Finite\Declarations())->domain($engine, 'null|State');
        self::assertNotNull($domain);
        self::assertSame(['scalar:N;' => true, 'enum:State::A' => true, 'enum:State::B' => true], $domain->remaining);
        self::assertSame(0, $engine->context->constructedNodes);
        self::assertNull((new \Deriver\Deriver\Evaluator\Enumeration\Finite\Declarations())->domain($engine, 'bool|int'));
    }

    public function testMembersLeavesOpenTypesUnbounded(): void
    {
        $source = 'function subject(){return 1;}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('a.php', '<?php ' . $source)));
        $index = new DefinitionIndex(new Index('s', [$tree]));
        $config = new EvaluatorConfig();
        $engine = new Evaluator(new Context($index, $config, new Evaluators($config->target), new Budget()));
        $domains = new \Deriver\Deriver\Evaluator\Enumeration\Finite\Declarations();
        self::assertSame(['b:0' => true, 'b:1' => true], $domains->members($engine, 'bool'));
        self::assertNull($domains->members($engine, 'int'));
        self::assertNull($domains->members($engine, 'MissingEnum'));
    }
}
