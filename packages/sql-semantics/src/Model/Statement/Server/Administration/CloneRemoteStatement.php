<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\Administration;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Administration\CloneEncryption;
use SqlSemantics\Model\Configuration\Replication\ReplicationNumber;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Configuration\Replication\ReplicationText;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Copies the data of a donor MySQL instance to this server (CLONE INSTANCE FROM).
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CLONE INSTANCE FROM 'donor'@'db1':3306 IDENTIFIED BY 'secret' REQUIRE SSL");
 *     [$statement->donor->host, $statement->port->text] // => ['db1', '3306']
 */
final class CloneRemoteStatement extends BoundStatement
{
    /**
     * A missing directory replaces this server's data; a missing encryption clause follows the clone_ssl setting.
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly AccountName|CurrentAccount $donor,
        public readonly Literal $port,
        public readonly Literal $password,
        public readonly ?Literal $directory = null,
        public readonly ?CloneEncryption $encryption = null,
    ) {
        ReplicationRelease::require($origin, 'CLONE INSTANCE', 80000);
        ReplicationNumber::check($port, 'A clone donor port');
        ReplicationText::check($password, 'A clone donor password');
        if ($directory !== null) {
            ReplicationText::check($directory, 'A clone data directory');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Clone;
    }

    /**
     * Retains the donor, credentials and destination when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->donor, $this->port, $this->password, $this->directory, $this->encryption);
    }

    /**
     * Replaces the donor account and its port.
     */
    public function withDonor(AccountName|CurrentAccount $donor, Literal $port): self
    {
        return $this->changed(new self($this->origin, $donor, $port, $this->password, $this->directory, $this->encryption));
    }

    /**
     * Replaces the donor account password.
     */
    public function withPassword(Literal $password): self
    {
        return $this->changed(new self($this->origin, $this->donor, $this->port, $password, $this->directory, $this->encryption));
    }

    /**
     * Replaces the destination directory; null replaces this server's own data.
     */
    public function withDirectory(?Literal $directory): self
    {
        return $this->changed(new self($this->origin, $this->donor, $this->port, $this->password, $directory, $this->encryption));
    }

    /**
     * Replaces the explicit encryption requirement.
     */
    public function withEncryption(?CloneEncryption $encryption): self
    {
        return $this->changed(new self($this->origin, $this->donor, $this->port, $this->password, $this->directory, $encryption));
    }
}
