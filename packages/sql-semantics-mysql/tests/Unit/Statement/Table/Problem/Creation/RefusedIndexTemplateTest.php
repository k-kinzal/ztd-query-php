<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem\Creation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(M\Table\Problem\Creation\RefusedIndexTemplate::class)]
#[Medium]
final class RefusedIndexTemplateTest extends TestCase
{
    public function testMessageRetainsTheExactCompleteIndexInstruction(): void
    {
        $definition = new M\Routine\Program\Write\Index\DeferredCreateIndex(new Name('i'), new QualifiedName(new Name('t')), [new M\Table\Key\Deferred\ExpressionPart(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]))]);
        $problem = new M\Table\Problem\Creation\RefusedIndexTemplate($definition);
        self::assertSame($definition, $problem->definition);
        self::assertSame('The index retains an operand with an invocation-time condition.', $problem->message());
    }
}
