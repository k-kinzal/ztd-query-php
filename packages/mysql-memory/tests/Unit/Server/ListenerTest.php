<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use MySqlMemory\Instance;
use MySqlMemory\Server\Listener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Listener::class)]
#[Small]
final class ListenerTest extends TestCase
{
    public function testOpenListensOnAFreeLocalPort(): void
    {
        $listener = new Listener(new Instance(), 'tcp://127.0.0.1:0');

        self::assertMatchesRegularExpression('/\Atcp:\/\/127\.0\.0\.1:[1-9][0-9]*\z/', $listener->open());
    }

    public function testOpenAnswersTheAddressOfAUnixSocket(): void
    {
        $path = sys_get_temp_dir() . '/mysql-memory-' . bin2hex(random_bytes(6)) . '.sock';
        $listener = new Listener(new Instance(), 'unix://' . $path);
        $address = $listener->open();
        $created = file_exists($path);
        unlink($path);

        self::assertSame('unix://' . $path, $address);
        self::assertTrue($created);
    }

    public function testOpenRefusesAnAddressItCannotListenOn(): void
    {
        $listener = new Listener(new Instance(), 'nosuch://127.0.0.1:0');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot listen on nosuch://127.0.0.1:0: ');

        $listener->open();
    }

    public function testServeRefusesAListenerThatIsNotOpen(): void
    {
        $listener = new Listener(new Instance(), 'tcp://127.0.0.1:0');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The listener is not open.');

        $listener->serve();
    }
}
