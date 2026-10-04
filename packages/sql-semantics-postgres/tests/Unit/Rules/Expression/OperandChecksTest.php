<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\OperandChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\Composite;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(OperandChecks::class)]
#[Small]
final class OperandChecksTest extends TestCase
{
    public function testBooleanAcceptsUnknownAndReportsIntegers(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        self::assertNull((new OperandChecks())->boolean($derivation, new Known(Builtin::Unknown), 'AND'));
        self::assertInstanceOf(Invalid::class, (new OperandChecks())->boolean($derivation, new Known(Builtin::Int4), 'AND'));
    }

    public function testAcceptedIsBooleanForAnAcceptedType(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        self::assertEquals(new Known(Builtin::Bool), (new OperandChecks())->accepted($derivation, new Known(Builtin::Xml), [Builtin::Xml], 'IS DOCUMENT'));
    }

    public function testCollatableAcceptsArraysOfText(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $type = new Known(new ArrayOf(Builtin::Text));
        self::assertSame($type, (new OperandChecks())->collatable($derivation, $type));
        self::assertInstanceOf(Invalid::class, (new OperandChecks())->collatable($derivation, new Known(Builtin::Bool)));
    }

    public function testNullabilityCountsNullAsNullable(): void
    {
        self::assertSame(Nullability::Nullable, (new OperandChecks())->nullability([new ScalarFact(new Known(Builtin::Int4), Nullability::NotNull), new ScalarFact(new NullOnly(), Nullability::Nullable)]));
    }

    public function testFieldsReadsTheFieldsOfRows(): void
    {
        $row = new ScalarFact(new Known(new Composite([new OutputSlot(new Name('f1'), new NullOnly(), Nullability::Nullable)])), Nullability::NotNull);
        self::assertSame(Nullability::Nullable, (new OperandChecks())->fields([$row]));
    }
}
