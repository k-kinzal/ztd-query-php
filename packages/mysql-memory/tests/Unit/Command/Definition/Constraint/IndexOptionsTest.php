<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Constraint\IndexOptions;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexUsing;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexVisibility;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(IndexOptions::class)]
#[Small]
final class IndexOptionsTest extends TestCase
{
    public function testAlgorithmRetainsTheLastSupportedUsingOption(): void
    {
        $index = new IndexDefinition(IndexKind::Index, [new ColumnPart(new Name('a'))], algorithm: IndexAlgorithm::Btree, options: [new IndexUsing(IndexAlgorithm::Hash)]);
        $options = new IndexOptions();

        self::assertSame(IndexAlgorithm::Hash, $options->algorithm($index, 'MEMORY'));
        self::assertNull($options->algorithm($index, 'InnoDB'));
        self::assertSame(IndexAlgorithm::Btree, $options->algorithm(new IndexDefinition(IndexKind::Index, $index->parts, algorithm: IndexAlgorithm::Btree), 'MEMORY'));
    }

    public function testWarnRecordsTheFallbackAfterCreatingTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT,KEY k(a) USING HASH)');
        $result = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);

        self::assertSame([['Note', '3502', 'This storage engine does not support the HASH index algorithm, storage engine default was used instead.']], $result->rows);
    }

    public function testPrepareReportsOnceBeforeUnresolvedColumnsAreRejected(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $operation = $session->analyze('CREATE PROCEDURE p() CREATE TABLE t(a INT,KEY k(missing) USING HASH)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        $definition = $operation->body;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable::class, $definition);
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $options = new IndexOptions();
        $options->prepare($definition, $session, $context);
        $options->prepare($definition, $session, $context);
        $result = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);

        self::assertSame([['Note', '3502', 'This storage engine does not support the HASH index algorithm, storage engine default was used instead.']], $result->rows);
    }

    public function testVisibleUsesTheLastWrittenOption(): void
    {
        $rules = new IndexOptions();
        $part = new ColumnPart(new Name('a'));

        self::assertTrue($rules->visible(new IndexDefinition(IndexKind::Index, [$part])));
        self::assertFalse($rules->visible(new IndexDefinition(IndexKind::Index, [$part], options: [new IndexVisibility(true), new IndexVisibility(false)])));
        self::assertTrue($rules->visible(new IndexDefinition(IndexKind::Index, [$part], options: [new IndexVisibility(false), new IndexVisibility(true)])));
    }

    public function testChangeReplacesVisibilityWithoutChangingTheIndexIdentity(): void
    {
        $index = new IndexDefinition(IndexKind::Unique, [new ColumnPart(new Name('a'))], new ColumnName(new Name('k')), options: [new IndexVisibility(false), new IndexVisibility(true)]);
        $changed = (new IndexOptions())->change($index, false);

        self::assertSame($index->parts, $changed->parts);
        self::assertSame($index->name, $changed->name);
        self::assertSame(IndexKind::Unique, $changed->kind);
        self::assertEquals([new IndexVisibility(false)], $changed->options);
    }

    public function testCheckRejectsHidingTheImplicitPrimaryKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT NOT NULL,UNIQUE KEY k(a))');
        $this->expectExceptionMessage('A primary key index cannot be invisible');

        $session->query('ALTER TABLE t ALTER INDEX k INVISIBLE');
    }
    public function testMisorderedAnswersTrueForAnOrderedFullTextKeyPart(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $statementAnalysis = $session->analyze('CREATE FULLTEXT INDEX i ON t (a ASC)');
        $statement = $statementAnalysis;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateIndex::class, $statement);
        $index = (new \MySqlMemory\Command\Definition\AlterTableCommand())->indexes((new \MySqlMemory\Command\Definition\AlterTableCommand())->request($statement)[1])[0];

        self::assertTrue((new IndexOptions())->misordered($index));
    }

    public function testMisorderedAnswersFalseForAnOrderedBTreeKeyPart(): void
    {
        $session = (new Instance())->connect();
        $statementAnalysis = $session->analyze('CREATE INDEX i ON t (a DESC)');
        $statement = $statementAnalysis;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\CreateIndex::class, $statement);
        $index = (new \MySqlMemory\Command\Definition\AlterTableCommand())->indexes((new \MySqlMemory\Command\Definition\AlterTableCommand())->request($statement)[1])[0];

        self::assertFalse((new IndexOptions())->misordered($index));
    }

}
