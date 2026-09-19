<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\ExternalInput;

#[CoversClass(ExternalInput::class)]
final class ExternalInputTest extends TestCase
{
    public function testIsVariableRecognisesTheSuperglobals(): void
    {
        $external = new ExternalInput();
        self::assertTrue($external->isVariable('_GET'));
        self::assertTrue($external->isVariable('_POST'));
        self::assertTrue($external->isVariable('argv'));
        self::assertFalse($external->isVariable('sql'));
    }

    public function testIsFunctionRecognisesTheReadingFunctions(): void
    {
        $external = new ExternalInput();
        self::assertTrue($external->isFunction('getenv'));
        self::assertTrue($external->isFunction('\\FILTER_INPUT'));
        self::assertFalse($external->isFunction('sprintf'));
    }

    public function testIsStreamRecognisesTheRequestBody(): void
    {
        $external = new ExternalInput();
        self::assertTrue($external->isStream('php://input'));
        self::assertTrue($external->isStream('php://stdin'));
        self::assertFalse($external->isStream('/etc/hosts'));
    }
}
