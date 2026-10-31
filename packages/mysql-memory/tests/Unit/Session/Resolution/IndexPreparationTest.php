<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(\MySqlMemory\Session\Resolution\IndexPreparation::class)]
#[Medium]
final class IndexPreparationTest extends TestCase
{
    public function testRetainsTheExactInstructionAndDerivedFacts(): void
    {
        $definition = new M\Table\CreateIndex(new Name('i'), new QualifiedName(new Name('t')), [new M\Table\Key\Deferred\ExpressionPart(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]))]);
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $bound = (new \MySqlMemory\Program\Definition\IndexUse())->header($definition, $session);
        $prepared = new \MySqlMemory\Session\Resolution\IndexPreparation($definition, $bound->facts);
        self::assertSame($definition, $prepared->statement);
        self::assertSame($bound->facts, $prepared->facts);
    }
}
