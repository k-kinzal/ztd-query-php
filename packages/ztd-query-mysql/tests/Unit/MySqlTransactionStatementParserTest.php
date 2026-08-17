<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlTransactionStatementParser;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTransactionManager;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenStream;
use ZtdQuery\Sql\TransactionStatement;

#[CoversClass(MySqlTransactionStatementParser::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(SqlTokenStream::class)]
#[UsesClass(TransactionStatement::class)]
#[UsesClass(ShadowStore::class)]
#[UsesClass(ShadowTransactionManager::class)]
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

        self::assertNull($parser->parse('BEGIN IMMEDIATE'));
        self::assertNull($parser->parse('END TRANSACTION'));
        self::assertNull($parser->parse('RELEASE point'));
        self::assertNull($parser->parse('SAVEPOINT point extra'));
        self::assertNull($parser->parse('SAVEPOINT `broken'));
    }
}
