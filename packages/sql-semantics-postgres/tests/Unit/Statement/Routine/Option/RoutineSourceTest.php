<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineSource;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RoutineSource::class)]
#[Medium]
final class RoutineSourceTest extends TestCase
{
    public function testRenderWritesTheTextAsAString(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new RoutineSource(new Name('sql'), new StringConstant('SELECT 1')))->render($out);
        self::assertSame("'SELECT 1'", (new Lexical())->join($out->pieces()));
    }

    public function testAnalyzeKeepsAnSqlDefinitionUnanalysedWithItsLanguage(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('CREATE FUNCTION f(a int4) RETURNS int4 AS $$ SELECT a FROM missing $$ LANGUAGE sql');
        $function = $operation->statement;
        self::assertInstanceOf(CreateFunction::class, $function);
        $definition = $function->options[0];
        self::assertInstanceOf(RoutineDefinition::class, $definition);
        self::assertSame(['sql', ' SELECT a FROM missing ', []], [$definition->source->language?->value, $definition->source->text->value, $operation->facts->diagnostics]);
    }

    public function testAnalyzeNamesNoLanguageWithoutALanguageOption(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze("CREATE FUNCTION f() RETURNS int4 AS 'x'");
        $function = $operation->statement;
        self::assertInstanceOf(CreateFunction::class, $function);
        $definition = $function->options[0];
        self::assertInstanceOf(RoutineDefinition::class, $definition);
        self::assertNull($definition->source->language);
    }
}
