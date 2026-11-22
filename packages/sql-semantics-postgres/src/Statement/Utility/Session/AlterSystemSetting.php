<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Session;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;

/**
 * `ALTER SYSTEM SET ...` or `ALTER SYSTEM RESET ...`: a request to change the server configuration file.
 *
 * Rule: PG-ALTER-SYSTEM-001. Mirrors PostgreSQL's `AlterSystemStmt`, which
 * holds a SET or RESET: a parameter set to values or to DEFAULT, or a
 * parameter or ALL reset. The setting is derived as the request it is.
 * Source: https://www.postgresql.org/docs/17/sql-altersystem.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing a transaction-local setting
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\AlterSystemSetting(new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetParameterFrom(
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterName([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a')]),
 *         \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterSource::Default,
 *         true,
 *     )) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class AlterSystemSetting implements Statement
{
    use Snapshot;

    /**
     * @param SetParameter|SetParameterFrom|Reset $setting The SET or RESET applied to the configuration file
     */
    public function __construct(public readonly SetParameter|SetParameterFrom|Reset $setting)
    {
        Check::input($setting instanceof Reset || !$setting->local, 'ALTER SYSTEM takes no LOCAL.');
        Check::input(!$setting instanceof SetParameterFrom || $setting->source === ParameterSource::Default, 'ALTER SYSTEM takes no FROM CURRENT.');
        Check::input(!$setting instanceof Reset || $setting->parameter instanceof ParameterName || $setting->parameter === SpecialParameter::All, 'ALTER SYSTEM resets a named parameter or ALL.');
    }

    /**
     * Derives the SET or RESET.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->statement($this->setting);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'SYSTEM')->node($this->setting);
    }
}
