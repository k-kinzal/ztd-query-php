<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

enum UpsertColumnSource
{
    case Existing;
    case Incoming;
}
