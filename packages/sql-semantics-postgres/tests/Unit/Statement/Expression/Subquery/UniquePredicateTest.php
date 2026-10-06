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
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\NotImplemented;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\UniquePredicate;
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

#[CoversClass(UniquePredicate::class)]
#[Small]
final class UniquePredicateTest extends TestCase
{
    public function testDeriveScalarReportsThePredicate(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        $query = self::createConfiguredStub(Query::class, ['deriveQuery' => new QueryFact([new Field(0, new OutputSlot(new Name('a'), new Known(Builtin::Int4), Nullability::NotNull))], $context->columnNames)]);
        $derivation = new Derivation($context);
        $fact = $derivation->scalar(new UniquePredicate(null, $query), $derivation->environment());
        self::assertInstanceOf(NotImplemented::class, $fact->type instanceof Invalid ? $fact->type->cause : null);
    }

    public function testRenderWritesTheNullsTreatment(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new UniquePredicate(false, self::createStub(Query::class)))->render($out);
        self::assertSame('UNIQUE NULLS NOT DISTINCT ()', (new Lexical())->join($out->pieces()));
    }
}
