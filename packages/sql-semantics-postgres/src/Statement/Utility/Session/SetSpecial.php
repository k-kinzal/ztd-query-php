<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Session;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
use SqlSemantics\Platform\PostgreSql\Statement\Option\XmlOption;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblemKind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A SET written with keywords of its own: CATALOG, SCHEMA, NAMES, ROLE, SESSION AUTHORIZATION, XML OPTION or TRANSACTION SNAPSHOT.
 *
 * Rule: PG-SET-004. Mirrors the keyword forms of PostgreSQL's
 * `VariableSetStmt`. CATALOG, SCHEMA and TRANSACTION SNAPSHOT take a string;
 * ROLE takes a word or a string; SESSION AUTHORIZATION takes a word, a string
 * or DEFAULT; NAMES takes a string, DEFAULT or nothing, the last two both
 * restoring the default encoding; XML OPTION takes DOCUMENT or CONTENT.
 * Diagnostic: SET CATALOG is rejected by the server while parsing.
 * Source: https://www.postgresql.org/docs/17/sql-set.html, https://www.postgresql.org/docs/17/sql-set-role.html,
 * https://www.postgresql.org/docs/17/sql-set-session-authorization.html, https://www.postgresql.org/docs/17/sql-set-transaction.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading SET SESSION AUTHORIZATION DEFAULT
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET SESSION AUTHORIZATION DEFAULT');
 *     [$operation->statement->setting, $operation->statement->default, $operation->statement->value] // => [\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialSetting::SessionAuthorization, true, null]
 * @example Refusing a schema that is not a string
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetSpecial(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialSetting::Schema, null) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class SetSpecial implements Statement
{
    use Snapshot;

    /**
     * @param SpecialSetting $setting The setting
     * @param StringConstant|Word|XmlOption|null $value The value; null when DEFAULT or nothing is written
     * @param bool $default Whether DEFAULT is written in place of a value
     * @param bool $local Whether the change lasts for the current transaction only
     */
    public function __construct(
        public readonly SpecialSetting $setting,
        public readonly StringConstant|Word|XmlOption|null $value,
        public readonly bool $default = false,
        public readonly bool $local = false,
    ) {
        Check::input(!$default || $value === null, 'DEFAULT is written in place of a value.');
        Check::input(match ($setting) {
            SpecialSetting::Catalog, SpecialSetting::Schema, SpecialSetting::TransactionSnapshot => $value instanceof StringConstant,
            SpecialSetting::Names => $value === null || $value instanceof StringConstant,
            SpecialSetting::Role => $value instanceof StringConstant || $value instanceof Word,
            SpecialSetting::SessionAuthorization => $value instanceof StringConstant || $value instanceof Word || $default,
            SpecialSetting::XmlOption => $value instanceof XmlOption,
        }, 'The value does not fit the setting.');
    }

    /**
     * Reports SET CATALOG, which the server refuses.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        if ($this->setting === SpecialSetting::Catalog) {
            $derivation->report(new UtilityProblem(UtilityProblemKind::CatalogChange));
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('SET');
        if ($this->local) {
            $out->keyword('LOCAL');
        }
        $out->keyword(...explode(' ', $this->setting->value));
        if ($this->default) {
            $out->keyword('DEFAULT');
        }
        if ($this->value instanceof XmlOption) {
            $out->keyword($this->value->value);
        } elseif ($this->value !== null) {
            $out->node($this->value);
        }
    }
}
