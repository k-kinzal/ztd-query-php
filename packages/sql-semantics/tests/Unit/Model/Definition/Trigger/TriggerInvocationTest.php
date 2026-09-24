<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Trigger\TriggerInvocation;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(TriggerInvocation::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TriggerInvocationTest extends TestCase
{
    public function testAnInvocationRetainsItsFunctionAndArguments(): void
    {
        $invocation = new TriggerInvocation(new QualifiedName(['audit', 'log']), ['1', 'x']);
        self::assertSame(['audit', 'log'], $invocation->function->parts);
        self::assertSame(['1', 'x'], $invocation->arguments);
    }

    public function testAFunctionNameHasAtMostThreeParts(): void
    {
        $this->expectException(InvalidStructure::class);
        new TriggerInvocation(new QualifiedName(['a', 'b', 'c', 'd']));
    }
}
