<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Noise\ReplicationNoise;

#[CoversClass(ReplicationNoise::class)]
#[Small]
final class ReplicationNoiseTest extends TestCase
{
    public function testPositionsListsTheWordsOfMultiWordKeywords(): void
    {
        self::assertSame([
            'change_replication_source: REPLICATION SOURCE_SYM' => [0],
            'master_or_binary_logs_and_gtids: BINARY_SYM LOGS_SYM AND_SYM GTIDS_SYM' => [1, 2, 3],
            'ignore_server_id_list: ignore_server_id_list , ignore_server_id' => [1],
        ], ReplicationNoise::positions());
    }

    public function testSynonymsMapTheDeprecatedSpellings(): void
    {
        $synonyms = ReplicationNoise::synonyms();

        self::assertCount(31, $synonyms);
        self::assertSame([0 => 'SOURCE_HOST_SYM'], $synonyms['change_replication_source_host: MASTER_HOST_SYM']);
        self::assertSame([0 => 'GET_SOURCE_PUBLIC_KEY_SYM'], $synonyms['change_replication_source_get_source_public_key: GET_MASTER_PUBLIC_KEY_SYM']);
        self::assertArrayNotHasKey('replica: SLAVE', $synonyms);
    }
}
