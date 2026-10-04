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
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\RowArity;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ScalarSubquery::class)]
#[Small]
final class ScalarSubqueryTest extends TestCase
{
    public function testOutputNameIsNoneForAQueryWithoutNaming(): void
    {
        self::assertNull((new ScalarSubquery(self::createStub(Query::class)))->outputName());
    }

    public function testDeriveScalarHasTheColumnTypeAndCanBeNull(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        $query = self::createConfiguredStub(Query::class, ['deriveQuery' => new QueryFact([new Field(0, new OutputSlot(new Name('a'), new Known(Builtin::Int4), Nullability::NotNull))], $context->columnNames)]);
        $derivation = new Derivation($context);
        $fact = $derivation->scalar(new ScalarSubquery($query), $derivation->environment());
        self::assertEquals(new Known(Builtin::Int4), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testDeriveScalarReportsAQueryWithoutColumns(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        $derivation = new Derivation($context);
        $fact = $derivation->scalar(new ScalarSubquery(self::createConfiguredStub(Query::class, ['deriveQuery' => new QueryFact([], $context->columnNames)])), $derivation->environment());
        self::assertInstanceOf(RowArity::class, $fact->type instanceof Invalid ? $fact->type->cause : null);
    }

    public function testRenderWritesParentheses(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ScalarSubquery(self::createStub(Query::class)))->render($out);
        self::assertSame('()', (new Lexical())->join($out->pieces()));
    }
}
