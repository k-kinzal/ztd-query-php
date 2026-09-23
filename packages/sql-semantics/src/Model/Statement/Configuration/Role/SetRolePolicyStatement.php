<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration\Role;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\SessionRolePolicy;
use SqlSemantics\Model\Statement\ConfigurationStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Selects active session roles by a predefined policy.
 * @visibility public
 * @example Binding the role operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SET ROLE NONE");
 *     $statement instanceof \SqlSemantics\Model\Statement\Configuration\Role\SetRolePolicyStatement // => true
 */
final class SetRolePolicyStatement extends ConfigurationStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly SessionRolePolicy $policy)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('MySQL role selection requires MySQL.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Set;
    }

    /**
     * Retains the role request while changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->policy);
    }

    /**
     * Replaces policy in a new validated operation.
     */
    public function withPolicy(SessionRolePolicy $policy): self
    {
        return $this->changed(new self($this->origin, $policy));
    }
}
