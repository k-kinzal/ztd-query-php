<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\OccurrenceScope;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

#[CoversClass(OccurrenceScope::class)]
#[Small]
final class OccurrenceScopeTest extends TestCase
{
    public function testPredicateRejectsAValidColumnNumberAtAnInvisibleOccurrence(): void
    {
        $table = new TableSource(new Identifier('public'), new Identifier('a'), [new Identifier('id')]);
        $outside = new IntegerComparison(IntegerOperator::from('='), new IntegerLiteral('1'), new ColumnReference(0, 1));
        $this->expectExceptionMessage('must belong to a visible input occurrence');

        (new OccurrenceScope())->predicate($outside, [$table]);
    }
}
