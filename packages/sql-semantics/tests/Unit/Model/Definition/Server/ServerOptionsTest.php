<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Server\ServerOptions;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(ServerOptions::class)]
#[Medium]
final class ServerOptionsTest extends TestCase
{
    public function testCarriesEveryConnectionOption(): void
    {
        $options = new ServerOptions('u', 'h', 'd', 'o', 'p', 's', 0);
        self::assertSame(['u', 'h', 'd', 'o', 'p', 's', 0], [$options->user, $options->host, $options->database, $options->owner, $options->password, $options->socket, $options->port]);
    }

    public function testRejectsAnEmptyOptionList(): void
    {
        $this->expectException(InvalidStructure::class);
        new ServerOptions();
    }

    public function testRejectsANegativePort(): void
    {
        $this->expectException(InvalidStructure::class);
        new ServerOptions(port: -1);
    }
}
