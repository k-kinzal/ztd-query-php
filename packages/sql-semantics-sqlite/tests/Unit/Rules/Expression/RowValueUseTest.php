<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Expression\RowValueUse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Platform\Sqlite\Statement\Type\Vector;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Reference\Missing\UnboundParameter;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(RowValueUse::class)]
#[Small]
final class RowValueUseTest extends TestCase
{
    public function testWidthIsOneForASingleValueAndTheWidthOfARowValue(): void
    {
        $rows = new RowValueUse();

        self::assertSame(1, $rows->width(new ScalarFact(new Known(Storage::Integer), Nullability::NotNull)));
        self::assertSame(1, $rows->width(new ScalarFact(new NullOnly(), Nullability::Nullable)));
        self::assertSame(1, $rows->width(new ScalarFact(new Choice([Storage::Integer, Storage::Real]), Nullability::NotNull)));
        self::assertSame(3, $rows->width(new ScalarFact(new Known(new Vector(3)), Nullability::NotNull)));
    }

    public function testWidthIsUnknownForADependentOrInvalidType(): void
    {
        $rows = new RowValueUse();

        self::assertNull($rows->width(new ScalarFact(new Dependent([new UnboundParameter('?')]), Nullability::Dependent)));
        self::assertNull($rows->width(new ScalarFact(new Invalid(new Misuse(MisuseRule::HexLiteralTooBig)), Nullability::NotNull)));
    }

    public function testSingleReportsARowValueAndAnswersTheProblem(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context());
        $problem = (new RowValueUse())->single(new ScalarFact(new Known(new Vector(2)), Nullability::NotNull), $derivation);

        self::assertNotNull($problem);
        self::assertSame(MisuseRule::TooManyValueColumns, $problem->rule);
        self::assertSame('row value misused', $problem->message());
        self::assertSame([$problem], $derivation->facts()->diagnostics);
    }

    public function testSingleAdmitsASingleValueAndAnUnknownWidth(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context());
        $rows = new RowValueUse();

        self::assertNull($rows->single(new ScalarFact(new Known(Storage::Text), Nullability::NotNull), $derivation));
        self::assertNull($rows->single(new ScalarFact(new Dependent([new UnboundParameter('?')]), Nullability::Dependent), $derivation));
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testUniformReportsRowsOfDifferentWidths(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context());
        (new RowValueUse())->uniform([new ScalarFact(new Known(new Vector(2)), Nullability::NotNull), new ScalarFact(new Known(new Vector(3)), Nullability::NotNull)], $derivation);
        $diagnostics = $derivation->facts()->diagnostics;

        self::assertCount(1, $diagnostics);
        self::assertInstanceOf(ArityMismatch::class, $diagnostics[0]);
        self::assertSame(ArityRule::RowComparison, $diagnostics[0]->rule);
        self::assertSame(2, $diagnostics[0]->expected);
        self::assertSame(3, $diagnostics[0]->actual);
        self::assertSame('Row value misused: 2 columns against 3.', $diagnostics[0]->message());
    }

    public function testUniformReportsARowValueBesideASingleValue(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context());
        (new RowValueUse())->uniform([new ScalarFact(new Known(Storage::Integer), Nullability::NotNull), new ScalarFact(new Known(new Vector(2)), Nullability::NotNull)], $derivation);
        $diagnostics = $derivation->facts()->diagnostics;

        self::assertCount(1, $diagnostics);
        self::assertInstanceOf(Misuse::class, $diagnostics[0]);
        self::assertSame(MisuseRule::TooManyValueColumns, $diagnostics[0]->rule);
    }

    public function testUniformReportsNothingForEqualOrUnknownWidths(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context());
        $rows = new RowValueUse();
        $rows->uniform([new ScalarFact(new Known(new Vector(2)), Nullability::NotNull), new ScalarFact(new Known(new Vector(2)), Nullability::Nullable), new ScalarFact(new Dependent([new UnboundParameter('?')]), Nullability::Dependent)], $derivation);
        $rows->uniform([new ScalarFact(new Known(Storage::Integer), Nullability::NotNull), new ScalarFact(new NullOnly(), Nullability::Nullable)], $derivation);
        $rows->uniform([], $derivation);

        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testMembershipReportsAnOperandOfAnotherWidthThanTheColumns(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context());
        (new RowValueUse())->membership(new ScalarFact(new Known(new Vector(2)), Nullability::NotNull), 3, $derivation);
        $diagnostics = $derivation->facts()->diagnostics;

        self::assertCount(1, $diagnostics);
        self::assertInstanceOf(ArityMismatch::class, $diagnostics[0]);
        self::assertSame(ArityRule::ScalarSubquery, $diagnostics[0]->rule);
        self::assertSame(2, $diagnostics[0]->expected);
        self::assertSame(3, $diagnostics[0]->actual);
        self::assertSame('Sub-select returns 3 columns - expected 2.', $diagnostics[0]->message());
    }

    public function testMembershipReportsNothingForAMatchingOrUnknownWidth(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context());
        $rows = new RowValueUse();
        $rows->membership(new ScalarFact(new Known(Storage::Integer), Nullability::NotNull), 1, $derivation);
        $rows->membership(new ScalarFact(new Known(new Vector(2)), Nullability::NotNull), 2, $derivation);
        $rows->membership(new ScalarFact(new Dependent([new UnboundParameter('?')]), Nullability::Dependent), 5, $derivation);

        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testElementsReportsTheFirstElementOfAnotherWidthOnly(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context());
        $pair = new ScalarFact(new Known(new Vector(2)), Nullability::NotNull);
        (new RowValueUse())->elements($pair, [$pair, new ScalarFact(new Known(Storage::Integer), Nullability::NotNull), new ScalarFact(new Known(new Vector(3)), Nullability::NotNull)], $derivation);
        $diagnostics = $derivation->facts()->diagnostics;

        self::assertCount(1, $diagnostics);
        self::assertInstanceOf(ArityMismatch::class, $diagnostics[0]);
        self::assertSame(ArityRule::InListElement, $diagnostics[0]->rule);
        self::assertSame(2, $diagnostics[0]->expected);
        self::assertSame(1, $diagnostics[0]->actual);
        self::assertSame('IN(...) element has 1 term - expected 2.', $diagnostics[0]->message());
    }

    public function testElementsSpellsTheTermsOfARowElementInThePlural(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context());
        (new RowValueUse())->elements(new ScalarFact(new Known(Storage::Integer), Nullability::NotNull), [new ScalarFact(new Known(new Vector(2)), Nullability::NotNull)], $derivation);
        $diagnostics = $derivation->facts()->diagnostics;

        self::assertCount(1, $diagnostics);
        self::assertSame('IN(...) element has 2 terms - expected 1.', $diagnostics[0]->message());
    }

    public function testElementsReportsNothingForMatchingOrUnknownWidths(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context());
        $rows = new RowValueUse();
        $single = new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
        $unknown = new ScalarFact(new Dependent([new UnboundParameter('?')]), Nullability::Dependent);
        $rows->elements($single, [$single, $unknown], $derivation);
        $rows->elements($unknown, [new ScalarFact(new Known(new Vector(2)), Nullability::NotNull)], $derivation);
        $rows->elements($single, [], $derivation);

        self::assertSame([], $derivation->facts()->diagnostics);
    }
}
