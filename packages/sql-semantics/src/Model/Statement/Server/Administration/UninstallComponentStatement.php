<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\Administration;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Configuration\Replication\ReplicationText;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Unloads server components by URN.
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("UNINSTALL COMPONENT 'file://component_validate_password'");
 *     $statement->components[0]->text // => "'file://component_validate_password'"
 */
final class UninstallComponentStatement extends BoundStatement
{
    /**
     * @var non-empty-list<Literal>
     */
    public readonly array $components;

    /**
     * @param list<Literal> $components Component URNs in request order; at least one
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, array $components)
    {
        ReplicationRelease::require($origin, 'UNINSTALL COMPONENT', 80000);
        Collections::objects($components, Literal::class);
        foreach ($components as $component) {
            ReplicationText::check($component, 'A component URN');
        }
        $this->components = Collections::nonEmpty($components);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Uninstall;
    }

    /**
     * Retains the components when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->components);
    }

    /**
     * Replaces the component URNs.
     * @param non-empty-list<Literal> $components
     */
    public function withComponents(array $components): self
    {
        return $this->changed(new self($this->origin, $components));
    }
}
