<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Platform\MySql\MySqlSelectRelationParser;

#[CoversClass(MySqlSelectRelationParser::class)]
#[UsesClass(MySqlLexerProfile::class)]
final class MySqlSelectRelationParserTest extends TestCase
{
    public function testFindsPhysicalRelationsAcrossMySqlSelectScopes(): void
    {
        $sql = 'SELECT * FROM (SELECT * FROM app.`users` UNION ALL SELECT * FROM archived_users) p JOIN JSON_TABLE(p.data, \'$[*]\' COLUMNS(id INT PATH \'$.id\')) j ON TRUE JOIN audit_log a ON TRUE';

        self::assertSame(
            ['audit_log', 'users', 'archived_users'],
            (new MySqlSelectRelationParser())->tableNames($sql),
        );
    }

    public function testFromClausesUseMySqlBoundaries(): void
    {
        $parser = new MySqlSelectRelationParser();

        self::assertSame(
            ['users', 'archived'],
            $parser->fromClauses('SELECT * FROM users PROCEDURE ANALYSE(); SELECT * FROM archived LOCK IN SHARE MODE'),
        );
    }

    public function testReferencesPreserveOffsetsAndUnqualifyOnlyTargets(): void
    {
        $parser = new MySqlSelectRelationParser();
        $sql = 'SELECT * FROM app.`users` u JOIN logs.audit_log a ON TRUE';

        self::assertSame(['users', 'audit_log'], array_column($parser->references($sql), 'name'));
        self::assertSame(
            'SELECT * FROM `users` u JOIN logs.audit_log a ON TRUE',
            $parser->unqualify($sql, ['users']),
        );
    }

    public function testReferencesExposeExactQualifiedIdentifierOffsets(): void
    {
        $parser = new MySqlSelectRelationParser();
        $sql = 'SELECT * FROM app.`a``b` a JOIN users u ON TRUE JOIN logs.orders o ON TRUE';

        self::assertSame([
            [
                'name' => 'a`b',
                'start' => 14,
                'unqualifiedStart' => 18,
                'end' => 24,
            ],
            [
                'name' => 'users',
                'start' => 32,
                'unqualifiedStart' => 32,
                'end' => 37,
            ],
            [
                'name' => 'orders',
                'start' => 53,
                'unqualifiedStart' => 58,
                'end' => 64,
            ],
        ], $parser->references($sql));
    }

    public function testUnqualifiesMultipleTargetsCaseInsensitivelyFromRightToLeft(): void
    {
        $sql = 'SELECT * FROM users u JOIN app.Users u2 ON TRUE JOIN logs.orders o ON TRUE';

        self::assertSame(
            'SELECT * FROM users u JOIN Users u2 ON TRUE JOIN orders o ON TRUE',
            (new MySqlSelectRelationParser())->unqualify($sql, ['USERS', 'ORDERS']),
        );
    }

    public function testKeepsOneCaseInsensitiveRelationName(): void
    {
        self::assertSame(
            ['Users'],
            (new MySqlSelectRelationParser())->tableNames('SELECT * FROM Users JOIN users ON TRUE'),
        );
    }

    public function testSetOperatorsAndRepeatedFromCloseTheCurrentSelectScope(): void
    {
        $parser = new MySqlSelectRelationParser();
        $sql = 'FROM orphan; SELECT 1 UNION FROM union_hidden; SELECT 1 INTERSECT FROM intersect_hidden; SELECT 1 EXCEPT FROM except_hidden; SELECT * FROM visible';

        self::assertSame(['visible'], $parser->fromClauses($sql));
        self::assertSame(['users'], $parser->tableNames('SELECT * FROM users FROM ignored'));
        self::assertSame(['users FROM ignored'], $parser->fromClauses('SELECT * FROM users FROM ignored'));
        self::assertSame([], $parser->tableNames('FROM orphan'));
    }

    public function testFindsParenthesizedJoinedRelationsButNotDerivedProducers(): void
    {
        $parser = new MySqlSelectRelationParser();
        $groupedSql = 'SELECT * FROM (app.users JOIN logs.orders ON TRUE) grouped, extra';

        self::assertSame(
            ['users', 'orders', 'extra'],
            $parser->tableNames($groupedSql),
        );
        self::assertSame([
            ['name' => 'users', 'start' => 15, 'unqualifiedStart' => 19, 'end' => 24],
            ['name' => 'orders', 'start' => 30, 'unqualifiedStart' => 35, 'end' => 41],
            ['name' => 'extra', 'start' => 60, 'unqualifiedStart' => 60, 'end' => 65],
        ], $parser->references($groupedSql));
        self::assertSame([], $parser->tableNames('SELECT * FROM () empty_group'));
        self::assertSame(['extra'], $parser->tableNames('SELECT * FROM (), extra'));
        self::assertSame([], $parser->tableNames('SELECT * FROM (values (1)) produced'));
        self::assertSame([], $parser->tableNames('SELECT * FROM (VALUES rogue, leaked) produced'));
        self::assertSame([], $parser->tableNames('SELECT * FROM (WITH rogue, leaked) produced'));
        self::assertSame(
            ['users'],
            $parser->tableNames('SELECT * FROM (SELECT id, name FROM app.users) produced'),
        );
        self::assertSame(
            ['users', 'orders'],
            $parser->tableNames('SELECT * FROM users, orders'),
        );
    }

    public function testNestedSelectFromClausesStopAtTheirOwnScopeBoundary(): void
    {
        $parser = new MySqlSelectRelationParser();

        self::assertSame(
            ['(SELECT * FROM inner_table WHERE id = 1) derived', 'inner_table'],
            $parser->fromClauses(
                'SELECT * FROM (SELECT * FROM inner_table WHERE id = 1) derived WHERE active = 1',
            ),
        );
        self::assertSame(
            ['(SELECT * FROM inner_table) derived', 'inner_table'],
            $parser->fromClauses('SELECT * FROM (SELECT * FROM inner_table) derived WHERE active = 1'),
        );
    }

    public function testParenthesizedRelationOffsetsDoNotIncludeFollowingAlias(): void
    {
        $sql = 'SELECT * FROM          (app.users) grouped, extra';

        self::assertSame([
            ['name' => 'users', 'start' => 24, 'unqualifiedStart' => 28, 'end' => 33],
            ['name' => 'extra', 'start' => 44, 'unqualifiedStart' => 44, 'end' => 49],
        ], (new MySqlSelectRelationParser())->references($sql));
    }

    public function testDoesNotTreatQuotedFunctionNameAsRelation(): void
    {
        self::assertSame(
            [],
            (new MySqlSelectRelationParser())->tableNames('SELECT * FROM `json_table`(payload, "$") j'),
        );
    }

    /** @param list<string> $expected */
    #[DataProvider('providerMySqlFromTerminators')]
    public function testStopsFromClauseAtEveryMySqlBoundary(string $suffix, array $expected): void
    {
        self::assertSame(
            $expected,
            (new MySqlSelectRelationParser())->fromClauses('SELECT * FROM users ' . $suffix),
        );
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function providerMySqlFromTerminators(): iterable
    {
        yield 'where' => ['WHERE active = 1', ['users']];
        yield 'group by' => ['GROUP BY id', ['users']];
        yield 'having' => ['HAVING count(*) > 1', ['users']];
        yield 'window' => ['WINDOW w AS ()', ['users']];
        yield 'order by' => ['ORDER BY id', ['users']];
        yield 'limit' => ['LIMIT 1', ['users']];
        yield 'procedure' => ['PROCEDURE ANALYSE()', ['users']];
        yield 'into' => ['INTO OUTFILE "/tmp/result"', ['users']];
        yield 'for' => ['FOR UPDATE', ['users']];
        yield 'lock' => ['LOCK IN SHARE MODE', ['users']];
        yield 'union' => ['UNION SELECT * FROM archived', ['users', 'archived']];
        yield 'intersect' => ['INTERSECT SELECT * FROM archived', ['users', 'archived']];
        yield 'except' => ['EXCEPT SELECT * FROM archived', ['users', 'archived']];
    }

    public function testRejectsNonMySqlSourceForms(): void
    {
        $parser = new MySqlSelectRelationParser();

        self::assertSame([], $parser->tableNames("SELECT * FROM 'literal'"));
        self::assertSame([], $parser->tableNames('SELECT * FROM VALUES (1)'));
        self::assertSame([], $parser->tableNames('SELECT * FROM +invalid'));
        self::assertSame([], $parser->tableNames('SELECT * FROM ``'));
        self::assertSame([], $parser->tableNames('SELECT * FROM `unterminated'));
        self::assertSame([], $parser->tableNames('SELECT * FROM SELECT'));
        self::assertSame([], $parser->tableNames('SELECT * FROM WITH'));
    }
}
