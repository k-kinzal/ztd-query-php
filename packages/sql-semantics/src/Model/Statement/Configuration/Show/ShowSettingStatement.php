<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration\Show;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Setting\SettingColumn;
use SqlSemantics\Model\Query\Inspection\Setting\SettingField;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Displays the current value of one PostgreSQL run-time parameter as a single text column; the value is not read by binding.
 * SHOW TIME ZONE, SHOW TRANSACTION ISOLATION LEVEL and SHOW SESSION AUTHORIZATION name the parameters timezone, transaction_isolation and session_authorization.
 * @visibility public
 * @example Reading the displayed parameter
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SHOW TIME ZONE');
 *     [$statement->name, (new \SqlSemantics\SimpleSerializer())->serialize($statement)] // => [['timezone'], 'SHOW "timezone"']
 * @example Reading a dotted custom parameter
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SHOW app.mode');
 *     $statement->resultColumns()[0]->name // => 'app.mode'
 */
final class ShowSettingStatement extends BoundStatement implements ResultStatement
{
    /**
     * @param non-empty-list<string> $name Parameter name parts; a custom parameter has a dotted prefix
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $name)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('SHOW of a run-time parameter requires PostgreSQL.');
        }
        Collections::nonEmpty($name);
        if (count($name) === 1 && strtolower($name[0]) === 'all') {
            throw new InvalidStructure('The parameter name all displays every parameter; use ShowAllSettingsStatement.');
        }
        foreach ($name as $part) {
            if ($part === '') {
                throw new InvalidStructure('A displayed parameter name part requires at least one character.');
            }
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Show;
    }

    /**
     * Retains the displayed parameter while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name);
    }

    /**
     * Displays another parameter.
     * @param non-empty-list<string> $name
     */
    public function withName(array $name): self
    {
        return $this->changed(new self($this->origin, $name));
    }

    /**
     * @return list<OutputColumn> One text column labeled with the requested parameter name
     */
    #[Override]
    public function resultColumns(): array
    {
        $label = implode('.', $this->name);
        return [new OutputColumn(0, $label, new SettingColumn($this->source, $this->scopeId, SettingField::Setting, $label))];
    }
}
