<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Configuration\ConnectionCharsets;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Configuration\Connection\ConnectionCharacterSet;
use SqlSemantics\Model\Configuration\Connection\ConnectionNames;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ConnectionCharsets::class)]
#[Medium]
final class ConnectionCharsetsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindSeparatesNamesAndCharacterSetFromVariablesAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind("SET NAMES 'utf8mb4' COLLATE utf8mb4_bin, CHAR SET DEFAULT, sql_mode = ''");
        self::assertInstanceOf(SetStatement::class, $statement);
        [$names, $charset, $mode] = $statement->settings;
        self::assertInstanceOf(ConnectionNames::class, $names);
        self::assertInstanceOf(ConnectionCharacterSet::class, $charset);
        self::assertInstanceOf(AssignedSetting::class, $mode);
        self::assertSame(['utf8mb4', 'utf8mb4_bin', null], [$names->characterSet, $names->collation, $charset->characterSet]);
        self::assertSame("SET NAMES `utf8mb4` COLLATE `utf8mb4_bin`, CHARACTER SET DEFAULT, `sql_mode` = ''", $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testBindRejectsAssigningNames(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::SessionSetting->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET NAMES = 'utf8'");
    }

    public function testNameReadsTheBinaryKeywordAsTheBinaryCharacterSet(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $binary = $binder->bind('SET CHARSET BINARY');
        $quoted = $binder->bind('SET CHARSET `Latin1`');
        self::assertInstanceOf(SetStatement::class, $binary);
        self::assertInstanceOf(SetStatement::class, $quoted);
        self::assertInstanceOf(ConnectionCharacterSet::class, $binary->settings[0]);
        self::assertInstanceOf(ConnectionCharacterSet::class, $quoted->settings[0]);
        self::assertSame(['binary', 'Latin1'], [$binary->settings[0]->characterSet, $quoted->settings[0]->characterSet]);
    }

    public function testKeywordCountsTheWordsOfTheItemKeyword(): void
    {
        self::assertSame([1, 2, 2, 0], [ConnectionCharsets::keyword(['NAMES']), ConnectionCharsets::keyword(['CHAR', 'SET']), ConnectionCharsets::keyword(['CHARACTER', 'SET', 'X']), ConnectionCharsets::keyword(['CHARACTER'])]);
    }
}
