<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Foreign\AddForeignOption;
use SqlSemantics\Model\Definition\Foreign\DropForeignOption;
use SqlSemantics\Model\Definition\Foreign\FunctionChange;
use SqlSemantics\Model\Definition\Foreign\SetForeignOption;
use SqlSemantics\Model\Definition\Foreign\WrapperInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests explicit changes to an existing wrapper without applying them.
 * @visibility public
 * @example Inspecting the named wrapper
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER FOREIGN DATA WRAPPER fdw NO HANDLER');
 *     $statement->name // => 'fdw'
 * @example Rejecting an alteration without a requested change
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER FOREIGN DATA WRAPPER fdw NO HANDLER');
 *     $statement->withChanges(\SqlSemantics\Model\Definition\Foreign\FunctionChange::Keep, \SqlSemantics\Model\Definition\Foreign\FunctionChange::Keep, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterForeignDataWrapperStatement extends BoundStatement
{
    /**
     * @param list<AddForeignOption|SetForeignOption|DropForeignOption> $options Ordered option changes
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly QualifiedName|FunctionChange $handler = FunctionChange::Keep,
        public readonly QualifiedName|FunctionChange $validator = FunctionChange::Keep,
        public readonly array $options = [],
    ) {
        WrapperInvariant::target($origin, $name);
        WrapperInvariant::functionName($handler);
        WrapperInvariant::functionName($validator);
        Collections::alternatives($options, [AddForeignOption::class, SetForeignOption::class, DropForeignOption::class]);
        if ($handler === FunctionChange::Keep && $validator === FunctionChange::Keep && $options === []) {
            throw new InvalidStructure('A foreign-data wrapper alteration requires at least one change.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains all wrapper operands while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->handler, $this->validator, $this->options);
    }

    /**
     * Replaces the wrapper identity in a separately validated statement.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->handler, $this->validator, $this->options));
    }

    /**
     * Atomically replaces function changes and option changes, requiring at least one operation.
     * @param list<AddForeignOption|SetForeignOption|DropForeignOption> $options Ordered option changes
     */
    public function withChanges(QualifiedName|FunctionChange $handler, QualifiedName|FunctionChange $validator, array $options): self
    {
        return $this->changed(new self($this->origin, $this->name, $handler, $validator, $options));
    }
}
