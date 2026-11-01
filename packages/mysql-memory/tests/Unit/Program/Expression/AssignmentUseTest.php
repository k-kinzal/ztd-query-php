<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\MySqlMemory\Program\Expression\AssignmentUse::class)]
#[Medium]
final class AssignmentUseTest extends TestCase
{
    public function testRaiseResolvesTheOperandWithoutInputText(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $session->text = 'unrelated invalid input';
        $item = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignment(new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('x')), new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]));
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1241);
        (new \MySqlMemory\Program\Expression\AssignmentUse())->raise($item, $session);
    }

    public function testInheritedPreservesTheTextMeaningOfABareSystemValue(): void
    {
        $item = new \SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment(new \SqlSemantics\Statement\Identifier\Name('binlog_format'), new \SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName(new \SqlSemantics\Statement\Identifier\Name('STATEMENT'), system: false));
        $scoped = (new \MySqlMemory\Program\Expression\AssignmentUse())->inherited($item, \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Global);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment::class, $scoped);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName::class, $scoped->value);
        self::assertTrue($scoped->value->system);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Global, $scoped->scope);
    }
}
