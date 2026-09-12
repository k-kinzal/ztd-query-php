<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Type\Enum\RankEdits;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[CoversClass(RankEdits::class)]
final class RankEditsTest extends TestCase
{
    public function testEnumMembersDecodesOnlyCompleteQuotedMembers(): void
    {
        $edits = new RankEdits();
        self::assertSame(['new', "it's done"], $edits->enumMembers("ENUM('new', 'it''s done')"));
        self::assertSame([], $edits->enumMembers('VARCHAR(50)'));
        self::assertSame([], $edits->enumMembers('ENUM(1)'));
        self::assertSame([], $edits->enumMembers('ENUM'));
    }

    public function testEnumColumnsDetectsAmbiguousUnqualifiedNames(): void
    {
        $left = new \ZtdQuery\Schema\ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::STRING, "ENUM('new','done')");
        $right = new \ZtdQuery\Schema\ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::STRING, "ENUM('off','on')");
        self::assertSame([
            ['users.status' => ['new', 'done'], 'orders.status' => ['off', 'on']],
            ['status' => null],
        ], (new RankEdits())->enumColumns([
            'users' => ['columnTypes' => ['status' => $left]],
            'orders' => ['columnTypes' => ['status' => $right]],
            'view' => ['viewSql' => 'SELECT 1'],
        ]));
    }

    public function testComparisonEditsRanksBothOperands(): void
    {
        $sql = "status < 'done'";
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $edits = (new RankEdits())->comparisonEdits($sql, $tokens, [], ['status' => ['new', 'done']]);
        self::assertSame([
            0 => ['start' => 0, 'end' => 6, 'replacement' => "FIELD(status, 'new', 'done')"],
            9 => ['start' => 9, 'end' => 15, 'replacement' => "FIELD('done', 'new', 'done')"],
        ], $edits);
    }

    public function testOrderByEditsLeavesArithmeticExpressionsUnchanged(): void
    {
        $sql = 'SELECT * FROM users ORDER BY status DESC LIMIT 2';
        $edits = (new RankEdits())->orderByEdits($sql, \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens(), [], ['status' => ['new', 'done']]);
        self::assertCount(1, $edits);
        self::assertSame("FIELD(status, 'new', 'done')", array_values($edits)[0]['replacement']);
        $sql = 'SELECT * FROM users ORDER BY status + 1';
        self::assertSame([], (new RankEdits())->orderByEdits($sql, \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens(), [], ['status' => ['new', 'done']]));
    }

    public function testColumnAtResolvesQualifiedColumnsAsOneSpan(): void
    {
        $sql = '`users`.`status`';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $column = (new RankEdits())->columnAt($sql, $tokens, 0, ['users.status' => ['new', 'done']], []);
        self::assertNotNull($column);
        self::assertSame(3, $column['length']);
        self::assertSame($sql, $column['token']->text);
        self::assertSame(['new', 'done'], $column['members']);
        self::assertNull((new RankEdits())->columnAt($sql, $tokens, 2, ['users.status' => ['new', 'done']], []));
    }

    public function testOrderedOperatorAtConsumesOnlyOrderingSymbols(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('>= 1', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $edits = new RankEdits();
        self::assertSame(['length' => 2], $edits->orderedOperatorAt($tokens, 0));
        self::assertNull($edits->orderedOperatorAt($tokens, 2));
        self::assertNull($edits->orderedOperatorAt([], 0));
    }

    public function testAddRankEditEscapesMembersAndRecordsOffsets(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('status', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $edits = [];
        (new RankEdits())->addRankEdit($edits, $tokens[0], ["a'b", 'c']);
        self::assertSame([0 => ['start' => 0, 'end' => 6, 'replacement' => "FIELD(status, 'a''b', 'c')"]], $edits);
    }

    public function testIsOrderTerminatorRequiresUnquotedClauseKeywords(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('LIMIT `LIMIT`', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $edits = new RankEdits();
        self::assertTrue($edits->isOrderTerminator($tokens[0]));
        self::assertFalse($edits->isOrderTerminator($tokens[1]));
    }

    public function testIsOrderSuffixAcceptsDirectionsSeparatorsAndFollowingClauses(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('ASC , LIMIT score', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        $edits = new RankEdits();
        self::assertTrue($edits->isOrderSuffix($tokens[0]));
        self::assertTrue($edits->isOrderSuffix($tokens[1]));
        self::assertTrue($edits->isOrderSuffix($tokens[2]));
        self::assertFalse($edits->isOrderSuffix($tokens[3]));
    }

}
