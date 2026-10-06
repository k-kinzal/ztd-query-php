<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute\Known;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\AggregateAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

#[CoversClass(AggregateAttribute::class)]
#[Small]
final class AggregateAttributeTest extends TestCase
{
    public function testReadingFollowsTheCommand(): void
    {
        self::assertSame([Reading::Function, Reading::Type, Reading::Integer, Reading::Text, Reading::Parallelism, Reading::FinalModification, Reading::Operator, Reading::Boolean], [AggregateAttribute::Sfunc1->reading(), AggregateAttribute::Stype->reading(), AggregateAttribute::Sspace->reading(), AggregateAttribute::Initcond->reading(), AggregateAttribute::Parallel->reading(), AggregateAttribute::FinalfuncModify->reading(), AggregateAttribute::Sortop->reading(), AggregateAttribute::Hypothetical->reading()]);
    }

    public function testNamedComparesRespectingCase(): void
    {
        self::assertSame([AggregateAttribute::Sfunc, null], [AggregateAttribute::named('sfunc'), AggregateAttribute::named('SFUNC')]);
    }

    public function testTextIsTheAttributeName(): void
    {
        self::assertSame('sfunc', AggregateAttribute::Sfunc->text());
    }
}
