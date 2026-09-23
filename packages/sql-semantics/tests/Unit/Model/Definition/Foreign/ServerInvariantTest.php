<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\ServerInvariant;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ServerInvariant::class)]
#[Medium]
final class ServerInvariantTest extends TestCase
{
    #[TestWith([Dialect::MySql, 'remote'])]
    #[TestWith([Dialect::PostgreSql, ''])]
    public function testTargetRejectsInvalidIdentity(Dialect $dialect, string $name): void
    {
        $origin = (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        ServerInvariant::target($origin, $name);
    }

    #[TestWith([Dialect::MySql, 'v1'])]
    #[TestWith([Dialect::PostgreSql, 1])]
    public function testTextRejectsAnIncompatibleLiteral(Dialect $dialect, string|int $input): void
    {
        $value = Expression::literal($input, $dialect);
        self::assertInstanceOf(Literal::class, $value);
        $this->expectException(InvalidStructure::class);
        ServerInvariant::text($value);
    }
}
