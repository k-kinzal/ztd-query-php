<?php

declare(strict_types=1);

namespace Deriver\Model\Registration;

use Deriver\Model\Signature\Signature;
use Deriver\Value\Identity;

/**
 * Includes argument and return contracts in immutable model snapshot identity.
 * @visibility root
 */
final class SignatureIdentity
{
    /**
     * Hashes signature semantics without exposing confidential default values.
     * @param Signature $signature Captured external signature
     * @return string Stable digest within the analysis process
     */
    public function key(Signature $signature): string
    {
        $parameters = [];
        foreach ($signature->parameters as $parameter) {
            $parameters[] = [$parameter->name, $parameter->type, $parameter->byReference, $parameter->variadic, $parameter->default === null ? null : (new Identity())->key($parameter->default)];
        }
        return hash('sha256', serialize([$parameters, $signature->returnType, $signature->byReference, $signature->allowExtraArguments]));
    }
}
