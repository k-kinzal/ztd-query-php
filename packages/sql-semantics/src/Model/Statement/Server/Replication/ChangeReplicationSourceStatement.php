<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\Replication;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Replication\ReplicaChannel;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Configuration\Replication\Source\SourceSetting;
use SqlSemantics\Model\Configuration\Replication\Source\SourceSettings;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Changes the connection and position a replica uses for its source (CHANGE REPLICATION SOURCE TO, or CHANGE MASTER TO before MySQL 8.4).
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'db1', SOURCE_PORT = 3306 FOR CHANNEL 'east'");
 *     [$statement->settings[1]->option()->value, $statement->channel] // => ['SOURCE_PORT', 'east']
 */
final class ChangeReplicationSourceStatement extends BoundStatement
{
    /**
     * @var non-empty-list<SourceSetting>
     */
    public readonly array $settings;

    /**
     * @param list<SourceSetting> $settings Option assignments, each option at most once; at least one
     * @param ?string $channel The FOR CHANNEL name (MySQL 5.7 and later); null names the default channel
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, array $settings, public readonly ?string $channel = null)
    {
        ReplicationRelease::require($origin, 'CHANGE REPLICATION SOURCE');
        ReplicaChannel::check($origin, $channel);
        SourceSettings::check($origin, $settings);
        $this->settings = Collections::nonEmpty($settings);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Change;
    }

    /**
     * Retains the settings and channel when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->settings, $this->channel);
    }

    /**
     * Replaces the option assignments.
     * @param non-empty-list<SourceSetting> $settings
     */
    public function withSettings(array $settings): self
    {
        return $this->changed(new self($this->origin, $settings, $this->channel));
    }

    /**
     * Replaces the channel; null names the default channel.
     */
    public function withChannel(?string $channel): self
    {
        return $this->changed(new self($this->origin, $this->settings, $channel));
    }
}
