<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

/**
 * Describes how much table context is available in the shadow store.
 */
enum ShadowTableState
{
    case Missing;
    case Materialized;
    case Initialized;
}
