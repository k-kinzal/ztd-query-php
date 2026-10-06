<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Session;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `ALTER SYSTEM SET ...` or `ALTER SYSTEM RESET ...`: a request to change the server configuration file.
 *
 * Rule: PG-ALTER-SYSTEM-001. Mirrors PostgreSQL's `AlterSystemStmt`, which
 * holds a SET or RESET: a parameter set to values or to DEFAULT, or a
 * parameter or ALL reset. The setting is derived as the request it is.
 * Source: https://www.postgresql.org/docs/17/sql-altersystem.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the setting of ALTER SYSTEM
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SYSTEM SET wal_level = replica');
 *     [$operation->statement->setting->parameter->text(), $operation->toString()] // => ['wal_level', 'ALTER SYSTEM SET wal_level TO replica']
 * @example Refusing a transaction-local setting
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\AlterSystemSetting(new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetParameterFrom(
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterName([new \SqlSemantics\Statement\Identifier\Name('a')]),
 *         \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterSource::Default,
 *         true,
 *     )) // throws \SqlSemantics\Diagnostic\InvalidConstruction
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
