<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem\Definition;

use MySqlMemory\Session\Problem\Definition\IndexShapes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(IndexShapes::class)]
#[Medium]
final class IndexShapesTest extends TestCase
{
    public function testDefinitionUsesTheBoundOperandOwner(): void
    {
        $parts = [new M\Table\Key\ColumnPart(new Name('a')), new M\Table\Key\ColumnPart(new Name('b'))];
        $definition = new M\Routine\Program\Write\Index\DeferredCreateIndex(new Name('i'), new QualifiedName(new Name('t')), $parts, M\Table\Key\IndexKind::Spatial);
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $prepared = (new \MySqlMemory\Program\Definition\IndexUse())->header($definition, $session);
        $index = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Spatial, $parts);
        $this->expectExceptionCode(1070);
        $this->expectExceptionMessage('Too many key parts specified; max 1 parts allowed');
        (new IndexShapes())->definition($index, $prepared->facts, GrammarRelease::MySql847, 'InnoDB');
    }

    public function testPreparePreservesSpatialArityBeforeAnUnknownColumn(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $this->expectExceptionCode(1070);
        $this->expectExceptionMessage('Too many key parts specified; max 1 parts allowed');
        $session->query('CREATE TABLE t(a INT,SPATIAL k(missing,a))');
    }

    public function testRequestUsesTheDestinationStorageCapability(): void
    {
        $session = (new \MySqlMemory\Instance('5.6.51', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT,b INT) ENGINE=MyISAM');
        $this->expectExceptionCode(1210);
        $this->expectExceptionMessage('Incorrect arguments to SPATIAL INDEX');
        $session->query('CREATE SPATIAL INDEX k ON t(a,b)');
    }
}
