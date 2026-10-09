<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use ArrayObject;
use MySqlMemory\Instance;
use MySqlMemory\Protocol\MalformedPacket;
use MySqlMemory\Protocol\PayloadReader;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Server\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Client::class)]
#[Small]
final class ClientTest extends TestCase
{
    public function testResetConnectionPreservesFoundRowsNegotiatedInTheHandshake(): void
    {
        $client = new Client(new Instance(), 7, static function (string $bytes): void {
        });
        $client->handle("\x02\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $client->session()->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1)');
        $before = $client->session()->query('UPDATE t SET a = a')[0];
        $client->ping(true);
        $after = $client->session()->query('UPDATE t SET a = a')[0];

        self::assertInstanceOf(Completion::class, $before);
        self::assertInstanceOf(Completion::class, $after);
        self::assertSame(1, $before->affectedRows);
        self::assertSame(1, $after->affectedRows);
        self::assertSame('Rows matched: 1  Changed: 0  Warnings: 0', $after->info);
    }

    public function testCloseReleasesTheLocksOfTheSession(): void
    {
        $instance = new Instance();
        $client = new Client($instance, 7, static function (string $bytes): void {
        });
        $client->close();
        $client->receive("\x26\x00\x00\x01" . "\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $client->session()->query("SELECT GET_LOCK('a', 0)");
        $client->close();

        self::assertSame([[], []], [$instance->registry->threads->locks, $instance->registry->threads->connected]);
    }

    public function testGreetSendsTheInitialHandshake(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 7, static function (string $bytes) use ($sent): void {
            $sent->append(strlen($bytes));
            $sent->append(substr($bytes, 0, 15) . substr($bytes, 23, 19) . substr($bytes, 54));
        });
        $client->greet();

        self::assertSame(
            [77, "\x49\x00\x00\x00\x0A8.4.7\x00\x07\x00\x00\x00" . "\x00\x0F\xA2\xFF\x02\x00\x3F\x00\x15" . str_repeat("\x00", 10) . "\x00mysql_native_password\x00"],
            $sent->getArrayCopy(),
        );
    }

    public function testGreetSendsTheVersionOfTheInstance(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance('8.0.44'), 1, static function (string $bytes) use ($sent): void {
            $sent->append(substr($bytes, 3, 13));
        });
        $client->greet();

        self::assertSame(["\x00\x0A8.0.44\x00\x01\x00\x00\x00"], $sent->getArrayCopy());
    }

    public function testReceiveOpensTheSessionFromTheHandshakeResponse(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        });
        $open = $client->receive("\x26\x00\x00\x01" . "\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");

        self::assertTrue($open);
        self::assertSame(["\x07\x00\x00\x02\x00\x00\x00\x02\x00\x00\x00"], $sent->getArrayCopy());
        self::assertSame('root', $client->session()->user);
        self::assertSame('localhost', $client->session()->host);
    }

    public function testReceiveWaitsForTheRestOfAPacket(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        });
        $first = $client->receive("\x26\x00");
        $second = $client->receive("\x00\x01\x00\x82\x08\x00\x00\x00\x00\x01\xFF");
        $waited = $sent->count();
        $third = $client->receive(str_repeat("\x00", 23) . "root\x00\x00");

        self::assertTrue($first);
        self::assertTrue($second);
        self::assertTrue($third);
        self::assertSame(0, $waited);
        self::assertSame(["\x07\x00\x00\x02\x00\x00\x00\x02\x00\x00\x00"], $sent->getArrayCopy());
    }

    public function testReceiveAnswersEveryPacketOfTheBytes(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        });
        $client->receive("\x26\x00\x00\x01" . "\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00" . "\x01\x00\x00\x00\x0E");

        self::assertSame(["\x07\x00\x00\x02\x00\x00\x00\x02\x00\x00\x00", "\x07\x00\x00\x01\x00\x00\x00\x02\x00\x00\x00"], $sent->getArrayCopy());
    }

    public function testReceiveAnswersFalseWhenTheClientQuits(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        });
        $client->receive("\x26\x00\x00\x01" . "\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");

        self::assertFalse($client->receive("\x01\x00\x00\x00\x01"));
        self::assertSame(1, $sent->count());
    }

    public function testHandleAnswersAQueryWithATextResultSet(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $client->receive("\x09\x00\x00\x00\x03SELECT 1");

        self::assertSame(
            [
                "\x07\x00\x00\x00\x00\x00\x00\x02\x00\x00\x00",
                "\x01\x00\x00\x01\x01",
                "\x17\x00\x00\x02\x03def\x00\x00\x00\x011\x00\x0C\x3F\x00\x02\x00\x00\x00\x08\x81\x80\x00\x00\x00",
                "\x05\x00\x00\x03\xFE\x00\x00\x02\x00",
                "\x02\x00\x00\x04\x011",
                "\x05\x00\x00\x05\xFE\x00\x00\x02\x00",
            ],
            $sent->getArrayCopy(),
        );
    }

    public function testHandleAnswersPingWithOk(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $client->receive("\x01\x00\x00\x00\x0E");

        self::assertSame(["\x07\x00\x00\x01\x00\x00\x00\x02\x00\x00\x00"], array_slice($sent->getArrayCopy(), 1, 1));
    }

    public function testHandleAnswersSetOptionWithEof(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $client->receive("\x03\x00\x00\x00\x1B\x00\x00");

        self::assertSame(["\x05\x00\x00\x01\xFE\x00\x00\x02\x00"], array_slice($sent->getArrayCopy(), 1, 1));
    }

    public function testHandleAnswersUnknownCommandToAnUnknownCode(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $open = $client->receive("\x01\x00\x00\x00\x05");

        self::assertTrue($open);
        self::assertSame(["\x18\x00\x00\x01\xFF\x17\x04#08S01Unknown command"], array_slice($sent->getArrayCopy(), 1, 1));
    }

    public function testHandleAnswersFalseToQuit(): void
    {
        $client = new Client(new Instance(), 7, static function (string $bytes): void {
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");

        self::assertFalse($client->handle("\x01"));
    }

    public function testAuthenticateReadsTheUserAndTheDatabase(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $sent = new ArrayObject();
        $client = new Client($instance, 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        }, '10.0.0.5');
        $open = $client->authenticate(new PayloadReader("\x08\x82\x28\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "app\x00\x14" . str_repeat('s', 20) . "d\x00mysql_native_password\x00"));

        self::assertTrue($open);
        self::assertSame('app', $client->session()->user);
        self::assertSame('10.0.0.5', $client->session()->host);
        self::assertSame('d', $client->session()->variables->database);
        self::assertSame(["\x07\x00\x00\x00\x00\x00\x00\x02\x00\x00\x00"], $sent->getArrayCopy());
    }

    public function testAuthenticateTakesAnEmptyDatabaseForNone(): void
    {
        $client = new Client(new Instance(), 7, static function (string $bytes): void {
        });
        $client->authenticate(new PayloadReader("\x08\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00\x00"));

        self::assertSame('', $client->session()->variables->database);
    }

    public function testAuthenticateSeesTheClientHostOfTheInstance(): void
    {
        $client = new Client(new Instance('8.4.7', [], [], 'example.com'), 7, static function (string $bytes): void {
        }, '10.0.0.5');
        $client->authenticate(new PayloadReader("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00"));

        self::assertSame('example.com', $client->session()->host);
    }

    public function testAuthenticateRefusesAnUnknownDatabase(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        });
        $open = $client->authenticate(new PayloadReader("\x08\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00nowhere\x00"));

        self::assertFalse($open);
        self::assertSame(["\x23\x00\x00\x00\xFF\x19\x04#42000Unknown database 'nowhere'"], $sent->getArrayCopy());
    }

    public function testAuthenticateRefusesATruncatedResponse(): void
    {
        $client = new Client(new Instance(), 7, static function (string $bytes): void {
        });

        $this->expectException(MalformedPacket::class);

        $client->authenticate(new PayloadReader("\x00\x82\x08\x00\x00\x00"));
    }

    public function testSessionAnswersTheSessionOfTheConnection(): void
    {
        $client = new Client(new Instance(), 3, static function (string $bytes): void {
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");

        self::assertSame($client->session(), $client->session());
        self::assertSame('root', $client->session()->user);
    }

    public function testStatusReportsAutocommitBeforeAuthentication(): void
    {
        $client = new Client(new Instance(), 7, static function (string $bytes): void {
        });

        self::assertSame(2, $client->status());
    }

    public function testStatusReportsAnOpenTransaction(): void
    {
        $client = new Client(new Instance(), 7, static function (string $bytes): void {
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $client->query('START TRANSACTION');

        self::assertSame(3, $client->status());
    }

    public function testStatusReportsAutocommitOff(): void
    {
        $client = new Client(new Instance(), 7, static function (string $bytes): void {
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $client->query('SET autocommit = 0');

        self::assertSame(0, $client->status());
    }

    public function testStatusReportsNoBackslashEscapes(): void
    {
        $client = new Client(new Instance(), 7, static function (string $bytes): void {
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $client->query("SET sql_mode = 'NO_BACKSLASH_ESCAPES'");

        self::assertSame(514, $client->status());
    }

    public function testInitDatabaseUsesTheDatabase(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance('8.4.7', [], ['d']), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $client->receive("\x02\x00\x00\x00\x02d");

        self::assertSame('d', $client->session()->variables->database);
        self::assertSame(["\x07\x00\x00\x01\x00\x00\x00\x02\x00\x00\x00"], array_slice($sent->getArrayCopy(), 1, 1));
    }

    public function testInitDatabaseAnswersTheErrorOfAnUnknownDatabase(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $open = $client->initDatabase('shop');

        self::assertTrue($open);
        self::assertSame('', $client->session()->variables->database);
        self::assertSame(["\x20\x00\x00\x01\xFF\x19\x04#42000Unknown database 'shop'"], array_slice($sent->getArrayCopy(), 1, 1));
    }

    public function testPingKeepsTheSession(): void
    {
        $client = new Client(new Instance(), 7, static function (string $bytes): void {
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $session = $client->session();

        self::assertTrue($client->ping(false));
        self::assertSame($session, $client->session());
    }

    public function testPingResetsTheConnection(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance('8.4.7', [], ['d']), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        });
        $client->handle("\x08\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "app\x00\x00d\x00");
        $client->query('SET @v = 1; START TRANSACTION');
        $client->receive("\x01\x00\x00\x00\x1F");

        self::assertSame([], $client->session()->variables->user);
        self::assertFalse($client->session()->transaction->open);
        self::assertSame('d', $client->session()->variables->database);
        self::assertSame('app', $client->session()->user);
        self::assertSame(["\x07\x00\x00\x01\x00\x00\x00\x02\x00\x00\x00"], array_slice($sent->getArrayCopy(), 3, 1));
    }

    public function testQueryMarksEveryResultButTheLastWithMoreResults(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $client->receive("\x0B\x00\x00\x00\x03DO 1; DO 2");

        self::assertSame(["\x07\x00\x00\x01\x00\x00\x00\x0A\x00\x00\x00", "\x07\x00\x00\x02\x00\x00\x00\x02\x00\x00\x00"], array_slice($sent->getArrayCopy(), 1));
    }

    public function testQueryEndsAtTheFirstError(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $client->receive("\x22\x00\x00\x00\x03DO 1; SELECT * FROM nowhere; DO 2");

        self::assertSame(["\x07\x00\x00\x01\x00\x00\x00\x0A\x00\x00\x00", "\x1D\x00\x00\x02\xFF\x16\x04#3D000No database selected"], array_slice($sent->getArrayCopy(), 1));
    }

    public function testReplySendsACompletionAsOk(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        });
        $client->reply(new Completion(2, 5, 1, 'Records: 2  Duplicates: 0  Warnings: 1'), 8, false);

        self::assertSame(["\x2E\x00\x00\x00\x00\x02\x05\x0A\x00\x01\x00\x26Records: 2  Duplicates: 0  Warnings: 1"], $sent->getArrayCopy());
    }

    public function testReplySendsAResultSetInTheBinaryProtocol(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        });
        $client->reply(new ResultSet([new ResultColumn('a', Field::Long, 11, 0, 0, 63)], [['7'], [null]], 1), 0, true);

        self::assertSame(
            [
                "\x01\x00\x00\x00\x01",
                "\x17\x00\x00\x01\x03def\x00\x00\x00\x01a\x00\x0C\x3F\x00\x0B\x00\x00\x00\x03\x00\x00\x00\x00\x00",
                "\x05\x00\x00\x02\xFE\x00\x00\x02\x00",
                "\x06\x00\x00\x03\x00\x00\x07\x00\x00\x00",
                "\x02\x00\x00\x04\x00\x04",
                "\x05\x00\x00\x05\xFE\x01\x00\x02\x00",
            ],
            $sent->getArrayCopy(),
        );
    }

    public function testSendSendsOnePacketAndAnswersTrue(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        });

        self::assertTrue($client->send('abc'));
        self::assertSame(["\x03\x00\x00\x00abc"], $sent->getArrayCopy());
    }

    public function testPacketNumbersThePacketsInSequence(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        });
        $client->packet('');
        $client->packet(str_repeat('x', 300));

        self::assertSame(["\x00\x00\x00\x00", "\x2C\x01\x00\x01" . str_repeat('x', 300)], $sent->getArrayCopy());
    }

    public function testGreetNamesLatin1ToTheClientsOf57(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance('5.7.44'), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes[27]);
        });
        $client->greet();

        self::assertSame(["\x08"], $sent->getArrayCopy());
    }
    public function testStatusReportsATransactionOnceItIsActive(): void
    {
        $client = new Client(new Instance(), 1, static function (string $bytes): void {
        });
        $client->receive("\x26\x00\x00\x01" . "\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $client->session()->query('CREATE DATABASE d; CREATE TABLE d.t (a INT); SET autocommit = 0; SELECT 1');
        $idle = $client->status();
        $client->session()->query('SELECT * FROM d.t');

        self::assertSame([0, 1], [$idle, $client->status()]);
    }

    public function testQueryEndsTheConnectionOnceAStatementReleasesTheSession(): void
    {
        $sent = new ArrayObject();
        $client = new Client(new Instance(), 7, static function (string $bytes) use ($sent): void {
            $sent->append($bytes);
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");

        $open = $client->receive("\x15\x00\x00\x00\x03COMMIT RELEASE; DO 1");

        self::assertFalse($open);
        self::assertSame(["\x07\x00\x00\x01\x00\x00\x00\x0A\x00\x00\x00"], array_slice($sent->getArrayCopy(), 1));
    }
}
