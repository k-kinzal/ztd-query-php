<?php

declare(strict_types=1);

namespace Tests\Unit\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\MySqlAlterStatements;
use ZtdQuery\Platform\MySql\Mutation\AlterTableColumn;

#[CoversClass(AlterTableColumn::class)]
final class AlterTableColumnTest extends TestCase
{
    public function testNameInAnswersTheColumnTheOperationIsAbout(): void
    {
        self::assertSame('name', (new AlterTableColumn())->nameIn(MySqlAlterStatements::operationOn('DROP COLUMN name')));
    }

    public function testNameInTakesTheQuotingOffTheName(): void
    {
        self::assertSame('order', (new AlterTableColumn())->nameIn(MySqlAlterStatements::operationOn('DROP COLUMN `order`')));
    }

    public function testNameInIsNothingWhereTheOperationNamesNoColumn(): void
    {
        self::assertNull((new AlterTableColumn())->nameIn(MySqlAlterStatements::operationOn('DROP PRIMARY KEY')));
    }

    public function testWithoutQuotesAnswersTheNameTheTableKnows(): void
    {
        self::assertSame('order', (new AlterTableColumn())->withoutQuotes('`order`'));
    }

    public function testWithoutQuotesLeavesAnUnquotedNameAlone(): void
    {
        self::assertSame('name', (new AlterTableColumn())->withoutQuotes('name'));
    }

    public function testDefinitionInReadsTheColumnAnAddDeclares(): void
    {
        $definition = (new AlterTableColumn())->definitionIn(MySqlAlterStatements::operationOn('ADD COLUMN email VARCHAR(255)'));

        self::assertSame('email', $definition?->name);
    }

    public function testDefinitionInReadsHowTheColumnIsDeclared(): void
    {
        $definition = (new AlterTableColumn())->definitionIn(MySqlAlterStatements::operationOn('ADD COLUMN email VARCHAR(255) NOT NULL'));

        self::assertSame('VARCHAR', $definition?->type?->name);
    }

    public function testDefinitionInIsNothingWhereTheOperationNamesNoColumn(): void
    {
        self::assertNull((new AlterTableColumn())->definitionIn(MySqlAlterStatements::operationOn('DROP PRIMARY KEY')));
    }

    public function testRedefinitionInReadsTheNewNameAChangeWrites(): void
    {
        $definition = (new AlterTableColumn())->redefinitionIn(MySqlAlterStatements::operationOn('CHANGE name full_name VARCHAR(200)'));

        self::assertSame('full_name', $definition?->name);
    }

    public function testRedefinitionInIsNothingWhereTheOperationWritesNoDeclaration(): void
    {
        self::assertNull((new AlterTableColumn())->redefinitionIn(MySqlAlterStatements::operationOn('DROP PRIMARY KEY')));
    }

    public function testUnplacedTextAnswersWhatTheParserCouldNotPlace(): void
    {
        self::assertSame(
            'VARCHAR(255)',
            (new AlterTableColumn())->unplacedText(MySqlAlterStatements::operationOn('ADD COLUMN email VARCHAR(255)')),
        );
    }

    public function testUnplacedTextIsEmptyWhereTheParserPlacedEverything(): void
    {
        self::assertSame('', (new AlterTableColumn())->unplacedText(MySqlAlterStatements::operationOn('DROP COLUMN name')));
    }

    public function testMentionsUnsupportedReportsASpatialIndex(): void
    {
        self::assertTrue((new AlterTableColumn())->mentionsUnsupported(MySqlAlterStatements::operationOn('ADD SPATIAL INDEX idx (g)')));
    }

    public function testMentionsUnsupportedIsFalseForAnOrdinaryColumn(): void
    {
        self::assertFalse((new AlterTableColumn())->mentionsUnsupported(MySqlAlterStatements::operationOn('ADD COLUMN email VARCHAR(255)')));
    }

    public function testOptionIsSetReportsAnOptionTheStatementWrote(): void
    {
        $operation = MySqlAlterStatements::operationOn('ADD COLUMN email VARCHAR(255)');

        self::assertTrue((new AlterTableColumn())->optionIsSet($operation->options, 'ADD'));
    }

    public function testOptionIsSetIsFalseForAnOptionTheStatementDidNotWrite(): void
    {
        $operation = MySqlAlterStatements::operationOn('ADD COLUMN email VARCHAR(255)');

        self::assertFalse((new AlterTableColumn())->optionIsSet($operation->options, 'DROP'));
    }

    public function testFirstFieldOfReadsTheOneColumnADeclarationDeclares(): void
    {
        self::assertSame('id', (new AlterTableColumn())->firstFieldOf('CREATE TABLE t (`id` INT)')?->name);
    }

    public function testFirstFieldOfIsNothingWhereTheTextIsNotADeclaration(): void
    {
        self::assertNull((new AlterTableColumn())->firstFieldOf('SELECT 1'));
    }
}
