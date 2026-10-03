<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Limit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\ProhibitedConstruct;

#[CoversClass(ProhibitedConstruct::class)]
#[Small]
final class ProhibitedConstructTest extends TestCase
{
    public function testCasesNameEachConstructWithThePhraseOfTheManual(): void
    {
        self::assertSame(['Parameter', 'Subquery', 'DotOperator', 'NonDeterministicFunction'], array_column(ProhibitedConstruct::cases(), 'name'));
        self::assertSame('the "." operator', ProhibitedConstruct::DotOperator->value);
    }
}
