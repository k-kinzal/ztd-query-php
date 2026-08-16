<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

enum IdentityGenerationStrategy
{
    case MaxValue;
    case Sequence;
}
