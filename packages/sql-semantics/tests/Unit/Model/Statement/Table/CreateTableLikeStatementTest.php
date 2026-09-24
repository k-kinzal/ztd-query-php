<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Table\CreateTableLikeStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateTableLikeStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class CreateTableLikeStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testWithOriginRetainsTheRequiredCopySource(string $version): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE original(id INTEGER)');
        $binder = new Binder($schema);
        $statement = $binder->bind('CREATE TEMPORARY TABLE IF NOT EXISTS copied LIKE original');
        self::assertInstanceOf(CreateTableLikeStatement::class, $statement);
        self::assertSame(['copied'], $statement->target->parts);
        self::assertSame($schema->tables[0], $statement->template->declaration);
        self::assertTrue($statement->temporary);
        self::assertTrue($statement->ifNotExists);
        self::assertSame('CREATE TEMPORARY TABLE IF NOT EXISTS `copied` LIKE `original`', $statement->toString());
        self::assertSame($statement->template, $statement->withOrigin($statement->origin)->template);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
        self::assertCount(1, $schema->tables);
    }

    public function testWithTargetChangesOnlyTheDestinationIdentity(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE original(id INTEGER)');
        $statement = (new Binder($schema))->bind('CREATE TABLE copied LIKE original');
        self::assertInstanceOf(CreateTableLikeStatement::class, $statement);
        $changed = $statement->withTarget(new QualifiedName(['archive','copy']));
        self::assertSame(['copied'], $statement->target->parts);
        self::assertSame(['archive','copy'], $changed->target->parts);
        self::assertSame('original', $changed->template->declaration->name);
        self::assertSame('CREATE TABLE `archive`.`copy` LIKE `original`', $changed->toString());
    }

    public function testWithTemplateRebindsTheRequiredSourceDefinition(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE original(id INTEGER)', 'CREATE TABLE replacement(label TEXT)');
        $binder = new Binder($schema);
        $original = $binder->bind('CREATE TABLE copied LIKE original');
        $replacement = $binder->bind('CREATE TABLE copied LIKE replacement');
        self::assertInstanceOf(CreateTableLikeStatement::class, $original);
        self::assertInstanceOf(CreateTableLikeStatement::class, $replacement);
        $changed = $original->withTemplate($replacement->template);
        self::assertSame('id', $original->template->declaration->columns[0]->name);
        self::assertSame('label', $changed->template->declaration->columns[0]->name);
        self::assertSame('CREATE TABLE `copied` LIKE `replacement`', $changed->toString());
    }

    public function testDefaultsToAPermanentUnguardedTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE original(id INTEGER)')))->bind('CREATE TABLE copied LIKE original');
        self::assertInstanceOf(CreateTableLikeStatement::class, $statement);
        $copy = new CreateTableLikeStatement($statement->origin, $statement->target, $statement->template);
        self::assertFalse($copy->temporary);
        self::assertFalse($copy->ifNotExists);
        self::assertSame('CREATE TABLE `copied` LIKE `original`', $copy->toString());
    }

    public function testRejectsAThreePartTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE original(id INTEGER)')))->bind('CREATE TABLE copied LIKE original');
        self::assertInstanceOf(CreateTableLikeStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new CreateTableLikeStatement($statement->origin, new QualifiedName(['a', 'b', 'c']), $statement->template);
    }
}
