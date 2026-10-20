<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(\MySqlMemory\Session\Parse\Definition\IndexInput::class)]
#[Medium]
final class IndexInputTest extends TestCase
{
    public function testRefusedReportsTargetLookupBeforeTheRetainedOperand(): void
    {
        $definition = new M\Routine\Program\Write\Index\DeferredCreateIndex(new Name('i'), new QualifiedName(new Name('t')), [new M\Table\Key\Deferred\ExpressionPart(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]))]);
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $problem = new M\Table\Problem\Creation\RefusedIndexTemplate($definition);
        $failure = new \SqlSemantics\Construction\RejectedInput($problem, 0, false);
        $input = new \MySqlMemory\Session\Parse\Definition\IndexInput();
        $missing = $input->refused($failure, 'CREATE INDEX i ON t ((ROW(1,2)))', $session);
        self::assertSame(1146, $missing->getCode());
        $session->query('CREATE TABLE t(a INT); INSERT INTO t VALUES(7)');
        $reached = $input->refused($failure, 'CREATE INDEX i ON t ((ROW(1,2)))', $session);
        self::assertSame(3800, $reached->getCode());
        self::assertSame($definition, $problem->definition);
    }
}
