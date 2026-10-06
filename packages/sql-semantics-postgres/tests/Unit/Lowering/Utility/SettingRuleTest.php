<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Lowering\Utility\SettingRule;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\TypedLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterSource;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\Reset;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetParameterFrom;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetSpecialSetting;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetTimeZone;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialParameter;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ZoneKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SetConstraints;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(SettingRule::class)]
#[Medium]
final class SettingRuleTest extends TestCase
{
    public function testStatementLowersEachCommand(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ['SET LOCAL a TO 1', 'RESET a', 'SHOW a', 'SHOW ALL', 'ALTER SYSTEM RESET ALL', 'SET CONSTRAINTS ALL IMMEDIATE', 'DISCARD TEMPORARY'],
            [
                $semantics->analyze('SET LOCAL a = 1')->toString(),
                $semantics->analyze('RESET a')->toString(),
                $semantics->analyze('SHOW a')->toString(),
                $semantics->analyze('SHOW ALL')->toString(),
                $semantics->analyze('ALTER SYSTEM RESET ALL')->toString(),
                $semantics->analyze('SET CONSTRAINTS ALL IMMEDIATE')->toString(),
                $semantics->analyze('DISCARD TEMPORARY')->toString(),
            ],
        );
    }

    public function testClauseLowersTheSettingOfARoleOrRoutine(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ['ALTER ROLE r SET search_path TO a', 'ALTER FUNCTION f () SET TIME ZONE LOCAL'],
            [$semantics->analyze('ALTER ROLE r SET search_path = a')->toString(), $semantics->analyze('ALTER FUNCTION f() SET TIME ZONE LOCAL')->toString()],
        );
    }

    public function testSetLowersTransactionModes(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ['SET TRANSACTION ISOLATION LEVEL SERIALIZABLE', 'SET LOCAL SESSION CHARACTERISTICS AS TRANSACTION READ ONLY'],
            [$semantics->analyze('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE')->toString(), $semantics->analyze('SET LOCAL SESSION CHARACTERISTICS AS TRANSACTION READ ONLY')->toString()],
        );
    }

    public function testMoreLowersTheKeywordForms(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ["SET SCHEMA 'a'", 'SET ROLE r', "SET SESSION AUTHORIZATION 'u'", 'SET XML OPTION DOCUMENT', "SET TRANSACTION SNAPSHOT '1'", "SET CATALOG 'd'", 'SET a FROM CURRENT'],
            [
                $semantics->analyze("SET SCHEMA 'a'")->toString(),
                $semantics->analyze('SET ROLE r')->toString(),
                $semantics->analyze("SET SESSION AUTHORIZATION 'u'")->toString(),
                $semantics->analyze('SET XML OPTION DOCUMENT')->toString(),
                $semantics->analyze("SET TRANSACTION SNAPSHOT '1'")->toString(),
                $semantics->analyze("SET CATALOG 'd'")->toString(),
                $semantics->analyze('SET a FROM CURRENT')->toString(),
            ],
        );
    }

    public function testGenericLowersDefaultAsTheSource(): void
    {
        $statement = (new Semantics(Dialect::PostgreSql))->analyze('SET a = DEFAULT')->statement;
        self::assertInstanceOf(SetParameterFrom::class, $statement);
        self::assertSame(ParameterSource::Default, $statement->source);
    }

    public function testParameterLowersTheDottedName(): void
    {
        $statement = (new Semantics(Dialect::PostgreSql))->analyze('RESET plpgsql.Variable_Conflict')->statement;
        self::assertInstanceOf(Reset::class, $statement);
        self::assertEquals([new Name('plpgsql'), new Name('variable_conflict')], $statement->parameter instanceof \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterName ? $statement->parameter->parts : []);
    }

    public function testZoneLowersEachForm(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $zones = array_map(
            static fn (string $sql): string => ($statement = $semantics->analyze($sql)->statement) instanceof SetTimeZone ? $statement->zone::class : '',
            ["SET TIME ZONE 'UTC'", 'SET TIME ZONE utc', "SET TIME ZONE INTERVAL '1' HOUR", "SET TIME ZONE INTERVAL(2) '1:30'", 'SET TIME ZONE -7', 'SET TIME ZONE DEFAULT', 'SET TIME ZONE LOCAL'],
        );
        self::assertSame([StringConstant::class, Name::class, TypedLiteral::class, TypedLiteral::class, SignedNumber::class, ZoneKeyword::class, ZoneKeyword::class], $zones);
    }

    public function testNamesLowersEachEncoding(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $statements = [$semantics->analyze("SET NAMES 'UTF8'")->statement, $semantics->analyze('SET NAMES DEFAULT')->statement, $semantics->analyze('SET NAMES')->statement];
        self::assertSame(
            [['UTF8', false], [null, true], [null, false]],
            array_map(static fn (object $statement): array => $statement instanceof SetSpecialSetting ? [$statement->value instanceof StringConstant ? $statement->value->value : null, $statement->default] : [], $statements),
        );
    }

    public function testResetLowersEachKeywordForm(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $statements = [$semantics->analyze('RESET TIME ZONE')->statement, $semantics->analyze('RESET TRANSACTION ISOLATION LEVEL')->statement, $semantics->analyze('RESET SESSION AUTHORIZATION')->statement, $semantics->analyze('RESET ALL')->statement];
        self::assertSame(
            [SpecialParameter::TimeZone, SpecialParameter::TransactionIsolationLevel, SpecialParameter::SessionAuthorization, SpecialParameter::All],
            array_map(static fn (object $statement): ?object => $statement instanceof Reset ? $statement->parameter : null, $statements),
        );
    }

    public function testConstraintsLowersAllAsNoName(): void
    {
        $all = (new Semantics(Dialect::PostgreSql))->analyze('SET CONSTRAINTS ALL DEFERRED')->statement;
        $named = (new Semantics(Dialect::PostgreSql))->analyze('SET CONSTRAINTS a, s.b DEFERRED')->statement;
        self::assertInstanceOf(SetConstraints::class, $all);
        self::assertInstanceOf(SetConstraints::class, $named);
        self::assertSame([0, 2], [count($all->constraints), count($named->constraints)]);
    }

    public function testDeferredTellsTheMode(): void
    {
        $deferred = (new Semantics(Dialect::PostgreSql))->analyze('SET CONSTRAINTS ALL DEFERRED')->statement;
        $immediate = (new Semantics(Dialect::PostgreSql))->analyze('SET CONSTRAINTS ALL IMMEDIATE')->statement;
        self::assertInstanceOf(SetConstraints::class, $deferred);
        self::assertInstanceOf(SetConstraints::class, $immediate);
        self::assertSame([true, false], [$deferred->deferred, $immediate->deferred]);
    }
}
