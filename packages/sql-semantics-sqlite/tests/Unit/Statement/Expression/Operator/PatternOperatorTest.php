<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\PatternMatch;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\PatternOperator;
use SqlSemantics\Statement\Shape\Field;

#[CoversClass(PatternOperator::class)]
#[Medium]
final class PatternOperatorTest extends TestCase
{
    public function testEachOperatorIsReadFromItsSpelling(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze("SELECT a LIKE 'x', a glob 'x', a REGEXP 'x', a match 'x' FROM t");
        $operators = array_map(static fn (Field $field): ?PatternOperator => $field->expression instanceof PatternMatch ? $field->expression->operator : null, $query->fields()->items ?? []);

        self::assertSame([PatternOperator::Like, PatternOperator::Glob, PatternOperator::Regexp, PatternOperator::Match], $operators);
    }

    public function testEachOperatorCarriesTheNameOfTheFunctionItCalls(): void
    {
        self::assertSame(['LIKE', 'GLOB', 'REGEXP', 'MATCH'], array_map(static fn (PatternOperator $operator): string => $operator->value, PatternOperator::cases()));
    }
}
