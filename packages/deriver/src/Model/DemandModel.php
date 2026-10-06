<?php

declare(strict_types=1);

namespace Deriver\Model;

/**
 * Requests only the inputs needed to select a candidate model's plan.
 * Plan parameter expressions request their own dependencies lazily.
 * @visibility public
 * @example Checking a model selection contract
 *     $model = new class implements \Deriver\Model\DemandModel {
 *         public function descriptor(): \Deriver\Model\ModelDescriptor {
 *             return new \Deriver\Model\ModelDescriptor('example.fast', '1', 'formatValue');
 *         }
 *         public function demand(\Deriver\Model\CallDescription $call): array { return ['mode']; }
 *         public function describe(\Deriver\Model\CallDescription $call): \Deriver\Model\ModelDecision {
 *             return \Deriver\Model\ModelDecision::declined();
 *         }
 *     };
 *     $model->demand(new \Deriver\Model\CallDescription('formatValue', new \Deriver\Model\Signature\Signature(), new \Deriver\Project\TargetProfile())) // => ['mode']
 */
interface DemandModel extends CallModel
{
    /**
     * @param CallDescription $call Declaration metadata and formal argument handles
     * @return list<string> Signature parameter names needed by describe()
     * @throws \Deriver\Exception\ModelContractException If the model cannot select its required inputs
     */
    public function demand(CallDescription $call): array;
}
