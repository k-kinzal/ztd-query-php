<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeErrorClassifier;
use ZtdQuery\Connection\Exception\DatabaseException;

#[CoversNothing]
final class ErrorClassifierTest extends TestCase
{
    public function testIsUnknownSchemaErrorAnswersForAFailureThePlatformCallsOne(): void
    {
        $classifier = new FakeErrorClassifier();

        self::assertTrue($classifier->isUnknownSchemaError(new DatabaseException('no such column', 1054)));
    }

    public function testIsUnknownSchemaErrorIsFalseForAFailureAboutSomethingElse(): void
    {
        $classifier = new FakeErrorClassifier();

        self::assertFalse($classifier->isUnknownSchemaError(new DatabaseException('deadlock', 2013)));
    }

    public function testIsUnknownSchemaErrorIsFalseForAFailureTheDriverPutNoCodeOn(): void
    {
        $classifier = new FakeErrorClassifier();

        self::assertFalse($classifier->isUnknownSchemaError(new DatabaseException('gone')));
    }
}
