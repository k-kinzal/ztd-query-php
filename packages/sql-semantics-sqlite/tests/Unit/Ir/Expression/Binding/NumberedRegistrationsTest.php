<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Binding\NumberedRegistrations;
use SqlSemantics\Platform\Sqlite\Statement\Expression\BindParameter;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ParameterPrefix;

#[CoversClass(NumberedRegistrations::class)]
#[Small]
final class NumberedRegistrationsTest extends TestCase
{
    public function testCollectPreservesTheNumberedMarkerSpelling(): void
    {
        $node = new BindParameter(ParameterPrefix::Question, '008');
        $values = (new NumberedRegistrations())->collect($node, 'Expected numbered registrations.');

        self::assertCount(1, $values);
        self::assertSame('008', $values[0]->label);
        self::assertSame(8, $values[0]->slot);
    }
}
