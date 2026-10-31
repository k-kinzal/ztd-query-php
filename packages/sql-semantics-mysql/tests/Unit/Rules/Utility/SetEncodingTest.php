<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Utility\SetEncoding;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetNames;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(SetEncoding::class)]
#[Medium]
final class SetEncodingTest extends TestCase
{
    /**
     * @return iterable<string, array{SetNames|SetCharacterSet, list<int>}>
     */
    public static function providerConditions(): iterable
    {
        yield 'deprecated names of SET NAMES' => [new SetNames(new CharsetName(new Name('utf8')), new CollationName(new Name('utf8_bin'))), [3719, 3778]];
        yield 'SET CHARACTER SET warns of nothing' => [new SetCharacterSet(new CharsetName(new Name('utf8'))), []];
    }

    /**
     * @param list<int> $codes The warning codes in written order
     */
    #[DataProvider('providerConditions')]
    public function testDeriveWarnsOfDeprecatedNamesInWrittenOrder(SetNames|SetCharacterSet $item, array $codes): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context());
        (new SetEncoding())->derive($item, $derivation);
        self::assertSame([], $derivation->facts()->diagnostics);
        self::assertSame($codes, array_map(static fn (\SqlSemantics\Statement\Fact\Warning $warning): int => $warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\CodedWarning ? $warning->code() : 0, $derivation->facts()->warnings));
    }

    public function testMismatchFindsACollationOfAnotherCharacterSetAndNamesItForTheRelease(): void
    {
        $rule = new SetEncoding();
        $mismatch = $rule->mismatch(new CharsetName(new Name('utf8')), new CollationName(new Name('latin1_bin')), \SqlSemantics\Contract\GrammarRelease::MySql5744);

        self::assertSame("COLLATION 'latin1_bin' is not valid for CHARACTER SET 'utf8'", $mismatch?->message());
        self::assertSame("COLLATION 'latin1_bin' is not valid for CHARACTER SET 'utf8mb3'", $rule->mismatch(new CharsetName(new Name('utf8')), new CollationName(new Name('latin1_bin')))?->message());
        self::assertNull($rule->mismatch(new CharsetName(new Name('utf8mb4')), new CollationName(new Name('utf8mb4_bin'))));
        self::assertNull($rule->mismatch(new CharsetName(null), new CollationName(new Name('latin1_bin'))));
        self::assertNull($rule->mismatch(new CharsetName(new Name('utf8mb4')), null));
    }

    public function testCollationKeepsDefaultsDependentOnTheSession(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context());
        (new SetEncoding())->collation(new SetNames(new CharsetName(null), new CollationName(new Name('latin1_bin'))), $derivation);
        self::assertSame([], $derivation->facts()->diagnostics);
        self::assertSame([], $derivation->facts()->warnings);
    }
}
