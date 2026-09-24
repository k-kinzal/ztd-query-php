<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Replication;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Replication\Subscription\SubscriptionInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Skips applying the remote transaction that finishes at a log sequence number, or clears a previous skip with NONE.
 * @visibility public
 * @example Reading a skipped transaction
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("ALTER SUBSCRIPTION sub SKIP (lsn = '0/14c0378')");
 *     $statement->lsn // => '0/14C0378'
 *     $statement->toString() // => 'ALTER SUBSCRIPTION "sub" SKIP(lsn = \'0/14C0378\')'
 */
final class SkipSubscriptionTransactionStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly ?string $lsn,
    ) {
        SubscriptionInvariant::identity($origin, $name);
        if ($lsn !== null && (preg_match('/^[0-9A-F]{1,8}\/[0-9A-F]{1,8}$/D', $lsn) !== 1 || preg_match('/^0+\/0+$/D', $lsn) === 1)) {
            throw new InvalidStructure('A skipped log sequence number is a nonzero upper-case hexadecimal X/X position.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->lsn);
    }

    /**
     * Replaces the subscription name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->lsn));
    }

    /**
     * Replaces the finishing log sequence number, or clears the skip with null.
     */
    public function withLsn(?string $lsn): self
    {
        return $this->changed(new self($this->origin, $this->name, $lsn));
    }
}
