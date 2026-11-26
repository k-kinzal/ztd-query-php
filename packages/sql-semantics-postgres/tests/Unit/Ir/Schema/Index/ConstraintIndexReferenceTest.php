<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ConstraintIndexReference;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexIndex;

#[CoversClass(ConstraintIndexReference::class)]
#[Small]
final class ConstraintIndexReferenceTest extends TestCase
{
    public function testQuotedUsesTheSchemaAndConstraintIndexName(): void
    {
        $target = new ConstraintIndexReference(new Identifier('app'), new Identifier('order"k'), new Identifier('orders'));

        self::assertSame('"app"."order""k"', $target->quoted());
        self::assertSame('REINDEX INDEX "app"."order""k"', (new ReindexIndex($target))->toString());
    }
}
