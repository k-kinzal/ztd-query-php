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
    public function testCharsetWarningSharesTheDecodedNameMapping(): void
    {
        self::assertSame(Deprecated::Utf8Alias, Deprecation::charsetWarning('UTF8'));
        self::assertSame(Deprecated::Utf8mb3, Deprecation::charsetWarning('utf8mb3'));
        self::assertSame(Deprecated::Ucs2, Deprecation::charsetWarning('Ucs2'));
        self::assertNull(Deprecation::charsetWarning('utf8mb4'));
    }

    public function testPhaseSeparatesFunctionContextualizationFromGrammarWarnings(): void
    {
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization, (new Deprecation(Deprecated::SystemVariable))->phase());
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization, (new Deprecation(Deprecated::FoundRows))->phase());
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Syntax, (new Deprecation(Deprecated::BangNot))->phase());
    }

    public function testRaiseRecordsTheWarningOnlyInReleasesThatWarn(): void
    {
        $modern = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $legacy = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]));

        Deprecation::raise(Deprecated::BangNot, $modern);
        Deprecation::raise(Deprecated::BangNot, $legacy);

        self::assertEquals([new Deprecation(Deprecated::BangNot)], $modern->facts()->warnings);
        self::assertSame([], $legacy->facts()->warnings);
    }

    public function testSpatialWarnsOnlyForLegacyNamesIn57(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]));
        Deprecation::spatial('srid', $derivation);
        Deprecation::spatial('ST_SRID', $derivation);

        self::assertCount(1, $derivation->facts()->warnings);
        self::assertInstanceOf(Deprecation::class, $derivation->facts()->warnings[0]);
        self::assertSame(1287, $derivation->facts()->warnings[0]->code());
        self::assertSame("'SRID' is deprecated and will be removed in a future release. Please use ST_SRID instead", $derivation->facts()->warnings[0]->message());
        self::assertSame([], (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT SRID(NULL)')->facts->warnings);
    }

    public function testSpatialWarningUsesTheReleaseAndNormalizesTheName(): void
    {
        $warning = Deprecation::spatialWarning('srid', \SqlSemantics\Contract\GrammarRelease::MySql5744);

        self::assertNotNull($warning);
        self::assertSame(Deprecated::SpatialFunction, $warning->construct);
        self::assertNull(Deprecation::spatialWarning('srid', \SqlSemantics\Contract\GrammarRelease::MySql847));
        self::assertNull(Deprecation::spatialWarning('ST_SRID', \SqlSemantics\Contract\GrammarRelease::MySql5744));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['SELECT CONTAINS(NULL,NULL)'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['CREATE PROCEDURE p() BEGIN IF @run THEN SELECT CONTAINS((1,2),NULL); END IF; END'])]
    public function testContainsRetainsItsMbrReplacementInOrdinaryAndStoredCalls(string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $warnings = $semantics->analyze($sql)->facts->warnings;
        self::assertCount(1, $warnings);
        self::assertSame("'CONTAINS' is deprecated and will be removed in a future release. Please use MBRCONTAINS instead", $warnings[0]->message());
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
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT, c INT)');
        $codes = static fn (string $sql): array => array_map(static fn ($warning): string => $warning->message(), $semantics->analyze($sql, [$table])->facts->warnings);

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

    public function testCharsetWarnsOfUcs2FromMySql80(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame([Deprecated::Ucs2->value], array_map(static fn ($warning): string => $warning->message(), $semantics->analyze("SELECT _ucs2'a'", [])->facts->warnings));
    }

    public function testVariableWarnsOfEachReadOfADeprecatedVariableIn57(): void
    {
        $warnings = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT @@tx_isolation, @@GLOBAL.tx_isolation, @@transaction_isolation')->facts->warnings;

        self::assertSame(["'@@tx_isolation' is deprecated and will be removed in a future release. Please use '@@transaction_isolation' instead", "'@@tx_isolation' is deprecated and will be removed in a future release. Please use '@@transaction_isolation' instead"], array_map(static fn ($warning): string => $warning->message(), $warnings));
        self::assertInstanceOf(Deprecation::class, $warnings[0]);
        self::assertSame(1287, $warnings[0]->code());
    }
}
