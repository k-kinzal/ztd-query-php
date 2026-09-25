<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Evaluation;

use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;

/**
 * One alternative an expression may evaluate to.
 *
 * The hierarchy is sealed. A term is a resolved scalar (`LiteralTerm`), a
 * string with gaps (`PatternTerm`), an array (`ArrayTerm`), an object
 * (`ObjectTerm`), or a value known only by its type (`OpaqueTerm`).
 *
 * @visibility root
 */
interface Term
{
    /**
     * The term written as the string PHP would splice into a query.
     */
    public function toPattern(): TextPattern;

    /**
     * The static type of the values this term stands for.
     */
    public function type(): TypeShape;

    /**
     * A canonical string used to deduplicate alternatives.
     */
    public function signature(): string;
}
