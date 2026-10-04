<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Subquery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\InList;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(InList::class)]
#[Small]
final class InListTest extends TestCase
{
    public function testDeriveScalarIsNullableWithANullItem(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new InList(new Constant(new IntegerConstant('1')), false, [new Constant(new IntegerConstant('2')), new NullLiteral()]), $derivation->environment());
        self::assertEquals(new Known(Builtin::Bool), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testRenderWritesTheList(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new InList(new NullLiteral(), false, [new NullLiteral(), new NullLiteral()]))->render($out);
        self::assertSame('NULL IN (NULL, NULL)', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsASingleScalarSubquery(): void
    {
        $this->expectExceptionMessage('IN with one scalar subquery is read as IN (SELECT ...).');
        new InList(new NullLiteral(), false, [new ScalarSubquery(self::createStub(Query::class))]);
    }
}
