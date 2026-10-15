<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Replication;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Replication\ChannelName;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Statement\Node;

#[CoversClass(ChannelName::class)]
#[Small]
final class ChannelNameTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(Text): Node}>
     */
    public static function providerConsumers(): iterable
    {
        yield 'show log events' => [static fn (Text $name): Node => new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowRelaylogEvents(channel: $name)];
        yield 'show status' => [static fn (Text $name): Node => new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowReplicaStatus(Terminology::Current, $name)];
        yield 'start' => [static fn (Text $name): Node => new \SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica(Terminology::Current, channel: $name)];
        yield 'stop' => [static fn (Text $name): Node => new \SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica(Terminology::Current, channel: $name)];
        yield 'reset' => [static fn (Text $name): Node => new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica(Terminology::Current, channel: $name)];
        yield 'flush' => [static fn (Text $name): Node => new \SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushItem(\SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushOption::RelayLogs, $name)];
        yield 'source' => [static fn (Text $name): Node => new \SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource(Terminology::Current, [new \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption(Terminology::Current, \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::Host, new Text('host'))], $name)];
        yield 'filter' => [static fn (Text $name): Node => new \SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter([new \SqlSemantics\Platform\MySql\Statement\Replication\Filter\ReplicationFilter(\SqlSemantics\Platform\MySql\Statement\Replication\Filter\FilterKind::DoDb, [])], $name)];
    }

    /**
     * @param Closure(Text): Node $construct A public channel consumer
     */
    #[DataProvider('providerConsumers')]
    public function testCheckRefusesLineFeedsInEveryChannelRole(Closure $construct): void
    {
        $this->expectExceptionMessage('A replication channel cannot contain a line feed.');
        $construct(new Text("a\nb"));
    }

    public function testCheckKeepsDefaultChannelsAndOtherControlBytes(): void
    {
        $domain = new ChannelName();
        $domain->check(null);
        $domain->check(new Text(''));
        $domain->check(new Text("a\rb"));
        $domain->check(new Text("a\0b"));

        self::assertNull($domain->problem(new Text("a\rb")));
    }

    public function testProblemRetainsTheDecodedRejectedOperand(): void
    {
        $text = new Text("a\nb");
        $problem = (new ChannelName())->problem($text);

        self::assertNotNull($problem);
        self::assertSame($text, $problem->text);
    }

    public function testValueTerminatesNamesAtNulBeforeCheckingForLineFeed(): void
    {
        $text = new Text("a\0\nb");
        $domain = new ChannelName();
        $domain->check($text);

        self::assertSame('a', $domain->value($text));
        self::assertNull($domain->problem($text));
        self::assertSame("a\n", $domain->problem(new Text("a\n\0b"))?->text?->value);
    }
}
