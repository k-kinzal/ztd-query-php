<?php

declare(strict_types=1);

namespace Tests\Fake\Oracle;

use Tests\Fake\RuntimeOracle;

/**
 * Observes trusted fixture heap identities and completion in a separate target PHP process.
 * @visibility root
 */
final class RuntimeState
{
    /**
     * Encodes concrete objects with encounter-order IDs, independently of Deriver's memory.
     * @param string $source Generated fixture source only
     * @param int $input Concrete generated argument
     * @return string Canonical runtime observation
     */
    public static function observe(string $source, int $input): string
    {
        $suffix = <<<'PHP'

$probe = [];
try {
    $observedValue = target(INPUT);
    $observedException = '';
} catch (Throwable $failure) {
    $observedValue = null;
    $observedException = get_class($failure);
}
$seenObjects = new SplObjectStorage();
$encode = function($value) use (&$encode, $seenObjects) {
    if (is_object($value)) {
        if ($seenObjects->contains($value)) {return ['ref' => $seenObjects[$value]];}
        $identity = count($seenObjects);
        $seenObjects[$value] = $identity;
        return ['object' => $identity, 'class' => get_class($value), 'fields' => $encode(get_mangled_object_vars($value))];
    }
    if (is_array($value)) {
        $entries = [];
        foreach ($value as $key => $item) {$entries[] = [$key, $encode($item)];}
        return ['array' => $entries];
    }
    return ['scalar' => $value];
};
echo json_encode(['value' => $observedValue, 'exception' => $observedException, 'state' => $encode($probe)], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
PHP;
        return RuntimeOracle::execute(RuntimeOracle::harness($source, str_replace('INPUT', (string) $input, $suffix)));
    }
}
