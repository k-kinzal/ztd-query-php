<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;

#[CoversClass(Terminology::class)]
#[Medium]
final class TerminologyTest extends TestCase
{
    public function testCasesNameBothVocabularies(): void
    {
        $stop = (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('STOP SLAVE')->statement;

        self::assertSame(['Legacy', 'Current'], array_column(Terminology::cases(), 'name'));
        self::assertInstanceOf(StopReplica::class, $stop);
        self::assertSame(Terminology::Legacy, $stop->terminology);
    }
}
