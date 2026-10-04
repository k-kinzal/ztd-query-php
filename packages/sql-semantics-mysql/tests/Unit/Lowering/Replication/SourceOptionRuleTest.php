<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Replication\SourceOptionRule;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;

#[CoversClass(SourceOptionRule::class)]
#[Medium]
final class SourceOptionRuleTest extends TestCase
{
    public function testOptionLowersDirectAndForwardedOptions(): void
    {
        $change = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("change master to master_tls_version = 'TLSv1.2', master_log_file = 'f'")->statement;

        self::assertInstanceOf(ChangeReplicationSource::class, $change);
        self::assertSame([SourceOptionKind::TlsVersion, SourceOptionKind::LogFile], array_column($change->options, 'kind'));
    }

    public function testKeywordReadsBothSpellingsOfAnOption(): void
    {
        self::assertSame(
            'CHANGE REPLICATION SOURCE TO GET_SOURCE_PUBLIC_KEY = 1, GET_SOURCE_PUBLIC_KEY = 0, SOURCE_COMPRESSION_ALGORITHMS = \'zstd\'',
            (new Semantics(Dialect::MySql, 'mysql-8.1.0'))->analyze("change replication source to get_master_public_key = 1, get_source_public_key = 0, master_compression_algorithms = 'zstd'")->toString(),
        );
    }
}
