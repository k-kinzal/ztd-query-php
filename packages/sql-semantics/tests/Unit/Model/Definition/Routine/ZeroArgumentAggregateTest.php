<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Relation\QualifiedName;

#[CoversClass(Routine\ZeroArgumentAggregate::class)]
final class ZeroArgumentAggregateTest extends TestCase
{
    public function testIdentityRetainsTheIdentifierPath(): void
    {
        $name = new QualifiedName(['app', 'f']);
        $target = new Routine\ZeroArgumentAggregate($name);
        self::assertSame($name, $target->name);
    }

}
