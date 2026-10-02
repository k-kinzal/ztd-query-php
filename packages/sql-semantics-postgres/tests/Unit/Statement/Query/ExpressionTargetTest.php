<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(ExpressionTarget::class)]
#[Small]
final class ExpressionTargetTest extends TestCase
{
    public function testProjectNamesAnUnnamedExpressionQuestionColumn(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fields = (new ExpressionTarget(new Constant(new IntegerConstant('1'))))->project($derivation, $derivation->environment(), 4);
        self::assertCount(1, $fields);
        self::assertInstanceOf(Field::class, $fields[0]);
        self::assertSame([4, '?column?'], [$fields[0]->position, $fields[0]->name?->value]);
    }

    public function testProjectPrefersTheAliasAndResolvesAStringConstantToText(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fields = (new ExpressionTarget(new Constant(new StringConstant('a')), new Name('label')))->project($derivation, $derivation->environment(), 0);
        self::assertInstanceOf(Field::class, $fields[0]);
        self::assertSame('label', $fields[0]->name?->value);
        self::assertEquals(new Known(Builtin::Text), $fields[0]->type);
    }

    public function testProjectNamesAFieldAfterWhatTheExpressionNames(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fields = (new ExpressionTarget(new BooleanLiteral(true)))->project($derivation, $derivation->environment(), 0);
        self::assertInstanceOf(Field::class, $fields[0]);
        self::assertSame('bool', $fields[0]->name?->value);
    }

    public function testRenderWritesTheAliasAfterAs(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ExpressionTarget(new Constant(new IntegerConstant('1')), new Name('select')))->render($out);
        self::assertSame('1 AS select', (new Lexical())->join($out->pieces()));
    }
}
