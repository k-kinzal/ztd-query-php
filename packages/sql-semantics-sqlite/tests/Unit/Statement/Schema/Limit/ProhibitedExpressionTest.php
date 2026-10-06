<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Limit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\DefinitionPosition;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\ProhibitedConstruct;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\ProhibitedExpression;

#[CoversClass(ProhibitedExpression::class)]
#[Small]
final class ProhibitedExpressionTest extends TestCase
{
    public function testMessageNamesTheConstructAndThePosition(): void
    {
        self::assertSame('Parameters are prohibited in CHECK constraints.', (new ProhibitedExpression(ProhibitedConstruct::Parameter, DefinitionPosition::CheckConstraint))->message());
        self::assertSame('The "." operator is prohibited in generated columns.', (new ProhibitedExpression(ProhibitedConstruct::DotOperator, DefinitionPosition::GeneratedColumn))->message());
        self::assertSame('Non-deterministic functions are prohibited in index expressions.', (new ProhibitedExpression(ProhibitedConstruct::NonDeterministicFunction, DefinitionPosition::IndexExpression))->message());
    }
}
