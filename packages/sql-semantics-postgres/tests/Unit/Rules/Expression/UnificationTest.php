<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Unification;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\TypeConflict;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Reference\Missing\UnboundParameter;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(Unification::class)]
#[Small]
final class UnificationTest extends TestCase
{
    public function testResolvePromotesWithinACategory(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        self::assertEquals(new Known(Builtin::Float8), (new Unification())->resolve($context, [new Known(Builtin::Int4), new NullOnly(), new Known(Builtin::Float8), new Known(Builtin::Numeric)]));
    }

    public function testResolveGivesTextForUnknownInputs(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        self::assertEquals(new Known(Builtin::Text), (new Unification())->resolve($context, [new Known(Builtin::Unknown), new NullOnly()]));
    }

    public function testResolveDependsOnMissingInputs(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        $missing = new Dependent([new UnboundParameter('$1')]);
        self::assertEquals($missing, (new Unification())->resolve($context, [new Known(Builtin::Int4), $missing]));
    }

    public function testCandidateReportsDifferentCategories(): void
    {
        $result = (new Unification())->candidate([new Known(Builtin::Int4), new Known(Builtin::Bool)], 'CASE');
        self::assertInstanceOf(Invalid::class, $result);
        self::assertEquals(new TypeConflict('CASE', 'integer', 'boolean'), $result->cause);
    }

    public function testNullabilityIsNullableWhenAnyInputIs(): void
    {
        self::assertSame(Nullability::Nullable, (new Unification())->nullability([Nullability::NotNull, Nullability::Nullable, Nullability::Dependent]));
    }

    public function testDistinctRemovesRepeatedInputs(): void
    {
        self::assertCount(1, (new Unification())->distinct([new UnboundParameter('$1'), new UnboundParameter('$1')]));
    }
}
