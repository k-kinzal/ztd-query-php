<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Inspection\ExplainProgram;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Validation\Failure\InvalidConstruction;
use SqlSemantics\Statement\Validation\ValueGraph;
use stdClass;

#[CoversClass(ValueGraph::class)]
#[Small]
final class ValueGraphTest extends TestCase
{
    public function testAcceptsRejectsForeignObjects(): void
    {
        $audit = new ValueGraph();
        self::assertTrue($audit->accepts(new Name('valid')));
        self::assertFalse($audit->accepts(new stdClass()));
    }

    public function testUnregisteredOperationIsRejectedBeforeItsWriterCanRun(): void
    {
        $foreign = new class () implements Operation {
            /** @throws LogicException */
            public function toString(): string
            {
                throw new LogicException('Unregistered behavior was called.');
            }
        };
        $this->expectException(InvalidConstruction::class);
        new ExplainProgram($foreign);
    }
}
