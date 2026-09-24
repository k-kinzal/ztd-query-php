<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Replication;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Replication\ReplicaField;
use SqlSemantics\Model\Query\Inspection\ReplicationVocabulary;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Lists the replicas registered with this source; SHOW SLAVE HOSTS returns the same fields under legacy labels.
 * @visibility public
 * @example Inspecting the result labels of both spellings
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build());
 *     array_column($binder->bind('SHOW REPLICAS')->resultColumns(), 'name') // => ['Server_Id', 'Host', 'Port', 'Source_Id', 'Replica_UUID']
 *     array_column($binder->bind('SHOW SLAVE HOSTS')->resultColumns(), 'name') // => ['Server_id', 'Host', 'Port', 'Master_id', 'Slave_UUID']
 */
final class ShowReplicasStatement extends InspectionStatement
{
    /**
     * @param ReplicationVocabulary $vocabulary REPLICAS or SLAVE HOSTS spelling, which selects the result labels
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly ReplicationVocabulary $vocabulary = ReplicationVocabulary::Current)
    {
        if (!$vocabulary->spelledIn($origin->context?->schema()->grammarVersion)) {
            throw new InvalidStructure('The grammar release does not spell replica listings in this vocabulary.');
        }
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->vocabulary);
    }

    /**
     * Spells the request in another vocabulary, which changes the result labels.
     */
    public function withVocabulary(ReplicationVocabulary $vocabulary): self
    {
        return $this->changed(new self($this->origin, $vocabulary));
    }

    /**
     * @return list<OutputColumn> The server, address, source, and identity fields labelled in the request vocabulary
     */
    #[Override]
    public function resultColumns(): array
    {
        $labels = [];
        foreach (ReplicaField::cases() as $field) {
            $labels[$field->label()] = $field->labelIn($this->vocabulary);
        }
        return $this->columns(ReplicaField::cases(), $labels);
    }
}
