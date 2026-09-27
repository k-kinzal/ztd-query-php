<?php

declare(strict_types=1);

namespace Tests\Fake\Oracle;

/**
 * Generates bounded trusted programs independently of the analyzer's IR and transfer rules.
 * @visibility root
 */
final class StatePrograms
{
    /**
     * Exhausts three inputs over reproducible combinations of storage and control operations.
     * @return array<string, array{string, int}> Trusted programs and concrete inputs
     */
    public static function cases(): array
    {
        $cases = [];
        foreach (range(0, 31) as $seed) {
            foreach ([-1, 0, 1] as $input) {
                $cases[$seed . ':' . $input] = [self::source(self::operations($seed)), $input];
            }
        }
        return $cases;
    }

    /**
     * Selects operations from a small grammar with initialized operands and bounded loops.
     * @param int $seed Reproducible generation seed
     * @return list<string> Removable independent operation statements
     */
    public static function operations(int $seed): array
    {
        $grammar = [
            '$a->value += 3;',
            '$b->value = $n;',
            '$c->value -= 2;',
            '$a->child = $c;',
            '$c->child = $a;',
            '$ref += 2;',
            '$list[1] = $n;',
            '$copy[0] += 3;',
            'foreach ([1,2] as $v) {$c->value += $v;}',
            '$f = function() use (&$ref) {$ref += 1;}; $f();',
            '$b = clone $a; $probe["b"] = $b;',
            '$c->child = null;',
            'if ($n < 0) {$a->value += 2;} else {$c->value += 2;}',
            'try {$a->value++; if ($n === 0) {throw new RuntimeException;}} catch (RuntimeException $e) {$ref += 2;} finally {$c->value += 1;}',
            'bump($ref);',
            '[&$pattern,$ignored] = $list; $pattern += 2;',
            '[$list[1],$unused] = $list;',
            'list(, &$pattern) = $copy; $pattern += 3;',
            'foreach ([$list,$copy] as [&$pattern]) {$pattern += 1;}',
            '[$a->value,$c->value] = [$n,4];',
            '[&$a->value] = $list;',
            '[[$a->value],$c->value] = [[$n],4];',
            'foreach ([$n] as $pattern => $pattern) {$c->value += $pattern;}',
        ];
        $bytes = hash('sha256', 'deriver-state:' . $seed, true);
        $operations = [];
        foreach (range(0, 7) as $index) {
            $operations[] = $grammar[ord($bytes[$index]) % count($grammar)];
        }
        return $operations;
    }

    /**
     * Wraps independently removable operations in a fixed valid setup and observation scope.
     * @param list<string> $operations Generated operations
     * @return string Trusted PHP source
     */
    public static function source(array $operations): string
    {
        return <<<'SOURCE'
<?php
class Box { public int $value = 0; public ?Box $child = null; }
function bump(int &$value): void {$value += 4;}
function target(int $n): int {
    global $probe;
    $a = new Box; $b = $a; $c = clone $a;
    $ref = 1; $list = [&$ref, 2]; $copy = $list;
    $probe = ['a' => $a, 'b' => $b, 'c' => $c];
    $probe['list'] =& $list; $probe['copy'] =& $copy;
SOURCE
            . "\n" . implode("\n", $operations) . "\n" . <<<'SOURCE'
    $probe['ref'] =& $ref;
    if ($n < 0) {throw new RuntimeException('end');}
    return $ref;
}
SOURCE;
    }
}
