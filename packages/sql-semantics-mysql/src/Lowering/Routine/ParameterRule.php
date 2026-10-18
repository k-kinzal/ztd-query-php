<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterMode;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Type\DeclaredName;

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
     * @throws RejectedInput When a completed parameter repeats an earlier name
     */
    public function parameters(Node $list): ParameterList
    {
        $form = $this->lowering->form($list);
        $parameters = [];
        $items = match ($form->signature) {
            'sp_fdparam_list:', 'sp_pdparam_list:' => [],
            'sp_fdparam_list: sp_fdparams', 'sp_pdparam_list: sp_pdparams' => (new Sequence($this->lowering))->items($form->node(0), self::LISTS),
            'sp_fdparams: sp_fdparams , sp_fdparam', 'sp_fdparams: sp_fdparam', 'sp_pdparams: sp_pdparams , sp_pdparam', 'sp_pdparams: sp_pdparam' => (new Sequence($this->lowering))->items($list, self::LISTS),
            default => throw ImplementationGap::production($form),
        };
        $seen = [];
        foreach ($items as $item) {
            $parameter = $this->parameter($item);
            $key = $parameter->key();
            if (isset($seen[$key])) {
                throw new RejectedInput(new ProgramProblem(ProgramRule::DuplicateParameter, $parameter->name->value), (new SourceRanges())->span($item)[1] ?? 0);
            }
            $seen[$key] = true;
            $parameters[] = $parameter;
        }

        return new ParameterList($parameters);
    }

    /**
     * Lowers one parameter: a node of `sp_fdparam` or `sp_pdparam`.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws RejectedInput When a parameter name is outside its intrinsic domain
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

        $identifier = $this->lowering->names->identifier($form->node($name));
        $legacy = in_array($this->lowering->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
        $problem = $legacy ? null : Parameter::nameProblem($identifier);
        if ($problem !== null) {
            throw new RejectedInput($problem, (new SourceRanges())->span($parameter)[1] ?? 0);
        }

        return new Parameter($identifier, $declared, $collate, $direction);
    }

    /**
     * Lowers a data type with its optional COLLATE clause: a node of `type_with_opt_collate`, or a node
     * of `type` and the node of `opt_collate` that follows it.
     *
     * @return array{DeclaredName, CollationName|null}
     * @throws ImplementationGap When a production has no rule
     * @throws RejectedInput When a named collation has no compatible declared character set
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

        $declared = $this->lowering->types->type($type);
        $collate = $this->lowering->charsets->collation($collation);
        $problem = (new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\DeclaredCollation())->problem($declared, $collate, $this->lowering->profile->grammar);
        if ($problem !== null) {
            if ($collate?->name !== null && \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::forName($collate->name->value, $this->lowering->profile->grammar) === null) {
                throw new RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation($collate->name->value), (new SourceRanges())->span($collation)[1] ?? 0);
            }
            throw new RejectedInput($problem, (new SourceRanges())->span($collation)[1] ?? 0);
        }

        return [$declared, $collate];
    }
}
