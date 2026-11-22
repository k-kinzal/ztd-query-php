<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type;

use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\TypeFact;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * The part of a type name that designates the element type: a keyword spelling, a catalog name, or a column's type.
 *
 * The grammar spells the standard types with keywords and every other type
 * with a name. Each spelling is one implementation; the type it denotes is a
 * fact derived against the context.
 * Source: https://www.postgresql.org/docs/17/datatype.html.
 *
 * @visibility SqlSemantics
 * @example Reading the catalog name of a keyword spelling
 *     $designation = new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation(\SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword::DoublePrecision);
 *     $designation->catalogName()->value // => 'float8'
 */
interface TypeDesignation extends Clause
{
    /**
     * Answers the type the designation denotes in a context.
     *
     * @param bool $constant Whether the designation types a constant, where `bit` and `character` without a length are unconstrained instead of one unit long
     */
    public function typeFact(AnalysisContext $context, bool $constant): TypeFact;

    /**
     * Answers the name the type has in the catalog as far as the spelling fixes it; a cast names its result column after it.
     */
    public function catalogName(): Name;
}
