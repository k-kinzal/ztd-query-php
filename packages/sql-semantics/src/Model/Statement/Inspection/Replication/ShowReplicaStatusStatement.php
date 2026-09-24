<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Replication;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Replication\ReplicaStatusField;
use SqlSemantics\Model\Query\Inspection\ReplicationVocabulary;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reports the state of the replica threads of one channel or of every channel; SHOW SLAVE STATUS returns the same fields under legacy labels.
 * @visibility public
 * @example Inspecting the vocabulary and channel
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build()))->bind("SHOW SLAVE STATUS FOR CHANNEL 'east'");
 *     [$statement->vocabulary->value, $statement->channel, $statement->resultColumns()[1]->name] // => ['legacy', 'east', 'Master_Host']
 */
final class ShowReplicaStatusStatement extends InspectionStatement
{
    /**
     * @param ReplicationVocabulary $vocabulary REPLICA or SLAVE spelling, which selects the result labels
     * @param string|null $channel Replication channel; null reports every channel
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly ReplicationVocabulary $vocabulary = ReplicationVocabulary::Current, public readonly ?string $channel = null)
    {
        if (!$vocabulary->spelledIn($origin->context?->schema()->grammarVersion)) {
            throw new InvalidStructure('The grammar release does not spell replica status requests in this vocabulary.');
        }
        ReplicationChannel::check($origin, $channel);
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->vocabulary, $this->channel);
    }

    /**
     * Spells the request in another vocabulary, which changes the result labels.
     */
    public function withVocabulary(ReplicationVocabulary $vocabulary): self
    {
        return $this->changed(new self($this->origin, $vocabulary, $this->channel));
    }

    /**
     * Reports another channel; null reports every channel.
     */
    public function withChannel(?string $channel): self
    {
        return $this->changed(new self($this->origin, $this->vocabulary, $channel));
    }

    /**
     * @return list<OutputColumn> The fields the grammar release reports, labelled in the request vocabulary
     */
    #[Override]
    public function resultColumns(): array
    {
        $fields = ReplicaStatusField::reported($this->origin->context?->schema()->grammarVersion);
        $labels = [];
        foreach ($fields as $field) {
            $labels[$field->label()] = $field->labelIn($this->vocabulary);
        }
        return $this->columns($fields, $labels);
    }
}
