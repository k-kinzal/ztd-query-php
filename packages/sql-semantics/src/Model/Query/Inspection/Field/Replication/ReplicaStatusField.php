<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Replication;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\ReplicationVocabulary;
use SqlSemantics\Model\Query\Inspection\TextField;
use SqlSemantics\Type\Nullability;

/**
 * Result fields of SHOW REPLICA STATUS; labels follow the request vocabulary.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Replication\ReplicaStatusField::IoState->label() // => 'Replica_IO_State'
 */
enum ReplicaStatusField: string implements MetadataField
{
    use TextField;

    case IoState = 'Replica_IO_State';
    case SourceHost = 'Source_Host';
    case SourceUser = 'Source_User';
    case SourcePort = 'Source_Port';
    case ConnectRetry = 'Connect_Retry';
    case SourceLogFile = 'Source_Log_File';
    case ReadSourceLogPosition = 'Read_Source_Log_Pos';
    case RelayLogFile = 'Relay_Log_File';
    case RelayLogPosition = 'Relay_Log_Pos';
    case RelaySourceLogFile = 'Relay_Source_Log_File';
    case IoRunning = 'Replica_IO_Running';
    case SqlRunning = 'Replica_SQL_Running';
    case ReplicateDoDb = 'Replicate_Do_DB';
    case ReplicateIgnoreDb = 'Replicate_Ignore_DB';
    case ReplicateDoTable = 'Replicate_Do_Table';
    case ReplicateIgnoreTable = 'Replicate_Ignore_Table';
    case ReplicateWildDoTable = 'Replicate_Wild_Do_Table';
    case ReplicateWildIgnoreTable = 'Replicate_Wild_Ignore_Table';
    case LastErrorNumber = 'Last_Errno';
    case LastError = 'Last_Error';
    case SkipCounter = 'Skip_Counter';
    case ExecSourceLogPosition = 'Exec_Source_Log_Pos';
    case RelayLogSpace = 'Relay_Log_Space';
    case UntilCondition = 'Until_Condition';
    case UntilLogFile = 'Until_Log_File';
    case UntilLogPosition = 'Until_Log_Pos';
    case SslAllowed = 'Source_SSL_Allowed';
    case SslCaFile = 'Source_SSL_CA_File';
    case SslCaPath = 'Source_SSL_CA_Path';
    case SslCert = 'Source_SSL_Cert';
    case SslCipher = 'Source_SSL_Cipher';
    case SslKey = 'Source_SSL_Key';
    case SecondsBehindSource = 'Seconds_Behind_Source';
    case SslVerifyServerCert = 'Source_SSL_Verify_Server_Cert';
    case LastIoErrorNumber = 'Last_IO_Errno';
    case LastIoError = 'Last_IO_Error';
    case LastSqlErrorNumber = 'Last_SQL_Errno';
    case LastSqlError = 'Last_SQL_Error';
    case ReplicateIgnoreServerIds = 'Replicate_Ignore_Server_Ids';
    case SourceServerId = 'Source_Server_Id';
    case SourceUuid = 'Source_UUID';
    case SourceInfoFile = 'Source_Info_File';
    case SqlDelay = 'SQL_Delay';
    case SqlRemainingDelay = 'SQL_Remaining_Delay';
    case SqlRunningState = 'Replica_SQL_Running_State';
    case SourceRetryCount = 'Source_Retry_Count';
    case SourceBind = 'Source_Bind';
    case LastIoErrorTimestamp = 'Last_IO_Error_Timestamp';
    case LastSqlErrorTimestamp = 'Last_SQL_Error_Timestamp';
    case SslCrl = 'Source_SSL_Crl';
    case SslCrlPath = 'Source_SSL_Crlpath';
    case RetrievedGtidSet = 'Retrieved_Gtid_Set';
    case ExecutedGtidSet = 'Executed_Gtid_Set';
    case AutoPosition = 'Auto_Position';
    case ReplicateRewriteDb = 'Replicate_Rewrite_DB';
    case ChannelName = 'Channel_Name';
    case SourceTlsVersion = 'Source_TLS_Version';
    case SourcePublicKeyPath = 'Source_public_key_path';
    case GetSourcePublicKey = 'Get_Source_public_key';
    case NetworkNamespace = 'Network_Namespace';

    /**
     * Returns the result label in the vocabulary the request was written in.
     */
    public function labelIn(ReplicationVocabulary $vocabulary): string
    {
        return $this === self::GetSourcePublicKey && $vocabulary === ReplicationVocabulary::Legacy ? 'Get_master_public_key' : $vocabulary->label($this->value);
    }

    /**
     * Returns the fields a grammar release reports, in server order: 5.6 ends with Auto_Position and 5.7 with the TLS version.
     * @return list<self>
     */
    public static function reported(?string $grammarVersion): array
    {
        $last = match ($grammarVersion) {
            'mysql-5.6.51' => self::AutoPosition,
            'mysql-5.7.44' => self::SourceTlsVersion,
            default => self::NetworkNamespace,
        };
        $fields = [];
        foreach (self::cases() as $field) {
            $fields[] = $field;
            if ($field === $last) {
                break;
            }
        }
        return $fields;
    }

    /**
     * Positions, counters, and delays are integers.
     */
    public function type(): string
    {
        return match ($this) {
            self::SourcePort, self::ConnectRetry, self::ReadSourceLogPosition, self::RelayLogPosition, self::LastErrorNumber, self::SkipCounter, self::ExecSourceLogPosition, self::RelayLogSpace, self::UntilLogPosition, self::SecondsBehindSource, self::LastIoErrorNumber, self::LastSqlErrorNumber, self::SourceServerId, self::SqlDelay, self::SqlRemainingDelay, self::SourceRetryCount, self::AutoPosition, self::GetSourcePublicKey => 'bigint',
            default => 'varchar',
        };
    }

    /**
     * Lag and remaining delay are absent while the applier is idle or stopped.
     */
    public function nullability(): Nullability
    {
        return match ($this) {
            self::SecondsBehindSource, self::SqlRemainingDelay => Nullability::MaybeNull,
            default => Nullability::NotNull,
        };
    }
}
