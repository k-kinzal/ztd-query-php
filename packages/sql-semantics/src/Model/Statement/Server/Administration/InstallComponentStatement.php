<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\Administration;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Configuration\Replication\ReplicationText;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Loads server components by URN and optionally initializes their system variables.
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("INSTALL COMPONENT 'file://component_validate_password' SET GLOBAL validate_password.length = 12");
 *     $statement->settings[0]->name // => ['validate_password', 'length']
 */
final class InstallComponentStatement extends BoundStatement
{
    /**
     * @var non-empty-list<Literal>
     */
    public readonly array $components;

    /**
     * @param list<Literal> $components Component URNs in request order; at least one
     * @param list<AssignedSetting> $settings GLOBAL or PERSIST variable assignments with one value each
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, array $components, public readonly array $settings = [])
    {
        ReplicationRelease::require($origin, 'INSTALL COMPONENT', 80000);
        Collections::objects($components, Literal::class);
        Collections::objects($settings, AssignedSetting::class);
        foreach ($components as $component) {
            ReplicationText::check($component, 'A component URN');
        }
        foreach ($settings as $setting) {
            if (!in_array($setting->scope, [SettingScope::Global, SettingScope::Persist], true) || count($setting->values) !== 1 || count($setting->name) > 2) {
                throw new InvalidStructure('A component variable assignment is GLOBAL or PERSIST, names at most two parts and has one value.');
            }
        }
        if ($settings !== [] && (ReplicationRelease::of($origin) ?? PHP_INT_MAX) < 80033) {
            throw new InvalidStructure('Component variable assignments require MySQL 8.0.33 or later.');
        }
        $this->components = Collections::nonEmpty($components);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Install;
    }

    /**
     * Retains the components and assignments when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->components, $this->settings);
    }

    /**
     * Replaces the component URNs.
     * @param non-empty-list<Literal> $components
     */
    public function withComponents(array $components): self
    {
        return $this->changed(new self($this->origin, $components, $this->settings));
    }

    /**
     * Replaces the variable assignments.
     * @param list<AssignedSetting> $settings
     */
    public function withSettings(array $settings): self
    {
        return $this->changed(new self($this->origin, $this->components, $settings));
    }
}
