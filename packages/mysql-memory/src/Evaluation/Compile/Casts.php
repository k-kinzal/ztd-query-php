<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use SqlSemantics\Statement\Scalar;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Operator\Conversion;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Compiles CAST and CONVERT to a type: the domain of the target and the conversion into it.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Casts
{
    /**
     * @param Compiler $compiler The compiler of the statement
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Compiles the conversion of an operand to a target type.
     *
     * @throws \MySqlMemory\Error\SqlError When the target is not one the emulator converts to
     */
    public function cast(Evaluable $operand, CastTarget $target, Scalar $node): Evaluable
    {
        return new Conversion($operand, $this->compiler->domain($node), $target->length !== null && in_array($target->kind, [CastKind::Char, CastKind::NationalChar, CastKind::Binary], true) ? (int) $target->length : null, $target->kind->value);
    }



}
