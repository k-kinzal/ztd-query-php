<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\ReplicaChannel;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReplicaChannel::class)]
#[Medium]
final class ReplicaChannelTest extends TestCase
{
    public function testCheckRequiresMySql57(): void
    {
        $current = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('STOP SLAVE');
        ReplicaChannel::check($current->origin, 'c');
        ReplicaChannel::check($current->origin, null);
        $legacy = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('STOP SLAVE');
        $this->expectException(InvalidStructure::class);
        ReplicaChannel::check($legacy->origin, 'c');
    }

    public function testCheckRejectsALineFeed(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('STOP REPLICA');
        $this->expectException(InvalidStructure::class);
        ReplicaChannel::check($statement->origin, "a\nb");
    }

}
