<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Texts;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(Texts::class)]
#[Small]
final class TextsTest extends TestCase
{
    public function testLengthWritesADoubleInTwentyTwoCharacters(): void
    {
        $texts = new Texts(new Settings(Collation::known('latin1_bin')));

        self::assertSame(22, $texts->length(Domain::double()));
        self::assertSame(5, $texts->length(Domain::double(5, 2)));
        self::assertSame(0, $texts->length(Domain::null()));
        self::assertSame(4, $texts->length(Domain::integer(Field::LongLong, 4)));
    }

    public function testCollatedKeepsTheLengthAndMakesTheCollationExplicit(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));

        self::assertEquals(Domain::string(3, Collation::known('latin1_german1_ci'), Field::VarString, Coercibility::Explicit), (new Texts(new Settings(Collation::known('latin1_bin'))))->collated(Domain::string(3, Collation::known('latin1_bin')), 'latin1_german1_ci', $derivation));
        self::assertEquals(Domain::string(4, Collation::known('latin1_bin'), Field::VarString, Coercibility::Explicit), (new Texts(new Settings(Collation::known('latin1_bin'))))->collated(Domain::integer(Field::LongLong, 4), 'latin1_bin', $derivation));
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testCollatedReportsAnUnknownOrMismatchedCollation(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));

        self::assertNull((new Texts(new Settings(Collation::known('latin1_bin'))))->collated(Domain::string(1, Collation::known('latin1_bin')), 'klingon_ci', $derivation));
        self::assertNull((new Texts(new Settings(Collation::known('latin1_bin'))))->collated(Domain::string(1, Collation::known('latin1_bin')), 'utf8mb4_bin', $derivation));
        self::assertEquals([new UnknownCollation('klingon_ci'), new CollationMismatch('utf8mb4_bin', 'latin1')], $derivation->facts()->diagnostics);
    }

    public function testCollatedConvertsLegacyNumericByteWidthsToCharacterCounts(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]));
        $texts = new Texts(new Settings(Collation::known('latin1_bin')));

        self::assertSame(0, $texts->collated(Domain::integer(Field::LongLong, 1), 'utf8mb4_bin', $derivation)?->length);
        self::assertSame(1, $texts->collated(Domain::decimal(2, 1), 'utf8mb4_bin', $derivation)?->length);
        self::assertSame(3, $texts->collated(Domain::double(3), 'latin1_bin', $derivation)?->length);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testCollatedRejectsACollationIntroducedByALaterRelease(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]));

        self::assertNull((new Texts(new Settings(Collation::known('utf8mb4_general_ci'))))->collated(Domain::string(1, Collation::known('utf8mb4_general_ci')), 'utf8mb4_0900_ai_ci', $derivation));
        self::assertEquals([new UnknownCollation('utf8mb4_0900_ai_ci')], $derivation->facts()->diagnostics);
    }

    public function testCollatedUsesLegacyNumericBytesIndependentlyOfTheConnection(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]));
        $texts = new Texts(new Settings(Collation::known('utf8mb4_general_ci')));

        self::assertSame(0, $texts->collated(Domain::integer(Field::LongLong, 1), 'utf8mb4_bin', $derivation)?->length);
        self::assertSame(1, $texts->collated(Domain::decimal(2, 1), 'utf8mb4_bin', $derivation)?->length);
        self::assertSame(0, $texts->collated(Domain::double(3), 'utf8mb4_bin', $derivation)?->length);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testCollatedRequiresLatin1ForNumericValuesInMySql56(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.6.51'))->context([]));

        self::assertNull((new Texts(new Settings(Collation::known('utf8mb4_general_ci'))))->collated(Domain::integer(Field::LongLong, 1), 'utf8mb4_bin', $derivation));
        self::assertEquals([new CollationMismatch('utf8mb4_bin', 'latin1')], $derivation->facts()->diagnostics);
    }

    public function testBinaryCountsTheBytesOfAString(): void
    {
        self::assertEquals(Domain::string(12, Collation::binary()), (new Texts(new Settings(Collation::known('latin1_bin'))))->binary(Domain::string(3, Collation::known('utf8mb4_bin'))));
        self::assertEquals(Domain::string(22, Collation::binary(), Field::Blob), (new Texts(new Settings(Collation::known('latin1_bin'))))->binary(Domain::string(22, Collation::binary(), Field::Blob)));
    }

    public function testConvertedTakesTheDefaultCollationOfTheCharacterSet(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));

        self::assertEquals(Domain::string(3, Collation::known('utf8mb4_0900_ai_ci')), (new Texts(new Settings(Collation::known('latin1_bin'))))->converted(Domain::string(3, Collation::known('latin1_bin')), 'utf8mb4', $derivation));
        self::assertNull((new Texts(new Settings(Collation::known('latin1_bin'))))->converted(Domain::string(3, Collation::known('latin1_bin')), 'klingon', $derivation));
        self::assertEquals([new UnknownCharset('klingon')], $derivation->facts()->diagnostics);
    }

    public function testCharacterCountsFourCharactersForEachCode(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $texts = new Texts(new Settings(Collation::known('latin1_bin')));

        self::assertEquals(Domain::string(8, Collation::binary(), Field::VarString, Coercibility::Coercible), $texts->character(2, null, $derivation));
        self::assertSame('latin1_swedish_ci', $texts->character(1, 'latin1', $derivation)?->collation->name);
        self::assertNull($texts->character(1, 'klingon', $derivation));
        self::assertEquals([new UnknownCharset('klingon')], $derivation->facts()->diagnostics);
    }

    public function testConvertedIsAMediumBlobBeyond65535Bytes(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));

        self::assertEquals(Domain::string(262140, Collation::known('utf8mb4_0900_ai_ci'), Field::MediumBlob), (new Texts(new Settings(Collation::known('latin1_bin'))))->converted(Domain::string(65535, Collation::known('latin1_bin'), Field::Blob), 'utf8mb4', $derivation));
    }
}
