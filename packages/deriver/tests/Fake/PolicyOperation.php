<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Model\Intrinsic\IntrinsicDescriptor;
use Deriver\Model\Intrinsic\PureIntrinsic;
use Deriver\Project\TargetProfile;
use Deriver\Value\Term;
use Override;

/**
 * Business composition and overwrite deliberately remain separate from lattice join.
 * @visibility root
 */
final class PolicyOperation implements PureIntrinsic
{
    /**
     * @param string $operation compose, override, or uncertain-tags
     */
    public function __construct(public readonly string $operation)
    {
    }
    /**
     * @return IntrinsicDescriptor Explicit input dependencies
     */
    #[Override]
    public function descriptor(): IntrinsicDescriptor
    {
        return new IntrinsicDescriptor('example.' . $this->operation, '1', 'policy.' . $this->operation, 2, [0, 1]);
    }
    /**
     * @param list<Term> $arguments Policy operands
     * @param TargetProfile $target Declared semantics
     * @return Term Structured policy with independent fields
     */
    #[Override]
    public function evaluate(array $arguments, TargetProfile $target): Term
    {
        [$left, $right] = $arguments;
        if ($left->kind !== 'array' || $right->kind !== 'array') {
            return new Term('intrinsic', 'policy.' . $this->operation, $arguments, ['type' => 'array']);
        }
        if ($this->operation === 'override') {
            return Term::array(array_replace($left->operands, $right->operands));
        }
        $ttl = $left->operands['ttl'] ?? Term::opaque('UNKNOWN_TTL');
        $other = $right->operands['ttl'] ?? $ttl;
        $ttl = is_int($ttl->literal) && is_int($other->literal) ? Term::constant(min($ttl->literal, $other->literal)) : new Term('intrinsic', 'policy.minimum', [$ttl, $other]);
        $tags = $this->operation === 'uncertain-tags' ? Term::opaque('UNKNOWN_TAG_TRANSFORM') : Term::array(array_values([...($left->operands['tags']->operands ?? []), ...($right->operands['tags']->operands ?? [])]));
        return Term::array(['ttl' => $ttl, 'tags' => $tags]);
    }
}
