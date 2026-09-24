<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration;

use Override;
use SqlSemantics\Model\Statement\ConfigurationStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * Typed SetStatement operation.
 * @visibility public
  * @example Inspecting SetStatement
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("SET LOCAL work_mem='64MB'");
 *     $statement instanceof \SqlSemantics\Model\Statement\Configuration\SetStatement // => true
 */
final class SetStatement extends ConfigurationStatement
{
    /**
     * @var non-empty-list<\SqlSemantics\Model\Configuration\DefaultSetting|\SqlSemantics\Model\Configuration\AssignedUserVariable|\SqlSemantics\Model\Configuration\AssignedSetting|\SqlSemantics\Model\Configuration\CurrentSetting|\SqlSemantics\Model\Configuration\Connection\ConnectionNames|\SqlSemantics\Model\Configuration\Connection\ConnectionCharacterSet> Validated ordered operands
     */
    public readonly array $settings;

    /**
     * @param list<\SqlSemantics\Model\Configuration\DefaultSetting|\SqlSemantics\Model\Configuration\AssignedUserVariable|\SqlSemantics\Model\Configuration\AssignedSetting|\SqlSemantics\Model\Configuration\CurrentSetting|\SqlSemantics\Model\Configuration\Connection\ConnectionNames|\SqlSemantics\Model\Configuration\Connection\ConnectionCharacterSet> $settings
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(Origin $origin, array $settings)
    {
        if ($settings === []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A SetStatement requires ordered settings.');
        }
        \SqlSemantics\Model\Validation\Collections::alternatives($settings, [\SqlSemantics\Model\Configuration\DefaultSetting::class, \SqlSemantics\Model\Configuration\AssignedUserVariable::class, \SqlSemantics\Model\Configuration\AssignedSetting::class, \SqlSemantics\Model\Configuration\CurrentSetting::class, \SqlSemantics\Model\Configuration\Connection\ConnectionNames::class, \SqlSemantics\Model\Configuration\Connection\ConnectionCharacterSet::class]);
        foreach ($settings as $setting) {
            if (($setting instanceof \SqlSemantics\Model\Configuration\Connection\ConnectionNames || $setting instanceof \SqlSemantics\Model\Configuration\Connection\ConnectionCharacterSet) && $origin->dialect !== \SqlSemantics\Dialect::MySql) {
                throw new \SqlSemantics\Model\Validation\InvalidStructure('SET NAMES and SET CHARACTER SET items require MySQL.');
            }
        }
        parent::__construct($origin);
        $this->settings = \SqlSemantics\Model\Validation\Collections::nonEmpty($settings);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Set;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->settings);
    }

    /**

     * @return list<\SqlSemantics\Model\Configuration\DefaultSetting|\SqlSemantics\Model\Configuration\AssignedUserVariable|\SqlSemantics\Model\Configuration\AssignedSetting>

     */
    #[Override]
    public function assignments(): array
    {
        return array_values(array_filter($this->settings, static fn ($setting): bool => $setting instanceof \SqlSemantics\Model\Configuration\DefaultSetting || $setting instanceof \SqlSemantics\Model\Configuration\AssignedUserVariable || $setting instanceof \SqlSemantics\Model\Configuration\AssignedSetting));
    }

    /**

     * @param non-empty-list<\SqlSemantics\Model\Expression> $values

     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function withValues(\SqlSemantics\Model\Configuration\AssignedSetting $setting, array $values): self
    {
        if (!in_array($setting, $this->assignments(), true)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('The setting does not belong to this statement.');
        }
        $replacement = new \SqlSemantics\Model\Configuration\AssignedSetting($setting->name, $setting->scope, $setting->source, $values);
        $settings = array_map(static fn ($current) => $current === $setting ? $replacement : $current, $this->settings);
        return $this->changed(new self($this->origin, $settings));
    }

    /**
     * Replaces one user-variable value, preserving the target and assignment order.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function withVariableValue(\SqlSemantics\Model\Configuration\AssignedUserVariable $assignment, \SqlSemantics\Model\Expression $value): self
    {
        if (!in_array($assignment, $this->settings, true)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('The variable assignment does not belong to this statement.');
        }
        $replacement = new \SqlSemantics\Model\Configuration\AssignedUserVariable($assignment->target, $value, $assignment->source);
        $settings = array_map(static fn ($current) => $current === $assignment ? $replacement : $current, $this->settings);
        return $this->changed(new self($this->origin, $settings));
    }
}
