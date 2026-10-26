<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem\Definition;

use MySqlMemory\Session\Problem\Definition\IndexNames;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(IndexNames::class)]
#[Medium]
final class IndexNamesTest extends TestCase
{
    public function testCheckUsesTheBoundNameCondition(): void
    {
        $name = new Name('PRIMARY');
        $definition = new M\Table\CreateIndex($name, new QualifiedName(new Name('t')), [new M\Table\Key\ColumnPart(new Name('a'))]);
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $prepared = (new \MySqlMemory\Program\Definition\IndexUse())->header($definition, $session);
        $this->expectExceptionCode(1280);
        $this->expectExceptionMessage("Incorrect index name 'PRIMARY'");
        (new IndexNames())->check($prepared->facts);
    }

    public function testDefinitionSelectsTheExactNameOccurrence(): void
    {
        $name = new Name('PRIMARY');
        $definition = new M\Table\CreateIndex($name, new QualifiedName(new Name('t')), [new M\Table\Key\ColumnPart(new Name('a'))]);
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $prepared = (new \MySqlMemory\Program\Definition\IndexUse())->header($definition, $session);
        $index = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Index, $definition->parts, new M\Name\ColumnName($name));
        $this->expectExceptionCode(1280);
        $this->expectExceptionMessage("Incorrect index name 'PRIMARY'");
        (new IndexNames())->definition($index, $prepared->facts);
    }
    public function testAlterOrdersMissingColumnsBeforeLaterNameConditions(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $this->expectExceptionCode(1072);
        $this->expectExceptionMessage("Key column 'missing' doesn't exist in table");
        $session->query('ALTER TABLE t ADD KEY ordinary(missing), ADD KEY `PRIMARY`(a)');
    }

    public function testCreationOrdersKeysAgainstQuerySuppliedColumns(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $this->expectExceptionCode(1072);
        $this->expectExceptionMessage("Key column 'missing' doesn't exist in table");
        $session->query('CREATE TABLE fresh(KEY ordinary(missing),KEY `PRIMARY`(a)) AS SELECT 1 AS a');
    }

    public function testColumnsPreservesTheEarlierNameCondition(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $this->expectExceptionCode(1280);
        $this->expectExceptionMessage("Incorrect index name 'PRIMARY'");
        $session->query('CREATE TABLE fresh(KEY `PRIMARY`(a),KEY ordinary(missing)) AS SELECT 1 AS a');
    }

}
