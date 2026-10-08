<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Notice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;

#[CoversClass(Deprecation::class)]
#[Medium]
final class DeprecationTest extends TestCase
{
    public function testRaiseRecordsTheWarningOnlyInReleasesThatWarn(): void
    {
        $modern = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $legacy = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]));

        Deprecation::raise(Deprecated::BangNot, $modern);
        Deprecation::raise(Deprecated::BangNot, $legacy);

        self::assertEquals([new Deprecation(Deprecated::BangNot)], $modern->facts()->warnings);
        self::assertSame([], $legacy->facts()->warnings);
    }

    public function testCodeIsThatOfTheConstruct(): void
    {
        self::assertSame(3005, (new Deprecation(Deprecated::InsertDelayed))->code());
        self::assertSame(1287, (new Deprecation(Deprecated::BangNot))->code());
    }

    public function testMessageIsTheTextOfTheConstruct(): void
    {
        self::assertSame('INSERT DELAYED is no longer supported. The statement was converted to INSERT.', (new Deprecation(Deprecated::InsertDelayed))->message());
        self::assertSame("'!' is deprecated and will be removed in a future release. Please use NOT instead", (new Deprecation(Deprecated::BangNot))->message());
    }

    public function testStatementsRaiseTheirWarningsInWrittenOrder(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $codes = static fn (string $sql): array => array_map(static fn ($warning): string => $warning->message(), $semantics->analyze($sql)->facts->warnings);

        self::assertSame([Deprecated::BangNot->value, Deprecated::BinaryOperator->value, Deprecated::PipesOr->value], $codes('SELECT !a, BINARY b || c FROM t'));
        self::assertSame([Deprecated::CalcFoundRows->value, Deprecated::FoundRows->value], $codes('SELECT SQL_CALC_FOUND_ROWS FOUND_ROWS()'));
        self::assertSame([Deprecated::ReplaceDelayed->value], $codes('REPLACE DELAYED INTO t (a) VALUES (1)'));
        self::assertSame([Deprecated::ValuesFunction->value], $codes('INSERT INTO t (a) VALUES (1) ON DUPLICATE KEY UPDATE a = VALUES(a)'));
        self::assertSame([Deprecated::AssignmentInExpression->value], $codes('SELECT @a := 1'));
    }

    public function testCharsetRecordsTheWarningOfUtf8AndUtf8mb3(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));

        Deprecation::charset('UTF8MB3', $derivation);
        Deprecation::charset('utf8', $derivation);
        Deprecation::charset('utf8mb4', $derivation);

        self::assertEquals([new Deprecation(Deprecated::Utf8mb3), new Deprecation(Deprecated::Utf8Alias)], $derivation->facts()->warnings);
        self::assertSame(1287, (new Deprecation(Deprecated::Utf8mb3))->code());
    }

}
