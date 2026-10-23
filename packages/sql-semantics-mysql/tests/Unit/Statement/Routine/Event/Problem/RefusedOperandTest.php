<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Event\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\RefusedOperand;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RefusedOperand::class)]
#[Small]
final class RefusedOperandTest extends TestCase
{
    public function testMessageKeepsTheOperandConditionSeparateFromTheHeader(): void
    {
        $name = new QualifiedName(new Name('e'), new Name('d'));
        $problem = new RefusedOperand($name, new \SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\OperandWidth(new OperandColumns(1, 3), \SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause::At, true));

        self::assertSame($name, $problem->name);
        self::assertSame('Operand should contain 1 column(s), not 3.', $problem->message());
    }
}
