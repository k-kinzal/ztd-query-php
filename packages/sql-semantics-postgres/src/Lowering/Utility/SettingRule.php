<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Utility;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\TypedLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\AlterSystem;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\Discard;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\DiscardTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterName;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterSource;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\Reset;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetParameter;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetParameterFrom;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetSpecial;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetTimeZone;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\Show;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialParameter;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialSetting;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ZoneKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SetConstraints;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SetTransaction;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\TransactionScope;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the configuration commands: SET, RESET, SHOW, ALTER SYSTEM, SET CONSTRAINTS and DISCARD.
 *
 * Rule: PG-SETTING-LOWER-001. Scope: `VariableSetStmt`, `set_rest`,
 * `generic_set`, `set_rest_more`, `zone_value`, `opt_encoding`,
 * `VariableResetStmt`, `reset_rest`, `generic_reset`, `SetResetClause`,
 * `FunctionSetResetClause`, `VariableShowStmt`, `AlterSystemStmt`,
 * `ConstraintsSetStmt`, `constraints_set_list`, `constraints_set_mode`,
 * `DiscardStmt`. Constructors: `SetParameter`, `SetParameterFrom`,
 * `SetTimeZone`, `SetSpecial`, `SetTransaction`, `Reset`, `Show`,
 * `AlterSystem`, `SetConstraints`, `Discard`. `TO` and `=`, and SESSION
 * after SET, are noise (UtilityNoise). Termination: one pass; lists are
 * flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-set.html, https://www.postgresql.org/docs/17/sql-reset.html,
 * https://www.postgresql.org/docs/17/sql-show.html, https://www.postgresql.org/docs/17/sql-altersystem.html,
 * https://www.postgresql.org/docs/17/sql-set-constraints.html, https://www.postgresql.org/docs/17/sql-discard.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class SettingRule
{
    /**
     * The keyword forms of RESET and SHOW.
     */
    private const SPECIAL = [
        'reset_rest: TIME ZONE' => SpecialParameter::TimeZone,
        'reset_rest: TRANSACTION ISOLATION LEVEL' => SpecialParameter::TransactionIsolationLevel,
        'reset_rest: SESSION AUTHORIZATION' => SpecialParameter::SessionAuthorization,
        'generic_reset: ALL' => SpecialParameter::All,
        'VariableShowStmt: SHOW TIME ZONE' => SpecialParameter::TimeZone,
        'VariableShowStmt: SHOW TRANSACTION ISOLATION LEVEL' => SpecialParameter::TransactionIsolationLevel,
        'VariableShowStmt: SHOW SESSION AUTHORIZATION' => SpecialParameter::SessionAuthorization,
        'VariableShowStmt: SHOW ALL' => SpecialParameter::All,
    ];

    /**
     * What each DISCARD production releases.
     */
    private const DISCARDED = [
        'DiscardStmt: DISCARD ALL' => DiscardTarget::All,
        'DiscardStmt: DISCARD TEMP' => DiscardTarget::Temp,
        'DiscardStmt: DISCARD TEMPORARY' => DiscardTarget::Temporary,
        'DiscardStmt: DISCARD PLANS' => DiscardTarget::Plans,
        'DiscardStmt: DISCARD SEQUENCES' => DiscardTarget::Sequences,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a configuration command.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);

        return match ($form->signature) {
            'VariableSetStmt: SET set_rest' => $this->set($form->node(1), false),
            'VariableSetStmt: SET LOCAL set_rest' => $this->set($form->node(2), true),
            'VariableSetStmt: SET SESSION set_rest' => $this->set($form->node(2), false),
            'VariableResetStmt: RESET reset_rest' => $this->reset($form->node(1)),
            'VariableShowStmt: SHOW var_name' => new Show($this->parameter($form->node(1))),
            'AlterSystemStmt: ALTER SYSTEM_P SET generic_set' => new AlterSystem($this->generic($form->node(3), false)),
            'AlterSystemStmt: ALTER SYSTEM_P RESET generic_reset' => new AlterSystem($this->reset($form->node(3))),
            'ConstraintsSetStmt: SET CONSTRAINTS constraints_set_list constraints_set_mode' => new SetConstraints($this->constraints($form->node(2)), $this->deferred($form->node(3))),
            default => match (true) {
                isset(self::SPECIAL[$form->signature]) => new Show(self::SPECIAL[$form->signature]),
                isset(self::DISCARDED[$form->signature]) => new Discard(self::DISCARDED[$form->signature]),
                default => throw ImplementationGap::production($form),
            },
        };
    }

    /**
     * Lowers `SetResetClause` or `FunctionSetResetClause`: the SET or RESET attached to a role, a database or a routine.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function clause(Node $clause): Statement
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'SetResetClause: SET set_rest' => $this->set($form->node(1), false),
            'FunctionSetResetClause: SET set_rest_more' => $this->more($form->node(1), false),
            'SetResetClause: VariableResetStmt', 'FunctionSetResetClause: VariableResetStmt' => $this->statement($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `set_rest`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function set(Node $rest, bool $local): Statement
    {
        $form = $this->lowering->productions->form($rest);
        $transactions = new TransactionRule($this->lowering);

        return match ($form->signature) {
            'set_rest: TRANSACTION transaction_mode_list' => new SetTransaction(TransactionScope::Transaction, $transactions->modes($form->node(1)), $local),
            'set_rest: SESSION CHARACTERISTICS AS TRANSACTION transaction_mode_list' => new SetTransaction(TransactionScope::SessionCharacteristics, $transactions->modes($form->node(4)), $local),
            'set_rest: set_rest_more' => $this->more($form->node(0), $local),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `set_rest_more`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function more(Node $rest, bool $local): Statement
    {
        $form = $this->lowering->productions->form($rest);
        $literals = $this->lowering->literals;
        $options = $this->lowering->options;

        return match ($form->signature) {
            'set_rest_more: generic_set' => $this->generic($form->node(0), $local),
            'set_rest_more: var_name FROM CURRENT_P' => new SetParameterFrom($this->parameter($form->node(0)), ParameterSource::Current, $local),
            'set_rest_more: TIME ZONE zone_value' => new SetTimeZone($this->zone($form->node(2)), $local),
            'set_rest_more: CATALOG_P Sconst' => new SetSpecial(SpecialSetting::Catalog, $literals->string($form->node(1)), false, $local),
            'set_rest_more: SCHEMA Sconst' => new SetSpecial(SpecialSetting::Schema, $literals->string($form->node(1)), false, $local),
            'set_rest_more: NAMES opt_encoding' => $this->names($form->node(1), $local),
            'set_rest_more: ROLE NonReservedWord_or_Sconst' => new SetSpecial(SpecialSetting::Role, $options->wordOrString($form->node(1)), false, $local),
            'set_rest_more: SESSION AUTHORIZATION NonReservedWord_or_Sconst' => new SetSpecial(SpecialSetting::SessionAuthorization, $options->wordOrString($form->node(2)), false, $local),
            'set_rest_more: SESSION AUTHORIZATION DEFAULT' => new SetSpecial(SpecialSetting::SessionAuthorization, null, true, $local),
            'set_rest_more: XML_P OPTION document_or_content' => new SetSpecial(SpecialSetting::XmlOption, $this->lowering->flags->xmlOption($form->node(2)), false, $local),
            'set_rest_more: TRANSACTION SNAPSHOT Sconst' => new SetSpecial(SpecialSetting::TransactionSnapshot, $literals->string($form->node(2)), false, $local),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `generic_set`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function generic(Node $set, bool $local): SetParameter|SetParameterFrom
    {
        $form = $this->lowering->productions->form($set);

        return match ($form->signature) {
            'generic_set: var_name TO var_list', 'generic_set: var_name = var_list' => new SetParameter($this->parameter($form->node(0)), $this->lowering->options->values($form->node(2)), $local),
            'generic_set: var_name TO DEFAULT', 'generic_set: var_name = DEFAULT' => new SetParameterFrom($this->parameter($form->node(0)), ParameterSource::Default, $local),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `var_name` into a parameter name.
     */
    public function parameter(Node $name): ParameterName
    {
        return new ParameterName($this->lowering->options->variable($name));
    }

    /**
     * Lowers `zone_value`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function zone(Node $zone): StringConstant|Name|SignedNumber|TypedLiteral|ZoneKeyword
    {
        $form = $this->lowering->productions->form($zone);
        $literals = $this->lowering->literals;
        $types = $this->lowering->types;

        return match ($form->signature) {
            'zone_value: Sconst' => $literals->string($form->node(0)),
            'zone_value: IDENT' => $this->lowering->names->token($form->token(0)),
            'zone_value: ConstInterval Sconst opt_interval' => new TypedLiteral(new TypeName($types->interval($form->node(0), $form->node(2))), $literals->string($form->node(1))),
            'zone_value: ConstInterval ( Iconst ) Sconst' => new TypedLiteral(new TypeName($types->preciseInterval($form->node(0), $form->node(2))), $literals->string($form->node(4))),
            'zone_value: NumericOnly' => $literals->signed($form->node(0)),
            'zone_value: DEFAULT' => ZoneKeyword::Default,
            'zone_value: LOCAL' => ZoneKeyword::Local,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers SET NAMES with its `opt_encoding`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function names(Node $encoding, bool $local): SetSpecial
    {
        $form = $this->lowering->productions->form($encoding);

        return match ($form->signature) {
            'opt_encoding: Sconst' => new SetSpecial(SpecialSetting::Names, $this->lowering->literals->string($form->node(0)), false, $local),
            'opt_encoding: DEFAULT' => new SetSpecial(SpecialSetting::Names, null, true, $local),
            'opt_encoding:' => new SetSpecial(SpecialSetting::Names, null, false, $local),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `reset_rest` or `generic_reset`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function reset(Node $rest): Reset
    {
        $form = $this->lowering->productions->form($rest);

        return match ($form->signature) {
            'reset_rest: generic_reset' => $this->reset($form->node(0)),
            'generic_reset: var_name' => new Reset($this->parameter($form->node(0))),
            default => new Reset(self::SPECIAL[$form->signature] ?? throw ImplementationGap::production($form)),
        };
    }

    /**
     * Lowers `constraints_set_list`; ALL is no name.
     *
     * @return list<\SqlSemantics\Statement\Identifier\QualifiedName>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function constraints(Node $list): array
    {
        $form = $this->lowering->productions->form($list);

        return match ($form->signature) {
            'constraints_set_list: ALL' => [],
            'constraints_set_list: qualified_name_list' => $this->lowering->names->qualifiedList($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `constraints_set_mode`: whether checking is deferred.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function deferred(Node $mode): bool
    {
        $form = $this->lowering->productions->form($mode);

        return match ($form->signature) {
            'constraints_set_mode: DEFERRED' => true,
            'constraints_set_mode: IMMEDIATE' => false,
            default => throw ImplementationGap::production($form),
        };
    }
}
