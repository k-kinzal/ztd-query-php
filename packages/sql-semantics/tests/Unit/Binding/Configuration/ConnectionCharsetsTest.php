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

    /**
     * @param list<string> $texts
     */
    #[TestWith([['NAMES']])]
    #[TestWith([['NAMES', '=', 'x']])]
    #[TestWith([['NAMES', 'a', 'b']])]
    #[TestWith([['CHARSET', 'a', 'COLLATE', 'b']])]
    #[TestWith([['NAMES', 'a', 'b', 'COLLATE']])]
    public function testBindRejectsAMalformedItem(array $texts): void
    {
        $tokens = array_map(static fn (string $text): \SqlParser\Lexer\Token => new \SqlParser\Lexer\Token(0, 'IDENT', $text, 0), $texts);
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::SessionSetting->message());
        ConnectionCharsets::bind($tokens, new \SqlParser\Parser\Node('option_value', 0, []), new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql)));
    }

    public function testBindReadsNamesWithACollation(): void
    {
        $tokens = array_map(static fn (string $text): \SqlParser\Lexer\Token => new \SqlParser\Lexer\Token(0, 'IDENT', $text, 0), ['names', 'a', 'collate', 'b']);
        $setting = ConnectionCharsets::bind($tokens, new \SqlParser\Parser\Node('option_value', 0, []), new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql)));
        self::assertInstanceOf(ConnectionNames::class, $setting);
        self::assertSame(['a', 'b'], [$setting->characterSet, $setting->collation]);
    }

    public function testBindReadsACharacterSet(): void
    {
        $tokens = array_map(static fn (string $text): \SqlParser\Lexer\Token => new \SqlParser\Lexer\Token(0, 'IDENT', $text, 0), ['CHAR', 'SET', 'a']);
        $setting = ConnectionCharsets::bind($tokens, new \SqlParser\Parser\Node('option_value', 0, []), new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql)));
        self::assertInstanceOf(ConnectionCharacterSet::class, $setting);
        self::assertSame('a', $setting->characterSet);
    }

    public function testNameReadsTheBinaryTokenInAnyCase(): void
    {
        self::assertSame('binary', ConnectionCharsets::name(new \SqlParser\Lexer\Token(0, 'BINARY_SYM', 'BINARY', 0), new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql))));
        self::assertNull(ConnectionCharsets::name(new \SqlParser\Lexer\Token(0, 'DEFAULT_SYM', 'DEFAULT', 0), new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql))));
    }
}
