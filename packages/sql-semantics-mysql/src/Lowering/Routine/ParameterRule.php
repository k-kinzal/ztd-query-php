<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterMode;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;

/**
 * Lowers the parameter lists of stored routines and the data types of parameters, variables and return values.
 *
 * Rule: MYSQL-ROUTINE-PARAMETER-LOWERING-001. Scope: sp_fdparam_list,
 * sp_fdparams, sp_fdparam, sp_pdparam_list, sp_pdparams, sp_pdparam,
 * sp_opt_inout, type_with_opt_collate. The type comes from the type rules,
 * the collation from the leaf rules. Constructs: ParameterList, Parameter.
 * Terminates: the lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ParameterRule
{
    /**
     * The direction keywords.
     */
    private const MODES = [
        'sp_opt_inout:' => null, 'sp_opt_inout: IN_SYM' => ParameterMode::In, 'sp_opt_inout: OUT_SYM' => ParameterMode::Out, 'sp_opt_inout: INOUT_SYM' => ParameterMode::InOut,
    ];

    /**
     * The parameter list productions.
     */
    private const LISTS = ['sp_fdparams: sp_fdparams , sp_fdparam', 'sp_fdparams: sp_fdparam', 'sp_pdparams: sp_pdparams , sp_pdparam', 'sp_pdparams: sp_pdparam'];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a parameter list: a node of `sp_fdparam_list` or `sp_pdparam_list`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function parameters(Node $list): ParameterList
    {
        $form = $this->lowering->form($list);
        $parameters = [];
        $items = match ($form->signature) {
            'sp_fdparam_list:', 'sp_pdparam_list:' => [],
            'sp_fdparam_list: sp_fdparams', 'sp_pdparam_list: sp_pdparams' => (new Sequence($this->lowering))->items($form->node(0), self::LISTS),
            default => throw ImplementationGap::production($form),
        };
        foreach ($items as $item) {
            $parameters[] = $this->parameter($item);
        }

        return new ParameterList($parameters);
    }

    /**
     * Lowers one parameter: a node of `sp_fdparam` or `sp_pdparam`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function parameter(Node $parameter): Parameter
    {
        $form = $this->lowering->form($parameter);
        [$mode, $name, $type, $collation] = match ($form->signature) {
            'sp_fdparam: ident sp_init_param type_with_opt_collate' => [null, 0, 2, null],
            'sp_fdparam: ident type opt_collate' => [null, 0, 1, 2],
            'sp_pdparam: sp_opt_inout sp_init_param ident type_with_opt_collate' => [0, 2, 3, null],
            'sp_pdparam: sp_opt_inout ident type opt_collate' => [0, 1, 2, 3],
            default => throw ImplementationGap::production($form),
        };
        if ($collation === null) {
            $this->lowering->options->skip($form->node(1));
        }
        $direction = null;
        if ($mode !== null) {
            $inout = $this->lowering->form($form->node($mode));
            if (!array_key_exists($inout->signature, self::MODES)) {
                throw ImplementationGap::production($inout);
            }
            $direction = self::MODES[$inout->signature];
        }
        [$declared, $collate] = $this->declared($form->node($type), $collation === null ? null : $form->node($collation));

        return new Parameter($this->lowering->names->identifier($form->node($name)), $declared, $collate, $direction);
    }

    /**
     * Lowers a data type with its optional COLLATE clause: a node of `type_with_opt_collate`, or a node
     * of `type` and the node of `opt_collate` that follows it.
     *
     * @return array{TypeName, CollationName|null}
     * @throws ImplementationGap When a production has no rule
     */
    public function declared(Node $type, ?Node $collation): array
    {
        if ($collation === null) {
            $form = $this->lowering->form($type);
            if ($form->signature !== 'type_with_opt_collate: type opt_collate') {
                throw ImplementationGap::production($form);
            }
            [$type, $collation] = [$form->node(0), $form->node(1)];
        }

        return [$this->lowering->types->type($type), $this->lowering->charsets->collation($collation)];
    }
}
