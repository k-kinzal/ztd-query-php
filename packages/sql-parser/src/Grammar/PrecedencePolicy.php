<?php

declare(strict_types=1);

namespace SqlParser\Grammar;

/**
 * Which terminal lends a rule its precedence when the rule names none itself.
 *
 * Bison takes the last terminal of the rule whether or not it has a declared
 * rank, so a rule ending in an unranked keyword has no precedence. Lemon
 * takes the first terminal that has a declared rank.
 *
 * @visibility root
 */
enum PrecedencePolicy
{
    case LastTerminal;
    case FirstRankedTerminal;
}
