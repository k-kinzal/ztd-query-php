<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Configuration\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Configuration\Role\AccountNames;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\Role\SetExplicitRolesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AccountNames::class)]
#[Medium]
final class AccountNamesTest extends TestCase
{
    public function testReadKeepsUserAndHostAsSeparateIdentities(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET ROLE 'CaseSensitive'@'localhost', reader");
        self::assertInstanceOf(SetExplicitRolesStatement::class, $statement);
        self::assertSame('CaseSensitive', $statement->roles[0]->username);
        self::assertSame('localhost', $statement->roles[0]->host);
        self::assertSame('reader', $statement->roles[1]->username);
        self::assertNull($statement->roles[1]->host);
    }

    #[DataProvider('providerQuotedNames')]
    public function testPartDecodesNamesWithoutChangingTheirIdentity(string $spelling, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('SET ROLE ' . $spelling);
        self::assertInstanceOf(SetExplicitRolesStatement::class, $statement);
        self::assertSame($expected, $statement->roles[0]->username);
        $again = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(SetExplicitRolesStatement::class, $again);
        self::assertSame($expected, $again->roles[0]->username);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerQuotedNames(): iterable
    {
        yield 'doubled quote' => ["'a''b'", "a'b"];
        yield 'backslash quote' => ["'a\\'b'", "a'b"];
        yield 'newline escape' => ["'a\\nb'", "a\nb"];
        yield 'escaped backslash' => ["'a\\\\b'", 'a\b'];
        yield 'pattern escape' => ["'a\\_b'", 'a\_b'];
        yield 'quoted identifier' => ['`a``b`', 'a`b'];
        yield 'empty string' => ["''", ''];
    }
}
