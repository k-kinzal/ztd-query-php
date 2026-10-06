<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(Grouped::class)]
#[Small]
final class GroupedTest extends TestCase
{
    public function testOutputNameIsTheOperandName(): void
    {
        self::assertSame('a', (new Grouped(new ColumnReference([new Name('a')])))->outputName()?->value);
    }

    public function testDeriveScalarHasTheOperandFacts(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new Grouped(new NullLiteral()), $derivation->environment());
        self::assertInstanceOf(NullOnly::class, $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testRenderWritesParentheses(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Grouped(new Grouped(new NullLiteral())))->render($out);
        self::assertSame('((NULL))', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAScalarSubquery(): void
    {
        $this->expectExceptionMessage('Parentheses around a scalar subquery belong to its query.');
        new Grouped(new ScalarSubquery(self::createStub(Query::class)));
    }
}
