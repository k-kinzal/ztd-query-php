<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Replication\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Replication\Publication as Operand;

#[CoversClass(Operand\PublicationOptions::class)]
#[Medium]
final class PublicationOptionsTest extends TestCase
{
    public function testIsEmptyOnlyWithoutOptions(): void
    {
        self::assertTrue((new Operand\PublicationOptions())->isEmpty());
        self::assertFalse((new Operand\PublicationOptions([]))->isEmpty());
        self::assertFalse((new Operand\PublicationOptions(null, false))->isEmpty());
    }
}
