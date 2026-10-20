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
use SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetNames;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(SetEncoding::class)]
#[Medium]
final class SetEncodingTest extends TestCase
{
    /**
     * @return iterable<string, array{SetNames|SetCharacterSet, string, NoticePhase}>
     */
    public static function providerConditions(): iterable
    {
        yield 'unknown names charset' => [new SetNames(new CharsetName(new Name('missing')), new CollationName(new Name('absent'))), "Unknown character set: 'missing'", NoticePhase::Syntax];
        yield 'unknown charset' => [new SetCharacterSet(new CharsetName(new Name('missing'))), "Unknown character set: 'missing'", NoticePhase::Syntax];
        yield 'unknown collation' => [new SetNames(new CharsetName(new Name('latin1')), new CollationName(new Name('absent'))), "Unknown collation: 'absent'", NoticePhase::Syntax];
        yield 'incompatible collation' => [new SetNames(new CharsetName(new Name('latin1')), new CollationName(new Name('utf8mb4_bin'))), "COLLATION 'utf8mb4_bin' is not valid for CHARACTER SET 'latin1'", NoticePhase::Contextualization];
    }

    #[DataProvider('providerConditions')]
    public function testDerivePreservesTheEncodingConditionPhase(SetNames|SetCharacterSet $item, string $message, NoticePhase $phase): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context());
        (new SetEncoding())->derive($item, $derivation);
        self::assertSame([$message], array_map(static fn ($problem): string => $problem->message(), $derivation->facts()->diagnostics));
        $notice = $derivation->facts()->warnings[0];
        self::assertInstanceOf(ParseFailure::class, $notice);
        self::assertTrue($notice->aborts);
        self::assertSame($phase, $notice->phase);
    }

    public function testCollationKeepsDefaultsDependentOnTheSession(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context());
        (new SetEncoding())->collation(new SetNames(new CharsetName(null), new CollationName(new Name('latin1_bin'))), $derivation);
        self::assertSame([], $derivation->facts()->diagnostics);
        self::assertSame([], $derivation->facts()->warnings);
    }
}
