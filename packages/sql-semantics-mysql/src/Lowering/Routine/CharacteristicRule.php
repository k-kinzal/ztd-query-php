<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\AccessLevel;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Characteristic;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\DataAccess;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineComment;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineLanguage;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\SecurityContext;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\SqlSecurity;

/**
 * Lowers the characteristics of stored routines.
 *
 * Rule: MYSQL-ROUTINE-CHARACTERISTIC-LOWERING-001. Scope: sp_a_chistics,
 * sp_c_chistics, sp_chistic, sp_c_chistic, sp_suid. The characteristics
 * are kept in written order, repeated kinds included. Constructs:
 * RoutineComment, RoutineLanguage, DataAccess, SqlSecurity, Determinism.
 * Terminates: the lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-procedure.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CharacteristicRule
{
    /**
     * The characteristic list productions.
     */
    private const LISTS = ['sp_a_chistics:', 'sp_a_chistics: sp_a_chistics sp_chistic', 'sp_c_chistics:', 'sp_c_chistics: sp_c_chistics sp_c_chistic'];

    /**
     * The data access characteristics.
     */
    private const ACCESS = [
        'sp_chistic: NO_SYM SQL_SYM' => AccessLevel::NoSql, 'sp_chistic: CONTAINS_SYM SQL_SYM' => AccessLevel::ContainsSql,
        'sp_chistic: READS_SYM SQL_SYM DATA_SYM' => AccessLevel::ReadsSqlData, 'sp_chistic: MODIFIES_SYM SQL_SYM DATA_SYM' => AccessLevel::ModifiesSqlData,
    ];

    /**
     * The SQL SECURITY characteristics.
     */
    private const SECURITY = ['sp_suid: SQL_SYM SECURITY_SYM DEFINER_SYM' => SecurityContext::Definer, 'sp_suid: SQL_SYM SECURITY_SYM INVOKER_SYM' => SecurityContext::Invoker];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a characteristic list: a node of `sp_a_chistics` or `sp_c_chistics`.
     *
     * @return list<Characteristic>
     * @throws ImplementationGap When a production has no rule
     */
    public function characteristics(Node $list): array
    {
        $characteristics = [];
        foreach ((new Sequence($this->lowering))->items($list, self::LISTS) as $item) {
            $characteristics[] = $this->characteristic($item);
        }

        return $characteristics;
    }

    /**
     * Lowers one characteristic: a node of `sp_chistic` or `sp_c_chistic`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function characteristic(Node $characteristic): Characteristic
    {
        $form = $this->lowering->form($characteristic);
        if ($form->signature === 'sp_c_chistic: sp_chistic') {
            $form = $this->lowering->form($form->node(0));
        }
        if (isset(self::ACCESS[$form->signature])) {
            return new DataAccess(self::ACCESS[$form->signature]);
        }
        if ($form->signature === 'sp_chistic: sp_suid') {
            $suid = $this->lowering->form($form->node(0));

            return new SqlSecurity(self::SECURITY[$suid->signature] ?? throw ImplementationGap::production($suid));
        }
        if ($form->signature === 'sp_c_chistic: not DETERMINISTIC_SYM') {
            $this->lowering->options->skip($form->node(0));

            return new Determinism(false);
        }

        return match ($form->signature) {
            'sp_chistic: COMMENT_SYM TEXT_STRING_sys' => new RoutineComment($this->lowering->literals->text($form->node(1))),
            'sp_chistic: LANGUAGE_SYM SQL_SYM' => new RoutineLanguage(),
            'sp_chistic: LANGUAGE_SYM ident' => new RoutineLanguage($this->lowering->names->identifier($form->node(1))),
            'sp_c_chistic: DETERMINISTIC_SYM' => new Determinism(true),
            default => throw ImplementationGap::production($form),
        };
    }
}
