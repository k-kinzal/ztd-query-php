<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceOption::class)]
#[Medium]
final class SequenceOptionTest extends TestCase
{
    public function testOptionsAreSequenceOptions(): void
    {
        $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
        $statement = $semantics->analyze('CREATE SEQUENCE s CYCLE', []);
        self::assertSame('CREATE SEQUENCE s CYCLE', $statement->toString());
    }
}
