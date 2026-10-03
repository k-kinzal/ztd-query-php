<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnostic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\InvariantViolation;
use SqlSemantics\Lowering\Form;

#[CoversClass(InvariantViolation::class)]
#[Small]
final class InvariantViolationTest extends TestCase
{
    public function testAFailedInternalCheckIsReported(): void
    {
        $this->expectExceptionMessage('The production has no terminal at position 0: expr: term');

        (new Form(new Node('expr', 0, [new Node('term', 2, [])]), 'expr: term'))->token(0);
    }

    public function testTheMessageIsKept(): void
    {
        self::assertSame('Derivation left a node without facts.', (new InvariantViolation('Derivation left a node without facts.'))->getMessage());
    }
}
