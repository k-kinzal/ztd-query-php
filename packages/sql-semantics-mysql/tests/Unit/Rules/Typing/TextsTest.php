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
        $texts = $this->texts();

        self::assertSame(22, $texts->length(Domain::double()));
        self::assertSame(5, $texts->length(Domain::double(5, 2)));
        self::assertSame(0, $texts->length(Domain::null()));
        self::assertSame(4, $texts->length(Domain::integer(Field::LongLong, 4)));
    }

    public function testCollatedKeepsTheLengthAndMakesTheCollationExplicit(): void
    {
        $derivation = $this->derivation();

        self::assertEquals(Domain::string(3, Collation::known('latin1_german1_ci'), Field::VarString, Coercibility::Explicit), $this->texts()->collated(Domain::string(3, Collation::known('latin1_bin')), 'latin1_german1_ci', $derivation));
        self::assertEquals(Domain::string(4, Collation::known('latin1_bin'), Field::VarString, Coercibility::Explicit), $this->texts()->collated(Domain::integer(Field::LongLong, 4), 'latin1_bin', $derivation));
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testCollatedReportsAnUnknownOrMismatchedCollation(): void
    {
        $derivation = $this->derivation();

        self::assertNull($this->texts()->collated(Domain::string(1, Collation::known('latin1_bin')), 'klingon_ci', $derivation));
        self::assertNull($this->texts()->collated(Domain::string(1, Collation::known('latin1_bin')), 'utf8mb4_bin', $derivation));
        self::assertEquals([new UnknownCollation('klingon_ci'), new CollationMismatch('utf8mb4_bin', 'latin1')], $derivation->facts()->diagnostics);
    }

    public function testBinaryCountsTheBytesOfAString(): void
    {
        self::assertEquals(Domain::string(12, Collation::binary()), $this->texts()->binary(Domain::string(3, Collation::known('utf8mb4_bin'))));
        self::assertEquals(Domain::string(22, Collation::binary(), Field::Blob), $this->texts()->binary(Domain::string(22, Collation::binary(), Field::Blob)));
    }

    public function testConvertedTakesTheDefaultCollationOfTheCharacterSet(): void
    {
        $derivation = $this->derivation();

        self::assertEquals(Domain::string(3, Collation::known('utf8mb4_0900_ai_ci')), $this->texts()->converted(Domain::string(3, Collation::known('latin1_bin')), 'utf8mb4', $derivation));
        self::assertNull($this->texts()->converted(Domain::string(3, Collation::known('latin1_bin')), 'klingon', $derivation));
        self::assertEquals([new UnknownCharset('klingon')], $derivation->facts()->diagnostics);
    }

    public function texts(): Texts
    {
        return new Texts(new Settings(Collation::known('latin1_bin')));
    }

    public function derivation(): Derivation
    {
        return new Derivation((new Semantics(Dialect::MySql))->context([]));
    }
}
