<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Declaration\AtomicBody;
use SqlSemantics\Model\Definition\Routine\Declaration\BodyInvariant;
use SqlSemantics\Model\Definition\Routine\Declaration\LinkedBody;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(BodyInvariant::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\Medium]
final class BodyInvariantTest extends TestCase
{
    public function testTextRequiresAStringConstant(): void
    {
        $text = Expression::literal('x', Dialect::PostgreSql);
        $flag = Expression::literal(true, Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $text);
        self::assertInstanceOf(Literal::class, $flag);
        BodyInvariant::text($text);
        $this->expectException(InvalidStructure::class);
        BodyInvariant::text($flag);
    }

    public function testLanguageRequiresSqlForAnInlineBody(): void
    {
        BodyInvariant::language('sql', new AtomicBody([]));
        $this->expectException(InvalidStructure::class);
        BodyInvariant::language('SQL', new AtomicBody([]));
    }

    public function testLanguageRequiresCForALinkedBody(): void
    {
        $file = Expression::literal('lib', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $file);
        BodyInvariant::language('c', new LinkedBody($file, $file));
        $this->expectException(InvalidStructure::class);
        BodyInvariant::language('internal', new LinkedBody($file, $file));
    }

    public function testLanguageRequiresAName(): void
    {
        $this->expectException(InvalidStructure::class);
        BodyInvariant::language('', new AtomicBody([]));
    }
}
