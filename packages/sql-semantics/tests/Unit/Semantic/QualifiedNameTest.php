<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Semantic\Name;
use SqlSemantics\Semantic\QualifiedName;

#[\PHPUnit\Framework\Attributes\CoversClass(QualifiedName::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class QualifiedNameTest extends TestCase
{
    public function testToStringKeepsNamespaceSeparateFromTheObjectName(): void
    {
        self::assertSame('"a.b".c', (new QualifiedName(new Name('c'), new Name('a.b', '"')))->toString());
    }
}
