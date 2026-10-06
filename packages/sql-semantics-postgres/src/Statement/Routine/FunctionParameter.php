<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One parameter of a routine definition or signature: mode, name, type and default value.
 *
 * Mirrors PostgreSQL's `FunctionParameter` node. The name may be written
 * before or after the mode; both orders declare the same parameter and the
 * order is kept so that the statement is written back as it was. Only input
 * parameters take part in calls; output parameters form the result row.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html.
 *
 * @visibility public
 * @example Reading a named output parameter
 *     $type = new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('text')])));
 *     $parameter = new \SqlSemantics\Platform\PostgreSql\Statement\Routine\FunctionParameter($type, new \SqlSemantics\Statement\Identifier\Name('label'), \SqlSemantics\Platform\PostgreSql\Statement\Routine\ParameterMode::Out);
 *     [$parameter->name?->value, $parameter->input()] // => ['label', false]
 */
final class FunctionParameter implements Clause
{
    use Snapshot;

    /**
     * @param TypeName $type The parameter type
     * @param Name|null $name The parameter name, if written
     * @param ParameterMode|null $mode The written mode; null when no mode is written
     * @param bool $nameFirst Whether the name is written before the mode
     * @param Scalar|null $default The default value, if written
     * @param DefaultSpelling|null $defaultSpelling How the default value is introduced; given exactly with a default value
     */
    public function __construct(
        public readonly TypeName $type,
        public readonly ?Name $name = null,
        public readonly ?ParameterMode $mode = null,
        public readonly bool $nameFirst = false,
        public readonly ?Scalar $default = null,
        public readonly ?DefaultSpelling $defaultSpelling = null,
    ) {
        Check::input(!$nameFirst || ($name !== null && $mode !== null), 'A parameter name is written before the mode only when both are written.');
        Check::input(($default === null) === ($defaultSpelling === null), 'A default spelling is given exactly with a default value.');
    }

    /**
     * Tells whether the caller passes a value for the parameter.
     */
    public function input(): bool
    {
        return $this->mode === null || $this->mode->input();
    }

    /**
     * Tells whether the parameter is part of the result.
     */
    public function output(): bool
    {
        return $this->mode !== null && $this->mode->output();
    }

    /**
     * Derives the type modifiers and the default value in the given environment.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->type->deriveClause($derivation, $environment);
        if ($this->default !== null) {
            $derivation->scalar($this->default, $environment);
        }
    }

    /**
     * Writes the mode and the name in their written order, the type and the default value.
     */
    public function render(Output $out): void
    {
        if ($this->nameFirst && $this->name !== null) {
            $out->name($this->name, NameUse::Routine);
        }
        if ($this->mode !== null) {
            $out->keyword(...$this->mode->keywords());
        }
        if (!$this->nameFirst && $this->name !== null) {
            $out->name($this->name, NameUse::Routine);
        }
        $out->node($this->type);
        if ($this->default !== null && $this->defaultSpelling !== null) {
            match ($this->defaultSpelling) {
                DefaultSpelling::Keyword => $out->keyword('DEFAULT'),
                DefaultSpelling::EqualsSign => $out->symbol('='),
            };
            $out->node($this->default);
        }
    }
}
