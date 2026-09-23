<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Modifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Modifier\IdentifierParameter;

#[CoversClass(IdentifierParameter::class)]
#[Medium]
final class IdentifierParameterTest extends TestCase
{
    #[TestWith([''])]
    #[TestWith(["x\0y"])]
    public function testRejectsAnInvalidIdentifier(string $name): void
    {
        $this->expectException(InvalidStructure::class);
        new IdentifierParameter($name);
    }

}
