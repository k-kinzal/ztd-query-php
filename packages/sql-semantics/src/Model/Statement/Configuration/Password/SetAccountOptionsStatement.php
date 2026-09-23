<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration\Password;

use Override;
use SqlSemantics\Model\Configuration\Account\PasswordOperands;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\Model\Statement\ConfigurationStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Ordered MySQL 5.6 SET clauses containing account credentials and optional variable assignments.
 * @visibility public
 * @example Inspecting a mixed credential request
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SET PASSWORD = PASSWORD('new'), PASSWORD FOR 'u' = '*hash'");
 *     count($statement->operations) // => 2
 */
final class SetAccountOptionsStatement extends ConfigurationStatement
{
    /**
     * @param non-empty-list<SetPasswordHashStatement|SetDerivedPasswordStatement|SetStatement> $operations Ordered SET clauses, each with its own required operands
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $operations)
    {
        PasswordOperands::validate($origin);
        Collections::alternatives($operations, [SetPasswordHashStatement::class, SetDerivedPasswordStatement::class, SetStatement::class]);
        if (count($operations) < 2 || array_filter($operations, static fn ($operation): bool => !$operation instanceof SetStatement) === []) {
            throw new InvalidStructure('A mixed account SET requires at least two clauses including a password request.');
        }
        foreach ($operations as $operation) {
            PasswordOperands::validate($operation->origin);
            if ($operation instanceof SetStatement && count($operation->settings) !== 1) {
                throw new InvalidStructure('Each variable clause must contain exactly one assignment.');
            }
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Set;
    }

    /**
     * Retains ordered clauses when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->operations);
    }

    /**
     * @param non-empty-list<SetPasswordHashStatement|SetDerivedPasswordStatement|SetStatement> $operations
     */
    public function withOperations(array $operations): self
    {
        return $this->changed(new self($this->origin, $operations));
    }
}
