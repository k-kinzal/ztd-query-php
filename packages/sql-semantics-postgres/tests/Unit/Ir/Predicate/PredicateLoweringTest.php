<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(PredicateLowering::class)]
#[Small]
final class PredicateLoweringTest extends TestCase
{
    public function testLowerResolvesSignedPredicates(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 WHERE (1 < 2)');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $bound = $operation->binding();

        $predicate = (new PredicateLowering())->lower($select->where, new SourceBinding($bound, null), true);
        self::assertInstanceOf(IntegerComparison::class, $predicate);
        self::assertSame('<', $predicate->operator->value);
    }
}
