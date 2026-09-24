<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\TextSearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch\TextSearchInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(TextSearchInvariant::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TextSearchInvariantTest extends TestCase
{
    public function testTokenTypesRejectsAnEmptyName(): void
    {
        TextSearchInvariant::tokenTypes(['word']);
        $this->expectException(InvalidStructure::class);
        TextSearchInvariant::tokenTypes(['word', '']);
    }
}
