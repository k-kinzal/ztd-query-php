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
    public function testErrorPreservesTheResolvedIndexCondition(): void
    {
        $errors = new IndexShapes();
        $part = new M\Table\Key\ExpressionPart(new M\Literal\NumberLiteral('1'));
        self::assertSame(3759, $errors->error(new M\Table\Problem\Creation\FunctionalIndexKind(M\Table\Key\IndexKind::FullText, $part))?->getCode());
        self::assertSame(1070, $errors->error(new M\Table\Problem\Creation\SpatialKeyArity($part))?->getCode());
        self::assertSame("Incorrect index name 'PRIMARY'", $errors->error(new M\Table\Problem\Creation\InvalidIndexName(new Name('PRIMARY')))?->getMessage());
        self::assertNull($errors->error(new M\Table\Problem\NoColumns()));
    }

    public function testDefinitionUsesTheBoundOperandOwner(): void
    {
        $parts = [new M\Table\Key\ColumnPart(new Name('a')), new M\Table\Key\ColumnPart(new Name('b'))];
        $definition = new M\Table\CreateIndex(new Name('i'), new QualifiedName(new Name('t')), $parts, M\Table\Key\IndexKind::Spatial);
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $prepared = (new \MySqlMemory\Program\Definition\IndexUse())->header($definition, $session);
        self::assertInstanceOf(M\Table\CreateIndex::class, $prepared->statement);
        $index = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Spatial, $prepared->statement->parts);
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
