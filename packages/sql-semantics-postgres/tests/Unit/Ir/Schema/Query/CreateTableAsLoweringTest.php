<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Query\CreateTableAs;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Query\CreateTableAsLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(CreateTableAsLowering::class)]
#[Medium]
final class CreateTableAsLoweringTest extends TestCase
{
    public function testLowerResolvesTheTargetAndTheUnspecifiedHeadingSuffix(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE public.accounts (id integer, checked boolean)']);
        $result = $semantics->analyze('CREATE UNLOGGED TABLE shadow (account) AS SELECT id, checked FROM accounts', $context);

        self::assertInstanceOf(CreateTableAs::class, $result);
        self::assertSame('public', $result->target->schema->value);
        self::assertSame(['account', 'checked'], array_column($result->columns, 'value'));
        self::assertTrue($result->unlogged);
        self::assertTrue($result->withData);
    }
}
