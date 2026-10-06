<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonPair;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\KeyValueSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(JsonPair::class)]
#[Small]
final class JsonPairTest extends TestCase
{
    public function testRejectsAKeyBeforeValueThatIsNotPrimary(): void
    {
        $this->expectExceptionMessage('A key before VALUE is a primary expression; group it.');
        new JsonPair(new BinaryOperation(new OperatorName(new Name('=')), new Constant(new IntegerConstant('1')), new Constant(new IntegerConstant('1'))), new JsonValueExpression(new Constant(new IntegerConstant('1'))), KeyValueSpelling::Value);
    }

    public function testDeriveClauseDerivesTheKeyAndTheValue(): void
    {
        $key = new Constant(new StringConstant('a'));
        $value = new Constant(new IntegerConstant('1'));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new JsonPair($key, new JsonValueExpression($value)))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($key));
        self::assertTrue($derivation->facts()->covers($value));
    }

    public function testRenderWritesBothSpellings(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new JsonPair(new Constant(new StringConstant('a')), new JsonValueExpression(new Constant(new IntegerConstant('1'))), KeyValueSpelling::Value))->render($out);
        $out->symbol(',');
        (new JsonPair(new Constant(new StringConstant('b')), new JsonValueExpression(new Constant(new IntegerConstant('1')))))->render($out);
        self::assertSame('\'a\' VALUE 1, \'b\' : 1', (new Lexical())->join($out->pieces()));
    }
}
