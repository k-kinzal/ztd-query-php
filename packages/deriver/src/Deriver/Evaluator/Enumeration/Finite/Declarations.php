<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Enumeration\Finite;

use Deriver\Deriver\Evaluator\Evaluator;

/**
 * Recognizes finite declared return domains without deriving their members.
 * @visibility root
 */
final class Declarations
{
    /**
     * A union is closed only when every member has a captured finite domain.
     */
    public function domain(Evaluator $engine, string $type, string $scope = ''): ?Demand
    {
        $members = [];
        foreach (explode('|', str_replace('?', 'null|', $type)) as $part) {
            $name = $engine->context->index->className($part, $scope);
            $cases = $this->members($engine, $name);
            if ($cases === null) {
                return null;
            }
            $members += $cases;
        }
        return $members === [] ? null : new Demand($members, 'values:' . implode(',', array_keys($members)));
    }

    /**
     * @return array<string, true>|null Exact inhabitants, or an open domain
     */
    public function members(Evaluator $engine, string $type): ?array
    {
        if (in_array($type, ['bool', 'true', 'false', 'null'], true)) {
            return match ($type) {
                'bool' => ['b:0' => true, 'b:1' => true],
                'true' => ['b:1' => true],
                'false' => ['b:0' => true],
                'null' => ['scalar:N;' => true],
            };
        }
        $class = $engine->context->index->program->classes()[strtolower($type)] ?? null;
        if ($class === null || !$class->enum) {
            return null;
        }
        $members = [];
        foreach ($class->constantDeclarations as $constant) {
            if ($constant->enum) {
                $members['enum:' . $class->name . '::' . $constant->name] = true;
            }
        }
        return $members;
    }
}
