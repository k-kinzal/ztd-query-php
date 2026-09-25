<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Extension\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Extension\Model\QueryOutput;

#[CoversClass(QueryOutput::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\Domain::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Core\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Core\Type\TypeShape::class)]
final class QueryOutputTest extends TestCase
{
    public function testKeepsTheModelContractInputs(): void
    {
        $sql = \SqlCatalog\Core\Evaluation\Domain::unknown('table');
        $bindings = \SqlCatalog\Core\Evaluation\Domain::literal(null);
        $output = new QueryOutput($sql, $bindings, true, true);
        self::assertSame($sql, $output->sql);
        self::assertSame($bindings, $output->bindings);
        self::assertTrue($output->truncated);
        self::assertTrue($output->combined);
        self::assertFalse((new QueryOutput($sql, $bindings))->truncated);
        self::assertFalse((new QueryOutput($sql, $bindings))->combined);
    }
}
