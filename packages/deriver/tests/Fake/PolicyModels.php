<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Model\CallModel;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Project\Configuration;

/**
 * Installs a complete independent model pack using public contracts only.
 * @visibility root
 */
final class PolicyModels
{
    /**
     * @return Configuration Explicit policy pack
     */
    public static function configuration(): Configuration
    {
        return new Configuration(models: [self::model('compose'), self::model('override'), self::model('uncertain_tags')], intrinsics: [new PolicyOperation('compose'), new PolicyOperation('override'), new PolicyOperation('uncertain-tags')], domains: [new PolicyDomain()]);
    }
    /**
     * @param string $operation External API operation
     * @return CallModel Declaration through the ordinary SDK
     */
    public static function model(string $operation): CallModel
    {
        return new PlanModel(new ModelDescriptor('policy.' . $operation, '1', 'policy_' . $operation, new Signature([new Parameter('a', 'array'), new Parameter('b', 'array')])), new SemanticPlan([Action::returns(new Expression('intrinsic', 'policy.' . str_replace('_', '-', $operation), [Expression::parameter('a'), Expression::parameter('b')]))]));
    }
}
