<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(\MySqlMemory\Program\Definition\IndexUse::class)]
#[Medium]
final class IndexUseTest extends TestCase
{
    public function testHeaderBindsTheSameInstructionWithoutRequiringItsTarget(): void
    {
        $definition = new M\Routine\Program\Write\Index\DeferredCreateIndex(new Name('i'), new QualifiedName(new Name('t')), [new M\Table\Key\Deferred\ExpressionPart(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]))]);
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $prepared = (new \MySqlMemory\Program\Definition\IndexUse())->header($definition, $session);
        self::assertSame($definition, $prepared->statement);
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\MissingTable::class, $prepared->facts->relation($definition)->table);
    }

    public function testRaiseChecksThePopulatedTargetBeforeItsRetainedKeyRole(): void
    {
        $definition = new M\Routine\Program\Write\Index\DeferredCreateIndex(new Name('i'), new QualifiedName(new Name('t')), [new M\Table\Key\Deferred\ExpressionPart(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]))]);
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); INSERT INTO t VALUES(7)');
        $session->text = 'not executable SQL';
        $this->expectExceptionCode(3800);
        $this->expectExceptionMessage("Expression of functional index 'i' cannot refer to a row value.");
        (new \MySqlMemory\Program\Definition\IndexUse())->raise($definition, $session);
    }
}
