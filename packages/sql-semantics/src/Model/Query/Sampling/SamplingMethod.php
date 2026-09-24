<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Sampling;

/**
 * A built-in table sampling method, backed by its keyword: SYSTEM samples whole pages, BERNOULLI samples single rows.
 *
 * @visibility public
 * @example Reading a built-in sampling method
 *     \SqlSemantics\Model\Query\Sampling\SamplingMethod::from('BERNOULLI') // => \SqlSemantics\Model\Query\Sampling\SamplingMethod::Bernoulli
 */
enum SamplingMethod: string
{
    case System = 'SYSTEM';
    case Bernoulli = 'BERNOULLI';
}
