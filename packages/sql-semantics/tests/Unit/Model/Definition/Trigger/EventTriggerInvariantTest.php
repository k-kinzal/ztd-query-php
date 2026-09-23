<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Definition\Trigger\EventTriggerInvariant::class)]
#[Medium]
final class EventTriggerInvariantTest extends TestCase
{
    #[TestWith([Dialect::MySql, 'audit'])]
    #[TestWith([Dialect::Sqlite, 'audit'])]
    #[TestWith([Dialect::PostgreSql, ''])]
    public function testTargetRejectsAnIncompatibleIdentity(Dialect $dialect, string $name): void
    {
        $origin = (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        \SqlSemantics\Model\Definition\Trigger\EventTriggerInvariant::target($origin, $name);
    }

}
