<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TypeNotices;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;

#[CoversClass(TypeNotices::class)]
#[Medium]
final class TypeNoticesTest extends TestCase
{
    public function testTypeWarnsAboutTheDeprecatedPartsOfEachColumnType(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $warnings = $semantics->analyze('CREATE TABLE w (a INT(11), b INT ZEROFILL, c FLOAT(5,2), d DECIMAL(5,2) UNSIGNED, f TINYINT(1), g YEAR(4), h CHAR(1) CHARACTER SET utf8)')->facts->warnings;

        self::assertSame([Deprecated::DisplayWidth, Deprecated::Zerofill, Deprecated::FloatingDigits, Deprecated::UnsignedFraction, Deprecated::YearWidth, Deprecated::Utf8Alias], array_map(static fn ($warning): Deprecated => $warning->construct, $warnings));
    }

    public function testTypeAcceptsTinyintOfWidthOne(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));

        (new TypeNotices())->type(new Integral(IntegralKind::TinyInt, '1'), $derivation);

        self::assertSame([], $derivation->facts()->warnings);
    }

    public function testCharsetWarnsAboutUtf8Only(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));

        (new TypeNotices())->charset(null, $derivation);

        self::assertSame([], $derivation->facts()->warnings);
    }
}
