<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Removal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Removal\MissingTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Removal\TriggerVerification;

#[CoversClass(MissingTrigger::class)]
#[Small]
final class MissingTriggerTest extends TestCase
{
    public function testQuotedPreservesAnEmptySchemaQualifier(): void
    {
        $target = new MissingTrigger(new Identifier('later'), TriggerVerification::KnownSchema, new Identifier(''));

        self::assertSame('""."later"', $target->quoted());
    }
}
