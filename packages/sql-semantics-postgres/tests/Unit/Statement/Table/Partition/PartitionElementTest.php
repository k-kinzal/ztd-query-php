<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\PartitionElement::class)]
#[Medium]
final class PartitionElementTest extends TestCase
{
    public function testDeriveClauseResolvesTheKey(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE TABLE n (a int) PARTITION BY RANGE (zz)', $context);
        self::assertSame([
          0 => 'Column zz does not exist.',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n (a text) PARTITION BY LIST (a COLLATE "C" text_ops, (a || \'x\'), lower(a))', []);
        self::assertSame('CREATE TABLE n (a text) PARTITION BY list (a COLLATE "C" text_ops, (a || \'x\'), lower(a))', $statement->toString());
    }
}
