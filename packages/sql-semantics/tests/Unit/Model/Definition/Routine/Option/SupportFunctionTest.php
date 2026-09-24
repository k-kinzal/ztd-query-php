<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Routine\Option\SupportFunction;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(SupportFunction::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\Medium]
final class SupportFunctionTest extends TestCase
{
    public function testRetainsTheFunctionName(): void
    {
        self::assertSame(['db', 'app', 'f'], (new SupportFunction(new QualifiedName(['db', 'app', 'f'])))->function->parts);
    }

    public function testRejectsFourComponents(): void
    {
        $this->expectException(InvalidStructure::class);
        new SupportFunction(new QualifiedName(['a', 'b', 'c', 'd']));
    }
}
