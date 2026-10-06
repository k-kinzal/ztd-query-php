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
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Exists::class)]
#[Small]
final class ExistsTest extends TestCase
{
    public function testOutputNameIsExists(): void
    {
        self::assertSame('exists', (new Exists(self::createStub(Query::class)))->outputName()->value);
    }

    public function testDeriveScalarIsANonNullBoolean(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        $query = self::createConfiguredStub(Query::class, ['deriveQuery' => new QueryFact([new Field(0, new OutputSlot(new Name('a'), new Known(Builtin::Int4), Nullability::NotNull))], $context->columnNames)]);
        $derivation = new Derivation($context);
        $fact = $derivation->scalar(new Exists($query), $derivation->environment());
        self::assertEquals(new Known(Builtin::Bool), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testRenderWritesExists(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Exists(self::createStub(Query::class)))->render($out);
        self::assertSame('EXISTS ()', (new Lexical())->join($out->pieces()));
    }
}
