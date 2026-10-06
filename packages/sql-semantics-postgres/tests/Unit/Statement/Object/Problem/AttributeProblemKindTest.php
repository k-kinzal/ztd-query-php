<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\AttributeProblemKind;

#[CoversClass(AttributeProblemKind::class)]
#[Small]
final class AttributeProblemKindTest extends TestCase
{
    public function testFormatCarriesTheServerMessage(): void
    {
        self::assertSame(['type attribute "%s" not recognized', 'type attribute "%s" not recognized', 'argument of %s must be a name'], [AttributeProblemKind::UnrecognizedTypeAttribute->format(), AttributeProblemKind::UnrecognizedRangeAttribute->format(), AttributeProblemKind::NotAName->format()]);
    }

    public function testWarningHoldsForTheBackwardsCompatibleCommands(): void
    {
        self::assertSame([AttributeProblemKind::UnrecognizedOperatorAttribute, AttributeProblemKind::UnrecognizedAggregateAttribute, AttributeProblemKind::UnrecognizedTypeAttribute], array_values(array_filter(AttributeProblemKind::cases(), static fn (AttributeProblemKind $kind): bool => $kind->warning())));
    }
}
