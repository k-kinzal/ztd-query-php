<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Preparation;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Preparation\Alternatives;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversClass(Alternatives::class)]
#[Large]
final class AlternativesTest extends TestCase
{
    public function testReadCapturesTheInitialDatabaseModeAndEncoding(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $session->query("CREATE DATABASE d; USE d; SET sql_mode='ANSI_QUOTES'; SET NAMES latin1");
        $definition = (new Alternatives())->read('SELECT 1', $session);
        self::assertSame('d', $definition->database?->value);
        self::assertSame('latin1', $definition->initial->client->name);
        self::assertTrue($definition->initial->language->lexical->ansiQuotes);
        self::assertCount(1184, $definition->alternatives);
    }
}
