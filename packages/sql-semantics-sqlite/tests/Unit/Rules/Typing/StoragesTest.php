<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rules\Typing\Storages;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Platform\Sqlite\Statement\Type\Vector;
use SqlSemantics\Statement\Reference\Missing\UnboundParameter;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(Storages::class)]
#[Small]
final class StoragesTest extends TestCase
{
    public function testOfAnswersTheClassesOfEachKindOfFact(): void
    {
        $storages = new Storages();

        self::assertSame([], $storages->of(new NullOnly()));
        self::assertSame([Storage::Integer], $storages->of(new Known(Storage::Integer)));
        self::assertSame([Storage::Integer, Storage::Text], $storages->of(new Choice([Storage::Text, Storage::Integer])));
        self::assertSame([Storage::Text, Storage::Blob], $storages->of(new Known(new ColumnDomain('VARCHAR'))));
        self::assertSame(Storage::cases(), $storages->of(new Known(new Vector(2))));
    }

    public function testOfIsNullForADependentOrInvalidFact(): void
    {
        $storages = new Storages();

        self::assertNull($storages->of(new Dependent([new UnboundParameter('?')])));
        self::assertNull($storages->of(new Invalid(new Misuse(MisuseRule::HexLiteralTooBig))));
    }

    public function testHeldOfAColumnFollowsItsAffinity(): void
    {
        $storages = new Storages();

        self::assertSame([Storage::Text, Storage::Blob], $storages->held(new ColumnDomain('TEXT')));
        self::assertSame([Storage::Real, Storage::Text, Storage::Blob], $storages->held(new ColumnDomain('DOUBLE')));
        self::assertSame(Storage::cases(), $storages->held(new ColumnDomain('INTEGER')));
        self::assertSame(Storage::cases(), $storages->held(new ColumnDomain('DECIMAL')));
        self::assertSame(Storage::cases(), $storages->held(new ColumnDomain('')));
    }

    public function testHeldOfAStrictColumnIsTheClassOfItsType(): void
    {
        $storages = new Storages();

        self::assertSame([Storage::Integer], $storages->held(new ColumnDomain('INT', true)));
        self::assertSame([Storage::Integer], $storages->held(new ColumnDomain('integer', true)));
        self::assertSame([Storage::Real], $storages->held(new ColumnDomain('REAL', true)));
        self::assertSame([Storage::Text], $storages->held(new ColumnDomain('TEXT', true)));
        self::assertSame([Storage::Blob], $storages->held(new ColumnDomain('BLOB', true)));
        self::assertSame(Storage::cases(), $storages->held(new ColumnDomain('ANY', true)));
    }

    public function testHeldOfAStorageOrAnotherDescriptorIsThatClassOrEveryClass(): void
    {
        $storages = new Storages();

        self::assertSame([Storage::Blob], $storages->held(Storage::Blob));
        self::assertSame(Storage::cases(), $storages->held(new Vector(3)));
    }

    public function testMergeUnitesInTheFixedOrderOfTheClasses(): void
    {
        $storages = new Storages();

        self::assertSame([Storage::Integer, Storage::Text], $storages->merge([Storage::Text], [Storage::Integer]));
        self::assertSame([Storage::Blob], $storages->merge([Storage::Blob, Storage::Blob], []));
        self::assertSame([], $storages->merge([], []));
        self::assertSame(Storage::cases(), $storages->merge([Storage::Blob, Storage::Real], [Storage::Text, Storage::Integer]));
    }

    public function testFactIsNullOnlyOneKnownClassOrAChoice(): void
    {
        $storages = new Storages();
        $choice = $storages->fact([Storage::Text, Storage::Integer]);
        $known = $storages->fact([Storage::Real]);

        self::assertInstanceOf(NullOnly::class, $storages->fact([]));
        self::assertInstanceOf(Known::class, $known);
        self::assertSame(Storage::Real, $known->descriptor);
        self::assertInstanceOf(Choice::class, $choice);
        self::assertSame([Storage::Integer, Storage::Text], $choice->alternatives);
        self::assertInstanceOf(Known::class, $storages->fact([Storage::Text, Storage::Text]));
    }

    public function testEitherUnitesTheClassesOfTheAlternatives(): void
    {
        $storages = new Storages();
        $single = $storages->either([new Known(Storage::Integer), new NullOnly()]);
        $several = $storages->either([new Known(Storage::Integer), new Known(new ColumnDomain('TEXT'))]);

        self::assertInstanceOf(Known::class, $single);
        self::assertSame(Storage::Integer, $single->descriptor);
        self::assertInstanceOf(Choice::class, $several);
        self::assertSame([Storage::Integer, Storage::Text, Storage::Blob], $several->alternatives);
        self::assertInstanceOf(NullOnly::class, $storages->either([]));
        self::assertInstanceOf(NullOnly::class, $storages->either([new NullOnly(), new NullOnly()]));
    }

    public function testEitherIsInvalidWhenAnAlternativeIs(): void
    {
        $invalid = new Invalid(new Misuse(MisuseRule::HexLiteralTooBig));

        self::assertSame($invalid, (new Storages())->either([new Known(Storage::Integer), $invalid, new Dependent([new UnboundParameter('?')])]));
    }

    public function testEitherDependsOnTheMissingInputsOfEveryDependentAlternative(): void
    {
        $first = new UnboundParameter('?1');
        $second = new UnboundParameter('?2');
        $result = (new Storages())->either([new Dependent([$first]), new Known(Storage::Integer), new Dependent([$second])]);

        self::assertInstanceOf(Dependent::class, $result);
        self::assertSame([$first, $second], $result->missing);
    }

    public function testNumericIsRealWhenAnOperandIsCertainlyRealAndNullWhenOneIsCertainlyNull(): void
    {
        $storages = new Storages();
        $mixed = $storages->numeric([new Known(Storage::Integer), new Known(Storage::Text)]);
        $real = $storages->numeric([new Known(Storage::Integer), new Known(Storage::Real)]);

        self::assertInstanceOf(Choice::class, $mixed);
        self::assertSame([Storage::Integer, Storage::Real], $mixed->alternatives);
        self::assertInstanceOf(Known::class, $real);
        self::assertSame(Storage::Real, $real->descriptor);
        self::assertInstanceOf(NullOnly::class, $storages->numeric([new NullOnly(), new Known(Storage::Real)]));
        self::assertInstanceOf(Choice::class, $storages->numeric([new Dependent([new UnboundParameter('?')])]));
        self::assertInstanceOf(Choice::class, $storages->numeric([]));
    }

    public function testNumericTreatsAColumnAsRealOnlyWhenItCanHoldNothingElse(): void
    {
        $storages = new Storages();
        $loose = $storages->numeric([new Known(new ColumnDomain('REAL'))]);
        $strict = $storages->numeric([new Known(new ColumnDomain('REAL', true))]);

        self::assertInstanceOf(Choice::class, $loose);
        self::assertInstanceOf(Known::class, $strict);
        self::assertSame(Storage::Real, $strict->descriptor);
    }

    public function testStrictYieldsTheResultClassUnlessAnOperandIsCertainlyNull(): void
    {
        $storages = new Storages();
        $null = new NullOnly();
        $text = $storages->strict(Storage::Text, [new Known(Storage::Integer), new Dependent([new UnboundParameter('?')])]);

        self::assertSame($null, $storages->strict(Storage::Integer, [new Known(Storage::Integer), $null]));
        self::assertInstanceOf(Known::class, $text);
        self::assertSame(Storage::Text, $text->descriptor);
        self::assertInstanceOf(Known::class, $storages->strict(Storage::Integer, []));
    }
}
