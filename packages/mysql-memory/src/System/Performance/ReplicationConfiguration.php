<?php

declare(strict_types=1);

namespace MySqlMemory\System\Performance;

use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * Configured replication connections, including updates retained by a failing CHANGE.
 *
 * A channel is visible when it has a host. RESET without ALL retains its row.
 * Defaults and partial updates were observed through SQL on MySQL 8.0.44,
 * 8.1.0, 8.4.7 and 9.1.0.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-replication-connection-configuration-table.html.
 *
 * @visibility MySqlMemory
 */
final class ReplicationConfiguration implements SystemRows
{
    /**
     * Answers each configured channel using the connection's current settings.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $registry = $reading->instance->registry;
        $rows = [];
        foreach (['' => $registry->replication] + $registry->channels as $name => $channel) {
            if ($channel->host === '') {
                continue;
            }
            $rows[] = [
                'CHANNEL_NAME' => $name, 'HOST' => $channel->host, 'PORT' => $channel->port,
                'AUTO_POSITION' => (int) $channel->autoPosition,
                'CONNECTION_RETRY_INTERVAL' => $channel->connectRetry,
                'HEARTBEAT_INTERVAL' => number_format($channel->heartbeatPeriods[$name] ?? 0.0, 3, '.', ''),
                'SOURCE_CONNECTION_AUTO_FAILOVER' => (int) $channel->autoFailover,
                'GTID_ONLY' => (int) $channel->gtidOnly,
            ] + $channel->configuration + [
                'USER' => '', 'NETWORK_INTERFACE' => '', 'SSL_ALLOWED' => 'NO',
                'SSL_CA_FILE' => '', 'SSL_CA_PATH' => '', 'SSL_CERTIFICATE' => '',
                'SSL_CIPHER' => '', 'SSL_KEY' => '', 'SSL_VERIFY_SERVER_CERTIFICATE' => 'NO',
                'SSL_CRL_FILE' => '', 'SSL_CRL_PATH' => '', 'TLS_VERSION' => '',
                'PUBLIC_KEY_PATH' => '', 'GET_PUBLIC_KEY' => 'NO', 'NETWORK_NAMESPACE' => '',
                'COMPRESSION_ALGORITHM' => 'uncompressed', 'ZSTD_COMPRESSION_LEVEL' => '3',
                'TLS_CIPHERSUITES' => null,
                'CONNECTION_RETRY_COUNT' => version_compare($reading->instance->version, '8.1.0', '<') ? '86400' : '10',
            ];
        }

        return $rows;
    }
}
