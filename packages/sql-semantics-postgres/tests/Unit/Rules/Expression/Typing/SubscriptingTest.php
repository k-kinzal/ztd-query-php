<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\Subscripting;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\Composite;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\FieldSelection;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\ImproperStar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\MissingField;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\NotSubscriptable;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Step\AllFields;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Step\Slice;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Step\Subscript;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Subscripting::class)]
#[Small]
final class SubscriptingTest extends TestCase
{
    public function testApplyReadsTheElementOfAnArray(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = (new Subscripting())->apply($derivation, new ScalarFact(new Known(new ArrayOf(Builtin::Text)), Nullability::NotNull), [new Subscript(new NullLiteral()), new Subscript(new NullLiteral())], false);
        self::assertEquals(new ScalarFact(new Known(Builtin::Text), Nullability::Nullable), $fact);
    }

    public function testApplyReportsAStarBeforeAnotherStep(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = (new Subscripting())->apply($derivation, new ScalarFact(new Known(Builtin::Int4), Nullability::NotNull), [new AllFields(), new Subscript(new NullLiteral())], false);
        self::assertEquals(new Invalid(new ImproperStar()), $fact->type);
    }

    public function testSubscriptKeepsTheArrayForASlice(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $type = new Known(new ArrayOf(Builtin::Int4));
        self::assertSame($type, (new Subscripting())->subscript($derivation, $type, [new Subscript(new NullLiteral()), new Slice(null, null)]));
        self::assertEquals(new Invalid(new NotSubscriptable('integer')), (new Subscripting())->subscript($derivation, new Known(Builtin::Int4), [new Subscript(new NullLiteral())]));
    }

    public function testFieldReadsTheSlotOfAComposite(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $composite = new Known(new Composite([new OutputSlot(new Name('a'), new Known(Builtin::Int4), Nullability::NotNull)]));
        self::assertEquals([new Known(Builtin::Int4), Nullability::NotNull], (new Subscripting())->field($derivation, $composite, new FieldSelection(new Name('a'))));
        self::assertEquals(new Invalid(new MissingField('b', 'record')), (new Subscripting())->field($derivation, $composite, new FieldSelection(new Name('b'))));
    }

    public function testProblemReportsTheDiagnostic(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new Subscripting())->problem($derivation, new ImproperStar());
        self::assertEquals([new ImproperStar()], $derivation->facts()->diagnostics);
    }
}
