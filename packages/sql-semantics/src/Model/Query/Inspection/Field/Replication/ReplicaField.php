<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Replication;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\ReplicationVocabulary;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result fields of SHOW REPLICAS; labels follow the request vocabulary.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Replication\ReplicaField::ServerId->label() // => 'Server_Id'
 */
enum ReplicaField: string implements MetadataField
{
    use TextField;

    case ServerId = 'Server_Id';
    case Host = 'Host';
    case Port = 'Port';
    case SourceId = 'Source_Id';
    case Uuid = 'Replica_UUID';

    /**
     * Returns the result label in the vocabulary the request was written in.
     */
    public function labelIn(ReplicationVocabulary $vocabulary): string
    {
        if ($vocabulary === ReplicationVocabulary::Current) {
            return $this->value;
        }
        return match ($this) {
            self::ServerId => 'Server_id',
            self::SourceId => 'Master_id',
            self::Host, self::Port, self::Uuid => $vocabulary->label($this->value),
        };
    }

    /**
     * Identities and the port are integers.
     */
    public function type(): string
    {
        return match ($this) {
            self::ServerId, self::Port, self::SourceId => 'bigint',
            self::Host, self::Uuid => 'varchar',
        };
    }
}
