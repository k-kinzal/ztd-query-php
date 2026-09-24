<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Replication\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Replication\Publication as Operand;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(Operand\PublishedSchema::class)]
#[Medium]
final class PublishedSchemaTest extends TestCase
{
    public function testASchemaRequiresAName(): void
    {
        self::assertSame('s', (new Operand\PublishedSchema('s'))->name);
        $this->expectException(InvalidStructure::class);
        new Operand\PublishedSchema('');
    }
}
