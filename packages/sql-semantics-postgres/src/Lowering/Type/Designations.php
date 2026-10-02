<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Type;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\BitDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DecimalDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DecimalKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\FloatDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeDesignation;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the designation of a type: its keyword spelling or its name.
 *
 * Rule: PG-TYPE-DESIGNATION-LOWER-001. Scope: `SimpleTypename`,
 * `ConstTypename`, `GenericType`, `opt_type_modifiers`, `Numeric`,
 * `opt_float`, `Bit`, `ConstBit`, `BitWithLength`, `BitWithoutLength`,
 * `JsonType`; character and date/time spellings are lowered by
 * PG-TYPE-SPELLING-LOWER-001. Constructors: the `TypeDesignation` classes.
 * The grammar action rejects a float precision below 1 or above 53.
 * Termination: no recursion besides the modifier expressions.
 * Source: https://www.postgresql.org/docs/17/datatype.html#DATATYPE-TABLE. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Designations
{
    /**
     * The keyword spellings that take no modifier.
     */
    private const KEYWORDS = [
        'Numeric: INT_P' => TypeKeyword::Int, 'Numeric: INTEGER' => TypeKeyword::Integer, 'Numeric: SMALLINT' => TypeKeyword::Smallint,
        'Numeric: BIGINT' => TypeKeyword::Bigint, 'Numeric: REAL' => TypeKeyword::Real, 'Numeric: DOUBLE_P PRECISION' => TypeKeyword::DoublePrecision,
        'Numeric: BOOLEAN_P' => TypeKeyword::Boolean, 'JsonType: JSON' => TypeKeyword::Json,
    ];

    /**
     * The keyword spellings of the numeric type.
     */
    private const DECIMALS = [
        'Numeric: DECIMAL_P opt_type_modifiers' => DecimalKeyword::Decimal, 'Numeric: DEC opt_type_modifiers' => DecimalKeyword::Dec,
        'Numeric: NUMERIC opt_type_modifiers' => DecimalKeyword::Numeric,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `SimpleTypename`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function simple(Node $type): TypeDesignation
    {
        $form = $this->lowering->productions->form($type);
        $spellings = new Spellings($this->lowering);
        $intervals = new Intervals($this->lowering);

        return match ($form->signature) {
            'SimpleTypename: GenericType' => $this->named($form->node(0)),
            'SimpleTypename: Numeric', 'SimpleTypename: JsonType' => $this->numeric($form->node(0)),
            'SimpleTypename: Bit' => $this->bit($form->node(0)),
            'SimpleTypename: Character' => $spellings->character($form->node(0)),
            'SimpleTypename: ConstDatetime' => $spellings->datetime($form->node(0)),
            'SimpleTypename: ConstInterval opt_interval' => $intervals->interval($form->node(0), $form->node(1)),
            'SimpleTypename: ConstInterval ( Iconst )' => $intervals->precise($form->node(0), $form->node(2)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `ConstTypename`: the keyword-spelled types a typed constant may use.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function constant(Node $type): TypeDesignation
    {
        $form = $this->lowering->productions->form($type);
        $spellings = new Spellings($this->lowering);

        return match ($form->signature) {
            'ConstTypename: Numeric', 'ConstTypename: JsonType' => $this->numeric($form->node(0)),
            'ConstTypename: ConstBit' => $this->bit($form->node(0)),
            'ConstTypename: ConstCharacter' => $spellings->character($form->node(0)),
            'ConstTypename: ConstDatetime' => $spellings->datetime($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `GenericType`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function named(Node $type): NamedDesignation
    {
        $form = $this->lowering->productions->form($type);
        $names = $this->lowering->names;

        return match ($form->signature) {
            'GenericType: type_function_name opt_type_modifiers' => new NamedDesignation(new DottedName([$names->name($form->node(0))]), $this->modifiers($form->node(1))),
            'GenericType: type_function_name attrs opt_type_modifiers' => new NamedDesignation(new DottedName([$names->name($form->node(0)), ...$names->attributes($form->node(1))]), $this->modifiers($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_type_modifiers`; no modifier list is empty.
     *
     * @return list<Scalar>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function modifiers(Node $modifiers): array
    {
        $form = $this->lowering->productions->form($modifiers);

        return match ($form->signature) {
            'opt_type_modifiers:' => [],
            'opt_type_modifiers: ( expr_list )' => $this->lowering->expressions->expressions($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `Numeric` or `JsonType`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function numeric(Node $type): TypeDesignation
    {
        $form = $this->lowering->productions->form($type);
        if (isset(self::KEYWORDS[$form->signature])) {
            return new KeywordDesignation(self::KEYWORDS[$form->signature]);
        }
        if (isset(self::DECIMALS[$form->signature])) {
            return new DecimalDesignation(self::DECIMALS[$form->signature], $this->modifiers($form->node(1)));
        }
        if ($form->signature !== 'Numeric: FLOAT_P opt_float') {
            throw ImplementationGap::production($form);
        }

        return new FloatDesignation($this->precision($form->node(1)));
    }

    /**
     * Lowers `opt_float` into the float precision.
     *
     * @throws AnalysisException When the precision is below 1 or above 53, which the server rejects while parsing
     * @throws ImplementationGap When the production has no rule
     */
    public function precision(Node $precision): ?IntegerConstant
    {
        $form = $this->lowering->productions->form($precision);
        if ($form->signature === 'opt_float:') {
            return null;
        }
        if ($form->signature !== 'opt_float: ( Iconst )') {
            throw ImplementationGap::production($form);
        }
        $bits = $this->lowering->literals->integer($form->node(1));
        if ($bits->digits === '0') {
            throw new AnalysisException('precision for type float must be at least 1 bit');
        }
        if (strlen($bits->digits) > 2 || (int) $bits->digits > 53) {
            throw new AnalysisException('precision for type float must be less than 54 bits');
        }

        return $bits;
    }

    /**
     * Lowers `Bit`, `ConstBit`, `BitWithLength` or `BitWithoutLength`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function bit(Node $type): BitDesignation
    {
        $form = $this->lowering->productions->form($type);
        $flags = new Spellings($this->lowering);

        return match ($form->signature) {
            'Bit: BitWithLength', 'Bit: BitWithoutLength', 'ConstBit: BitWithLength', 'ConstBit: BitWithoutLength' => $this->bit($form->node(0)),
            'BitWithLength: BIT opt_varying ( expr_list )' => new BitDesignation($flags->varying($form->node(1)), $this->lowering->expressions->expressions($form->node(3))),
            'BitWithoutLength: BIT opt_varying' => new BitDesignation($flags->varying($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }
}
