<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication;

use MySqlMemory\Command\Admin\Literals;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Registry\Replication;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;

/**
 * Applies connection settings in their observed order, preserving partial updates.
 *
 * Text and SSL settings survive a compression failure. A valid algorithm list
 * survives a later invalid ZSTD level. Observed through SQL on MySQL 8.0.44,
 * 8.4.7 and 9.1.0, including Performance Schema reads after each failure.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html.
 *
 * @visibility MySqlMemory
 */
final class SourceConfiguration
{
    /**
     * Connection text options and the Performance Schema column holding them.
     */
    private const TEXT = [
        'SOURCE_USER' => 'USER', 'SOURCE_BIND' => 'NETWORK_INTERFACE',
        'SOURCE_SSL_CA' => 'SSL_CA_FILE', 'SOURCE_SSL_CAPATH' => 'SSL_CA_PATH',
        'SOURCE_SSL_CERT' => 'SSL_CERTIFICATE', 'SOURCE_SSL_CIPHER' => 'SSL_CIPHER',
        'SOURCE_SSL_KEY' => 'SSL_KEY', 'SOURCE_SSL_CRL' => 'SSL_CRL_FILE',
        'SOURCE_SSL_CRLPATH' => 'SSL_CRL_PATH', 'SOURCE_TLS_VERSION' => 'TLS_VERSION',
        'SOURCE_PUBLIC_KEY_PATH' => 'PUBLIC_KEY_PATH', 'NETWORK_NAMESPACE' => 'NETWORK_NAMESPACE',
        'SOURCE_TLS_CIPHERSUITES' => 'TLS_CIPHERSUITES',
    ];

    /**
     * Applies the effective options, preserving earlier updates when a later option fails.
     *
     * @param array<string, SourceOption> $options The final setting of each kind
     * @throws \MySqlMemory\Error\SqlError When compression validation rejects a setting
     */
    public function apply(array $options, Replication $state, string $channel): void
    {
        foreach (self::TEXT as $key => $column) {
            if (isset($options[$key])) {
                $value = $options[$key]->value;
                $state->configuration[$column] = $value instanceof Text ? explode("\0", $value->bytes(), 2)[0] : null;
            }
        }
        foreach (['SOURCE_SSL' => 'SSL_ALLOWED', 'SOURCE_SSL_VERIFY_SERVER_CERT' => 'SSL_VERIFY_SERVER_CERTIFICATE', 'GET_SOURCE_PUBLIC_KEY' => 'GET_PUBLIC_KEY'] as $key => $column) {
            $value = $options[$key]->value ?? null;
            if ($value instanceof Numeral) {
                $state->configuration[$column] = (new Literals())->number($value) !== '0' ? 'YES' : 'NO';
            }
        }
        $retry = $options[SourceOptionKind::RetryCount->value]->value ?? null;
        if ($retry instanceof Numeral) {
            $state->configuration['CONNECTION_RETRY_COUNT'] = (new Literals())->number($retry);
        }
        $compression = $options[SourceOptionKind::CompressionAlgorithms->value]->value ?? null;
        if ($compression instanceof Text) {
            $value = explode("\0", $compression->bytes(), 2)[0];
            (new SourceChange())->compression($value, $channel);
            $state->configuration['COMPRESSION_ALGORITHM'] = $value;
        }
        $this->level($options[SourceOptionKind::ZstdCompressionLevel->value] ?? null, $state, $channel);
    }

    /**
     * Applies a nonzero compression level after the algorithm list has been retained.
     *
     * @throws \MySqlMemory\Error\SqlError When the nonzero converted level exceeds 22
     */
    public function level(?SourceOption $option, Replication $state, string $channel): void
    {
        $value = $option?->value;
        if (!$value instanceof Numeral) {
            return;
        }
        $number = (string) (new SourceRepository())->unsigned($value);
        if ($number === '0') {
            return;
        }
        if (bccomp($number, '22') > 0) {
            throw AdministrationError::ReplicationCompressionLevel->error($number, $channel);
        }
        $state->configuration['ZSTD_COMPRESSION_LEVEL'] = $number;
    }
}
