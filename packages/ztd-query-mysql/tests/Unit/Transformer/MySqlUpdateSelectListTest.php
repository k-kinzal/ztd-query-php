<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Dialect\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\Dialect\MySqlLexerProfile;
use ZtdQuery\Platform\MySql\Transformer\MySqlUpdateSelectList;
use ZtdQuery\Shadow\Mutation\Row\MultiTableMutationTarget;

#[CoversClass(MySqlUpdateSelectList::class)]
#[UsesClass(MySqlIdentifierQuoter::class)]
#[UsesClass(MySqlLexerProfile::class)]
final class MySqlUpdateSelectListTest extends TestCase
{
    public function testMultiTableSelectColumnsCarriesTheKeyTheRowHadSeparately(): void
    {
        $selectList = new MySqlUpdateSelectList();
        $statement = (new Parser('UPDATE users AS u SET u.id = 2'))->statements[0];
        self::assertInstanceOf(UpdateStatement::class, $statement);
        $targets = [new MultiTableMutationTarget('users', ['id'], ['id'])];

        $columns = $selectList->multiTableSelectColumns($statement, ['users' => ['alias' => 'u']], $targets, ['2']);

        self::assertSame(
            ['2 AS `__ztd_multi_0_value_0`', '`u`.`id` AS `__ztd_multi_0_identity_0`'],
            $columns,
        );
    }

    public function testAssignmentsByTableReadsAQualifiedAssignmentAsBelongingToThatTable(): void
    {
        $selectList = new MySqlUpdateSelectList();
        $statement = (new Parser('UPDATE users AS u SET u.name = 1'))->statements[0];
        self::assertInstanceOf(UpdateStatement::class, $statement);

        self::assertSame(
            ['users' => ['name' => '1']],
            $selectList->assignmentsByTable($statement, ['users' => ['alias' => 'u']], ['1']),
        );
    }

    public function testAssignmentsByTableReadsABareAssignmentAsBelongingToTheFirstTable(): void
    {
        $selectList = new MySqlUpdateSelectList();
        $statement = (new Parser('UPDATE users AS u SET name = 1'))->statements[0];
        self::assertInstanceOf(UpdateStatement::class, $statement);

        self::assertSame(
            ['users' => ['name' => '1']],
            $selectList->assignmentsByTable($statement, ['users' => ['alias' => 'u']], ['1']),
        );
    }

    public function testUnquoteIdentifierTakesTheQuotingOffTheName(): void
    {
        self::assertSame('order', MySqlUpdateSelectList::unquoteIdentifier('`order`'));
    }

    public function testUnquoteIdentifierLeavesAnUnquotedNameAlone(): void
    {
        self::assertSame('name', MySqlUpdateSelectList::unquoteIdentifier('name'));
    }

    public function testSelectColumnsCarriesEveryColumnTheStatementDoesNotAssignAsItStands(): void
    {
        $statement = (new Parser('UPDATE users SET name = 1'))->statements[0];
        self::assertInstanceOf(UpdateStatement::class, $statement);

        $columns = (new MySqlUpdateSelectList())->selectColumns($statement, ['id', 'name'], ['id'], ['1'], 'users');

        self::assertSame(
            ['1 AS `name`', '`users`.`id`', '`users`.`id` AS `__ztd_original_id`'],
            $columns,
        );
    }

    public function testAssignedColumnAnswersTheColumnHoweverItWasQualified(): void
    {
        self::assertSame(
            ['name', 'name'],
            [MySqlUpdateSelectList::assignedColumn('`u`.`name`'), MySqlUpdateSelectList::assignedColumn('name')],
        );
    }
}
