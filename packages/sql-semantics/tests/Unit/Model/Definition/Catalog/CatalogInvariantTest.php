<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Catalog;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(Catalog\CatalogInvariant::class)]
#[Medium]
final class CatalogInvariantTest extends TestCase
{
    public function testNameAcceptsComponentsWithinTheDepth(): void
    {
        Catalog\CatalogInvariant::name(new QualifiedName(['app', 'money']), 2);
        $this->expectException(InvalidStructure::class);
        Catalog\CatalogInvariant::name(new QualifiedName(['db', 'app', 'money']), 2);
    }

    public function testIdentifierRejectsAnEmptyName(): void
    {
        Catalog\CatalogInvariant::identifier('x');
        $this->expectException(InvalidStructure::class);
        Catalog\CatalogInvariant::identifier('');
    }
}
