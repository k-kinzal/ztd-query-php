<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

/**
 * Marker for mutations that can change rows participating in referential integrity.
 */
interface DataMutation extends ShadowMutation
{
}
