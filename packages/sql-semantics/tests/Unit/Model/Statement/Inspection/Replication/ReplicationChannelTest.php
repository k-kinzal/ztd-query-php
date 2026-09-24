<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Inspection\Replication\ReplicationChannel;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReplicationChannel::class)]
#[Medium]
final class ReplicationChannelTest extends TestCase
{
    public function testCheckAcceptsANamedOrEmptyChannel(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SHOW BINARY LOGS');
        ReplicationChannel::check($statement->origin, 'east');
        ReplicationChannel::check($statement->origin, '');
        ReplicationChannel::check($statement->origin, null);
        self::assertSame('SHOW BINARY LOGS', $statement->toString());
    }

    #[TestWith(['mysql-5.6.51', 'east'])]
    #[TestWith(['mysql-8.4.7', "a\nb"])]
    public function testCheckRejectsAChannelTheServerCannotName(string $version, string $channel): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('SHOW BINARY LOGS');
        $this->expectException(InvalidStructure::class);
        ReplicationChannel::check($statement->origin, $channel);
    }
}
