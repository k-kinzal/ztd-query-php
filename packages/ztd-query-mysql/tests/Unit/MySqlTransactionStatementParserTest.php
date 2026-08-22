<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Platform\MySql\MySqlTransactionStatementParser;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTransactionManager;

#[CoversClass(MySqlTransactionStatementParser::class)]
#[UsesClass(MySqlLexerProfile::class)]
final class MySqlTransactionStatementParserTest extends TestCase
{
    #[DataProvider('providerMySqlTransactionForms')]
    public function testParsesMySqlTransactionForms(string $sql): void
    {
        self::assertNotNull((new MySqlTransactionStatementParser())->parse($sql));
    }

    /** @return array<string, array{string}> */
    public static function providerMySqlTransactionForms(): array
    {
        return [
            'begin' => ['BEGIN;'],
            'begin work' => ['BEGIN WORK'],
            'start' => ['START TRANSACTION'],
            'commit' => ['COMMIT'],
            'commit work' => ['COMMIT WORK'],
            'rollback' => ['ROLLBACK'],
            'rollback work' => ['ROLLBACK WORK'],
            'savepoint' => ['SAVEPOINT `a``b`'],
            'rollback to' => ['ROLLBACK TO `a``b`'],
            'rollback to savepoint' => ['ROLLBACK TO SAVEPOINT point'],
            'release' => ['RELEASE SAVEPOINT point'],
        ];
    }

    public function testRejectsNonMySqlAndMalformedForms(): void
    {
        $parser = new MySqlTransactionStatementParser();

        self::assertNull($parser->parse(''));
        self::assertNull($parser->parse('BEGIN IMMEDIATE'));
        self::assertNull($parser->parse('END TRANSACTION'));
        self::assertNull($parser->parse('RELEASE point'));
        self::assertNull($parser->parse('SAVEPOINT point extra'));
        self::assertNull($parser->parse('SAVEPOINT `broken'));
    }

    public function testAppliesUnescapedMySqlSavepointName(): void
    {
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $transactions = new ShadowTransactionManager($store);
        $transactions->begin();
        $statement = (new MySqlTransactionStatementParser())->parse('SAVEPOINT `a``b`');
        self::assertNotNull($statement);
        $statement->apply($transactions);
        $store->insert('items', [['id' => 2]]);

        $transactions->rollBackTo('a`b');

        self::assertSame([['id' => 1]], $store->get('items'));
    }
}
