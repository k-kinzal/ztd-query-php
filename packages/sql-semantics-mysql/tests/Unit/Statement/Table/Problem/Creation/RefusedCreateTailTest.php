<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem\Creation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTail;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RefusedCreateTail::class)]
#[Small]
final class RefusedCreateTailTest extends TestCase
{
    public function testMessageRetainsTheCompletedHeaderBeforeTheTailCondition(): void
    {
        $name = new QualifiedName(new Name('t'));
        $engine = new EngineOption(new Name('missing'));
        $cause = new OperandColumns(1, 2);
        $problem = new RefusedCreateTail($name, [], [$engine], 1, true, $cause);

        self::assertSame('Operand should contain 1 column(s), not 2.', $problem->message());
        self::assertSame($cause, $problem->problem);
        self::assertSame($name, $problem->name);
        self::assertSame([$engine], $problem->options);
        self::assertSame(1, $problem->temporaryWords);
        self::assertTrue($problem->ifNotExists);
    }
}
