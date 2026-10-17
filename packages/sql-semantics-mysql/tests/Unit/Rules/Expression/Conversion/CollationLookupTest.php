<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Expression\Conversion\CollationLookup;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

#[CoversClass(CollationLookup::class)]
#[Medium]
final class CollationLookupTest extends TestCase
{
    public function testParsedUsesTheReleaseSpecificLookupPhase(): void
    {
        $lookup = new CollationLookup();
        self::assertNull($lookup->parsed(new Name('missing'), GrammarRelease::MySql5651));
        self::assertNull($lookup->parsed(new Name('missing'), GrammarRelease::MySql5744));
        self::assertInstanceOf(UnknownCollation::class, $lookup->parsed(new Name('missing'), GrammarRelease::MySql847));
        self::assertNull($lookup->parsed(new Name(''), GrammarRelease::MySql847));
        self::assertNull($lookup->parsed(new Name('binary'), GrammarRelease::MySql847));
    }

    public function testDerivePlacesLegacyNameLookupAfterTheOperand(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([], true));
        $derivation->scalar(new Collated(new ColumnUse(new Name('absent')), new Name('missing')), $derivation->environment());
        self::assertInstanceOf(MissingColumn::class, $derivation->facts()->diagnostics[0]);
        self::assertInstanceOf(UnknownCollation::class, $derivation->facts()->diagnostics[1]);
        self::assertSame([], $derivation->facts()->warnings);
    }
}
