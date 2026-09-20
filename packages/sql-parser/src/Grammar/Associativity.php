<?php

declare(strict_types=1);

namespace SqlParser\Grammar;

/**
 * How a shift/reduce conflict between equally ranked operators is settled.
 *
 * A grammar declares operators in ranked groups. When a shift and a reduce
 * carry the same rank, the group's associativity decides: left-associative
 * operators reduce, right-associative ones shift, non-associative ones make
 * the token an error, and a bare rank leaves the conflict unresolved.
 *
 * @visibility root
 */
enum Associativity
{
    case Left;
    case Right;
    case NonAssoc;
    case Precedence;
}
