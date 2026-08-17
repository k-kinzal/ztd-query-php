<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\ForeignKeyDefinitionParser;
use ZtdQuery\Schema\ForeignKeyDefinition;
use ZtdQuery\Schema\ReferentialAction;
use ZtdQuery\Sql\SqlTokenDialect;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(ForeignKeyDefinitionParser::class)]
#[UsesClass(ForeignKeyDefinition::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(SqlTokenStream::class)]
final class ForeignKeyDefinitionParserTest extends TestCase
{
    public function testParsesNamedCompositeConstraintWithoutRegexSubstitution(): void
    {
        $foreignKeys = (new ForeignKeyDefinitionParser())->parseCreateTable(
            'CREATE TABLE child (id INT, tenant_id INT, parent_id INT, '
            . 'CONSTRAINT "fk child parent" FOREIGN KEY (tenant_id, parent_id) '
            . 'REFERENCES app.parents (tenant_id, id) ON UPDATE CASCADE ON DELETE SET NULL)',
        );

        self::assertSame(['fk child parent'], array_keys($foreignKeys));
        self::assertSame(['tenant_id', 'parent_id'], $foreignKeys['fk child parent']->columns);
        self::assertSame('parents', $foreignKeys['fk child parent']->referencedTable);
        self::assertSame(['tenant_id', 'id'], $foreignKeys['fk child parent']->referencedColumns);
        self::assertSame(ReferentialAction::SetNull, $foreignKeys['fk child parent']->onDelete);
        self::assertSame(ReferentialAction::Cascade, $foreignKeys['fk child parent']->onUpdate);
    }

    public function testParsesInlineAndMySqlQuotedReferences(): void
    {
        $foreignKeys = (new ForeignKeyDefinitionParser())->parseCreateTable(
            'CREATE TABLE `child` (`id` INT, `parent_id` INT REFERENCES `app`.`parents` (`id`) ON DELETE RESTRICT)',
            SqlTokenDialect::MySql,
        );

        self::assertCount(1, $foreignKeys);
        $foreignKey = array_values($foreignKeys)[0];
        self::assertSame(['parent_id'], $foreignKey->columns);
        self::assertSame('parents', $foreignKey->referencedTable);
        self::assertSame(['id'], $foreignKey->referencedColumns);
        self::assertSame(ReferentialAction::Restrict, $foreignKey->onDelete);
        self::assertSame(ReferentialAction::NoAction, $foreignKey->onUpdate);
    }

    public function testUsesReferencedPrimaryKeyWhenColumnListIsOmitted(): void
    {
        $foreignKeys = (new ForeignKeyDefinitionParser())->parseCreateTable(
            'CREATE TABLE child (parent_id INTEGER REFERENCES parent ON DELETE CASCADE)',
        );

        self::assertSame([], array_values($foreignKeys)[0]->referencedColumns);
        self::assertSame(ReferentialAction::Cascade, array_values($foreignKeys)[0]->onDelete);
    }

    public function testRejectsStatementsAndMalformedReferencesWithoutTableBody(): void
    {
        $parser = new ForeignKeyDefinitionParser();

        self::assertSame([], $parser->parseCreateTable('CREATE TABLE child AS SELECT 1'));
        self::assertSame([], $parser->parseCreateTable('CREATE TABLE child (id INT, FOREIGN KEY REFERENCES parent(id))'));
    }
}
