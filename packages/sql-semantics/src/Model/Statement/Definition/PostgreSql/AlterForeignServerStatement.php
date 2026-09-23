<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Foreign\AddForeignOption;
use SqlSemantics\Model\Definition\Foreign\DropForeignOption;
use SqlSemantics\Model\Definition\Foreign\ServerInvariant;
use SqlSemantics\Model\Definition\Foreign\ServerVersionChange;
use SqlSemantics\Model\Definition\Foreign\SetForeignOption;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Changes a server's version and/or connection options without applying the request.
 * @visibility public
 * @example Rejecting an empty alteration
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER SERVER remote VERSION NULL');
 *     $statement->withChanges(\SqlSemantics\Model\Definition\Foreign\ServerVersionChange::Keep, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterForeignServerStatement extends BoundStatement
{
    /**
     * @param list<AddForeignOption|SetForeignOption|DropForeignOption> $options Ordered changes to existing options
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly Literal|ServerVersionChange $version = ServerVersionChange::Keep,
        public readonly array $options = [],
    ) {
        ServerInvariant::target($origin, $name);
        ServerInvariant::text($version);
        Collections::alternatives($options, [AddForeignOption::class, SetForeignOption::class, DropForeignOption::class]);
        if ($version === ServerVersionChange::Keep && $options === []) {
            throw new InvalidStructure('A foreign server alteration requires a version or option change.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the changes while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->version, $this->options);
    }

    /**
     * Replaces the target server name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->version, $this->options));
    }

    /**
     * Replaces the version and option changes together, requiring at least one change.
     * @param list<AddForeignOption|SetForeignOption|DropForeignOption> $options Ordered option changes
     */
    public function withChanges(Literal|ServerVersionChange $version, array $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $version, $options));
    }
}
