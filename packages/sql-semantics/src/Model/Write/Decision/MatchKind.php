<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Decision;

/**
 * Closed MatchKind alternatives.
 * @visibility public
 */
enum MatchKind: string
{
    case Matched = 'matched';
    case MissingSource = 'not-matched-by-source';
    case MissingTarget = 'not-matched-by-target';
}
