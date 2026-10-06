<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

use Deriver\Value\Term;

/**
 * Trusted float-to-string conversions under an explicitly captured precision directive.
 * @visibility root
 */
final class FloatStringPrograms
{
    /**
     * @return array<string, array{int, string, Term}> Precision directive, source program, and expected concrete observation
     */
    public static function cases(): array
    {
        return [
            'concatenation with the default precision' => [14, '<?php function target(){$f=0.25;return "v" . $f;}', Term::constant('v0.25')],
            'concatenation rounds to the precision' => [14, '<?php function target(){$f=1 / 3;return "v" . $f;}', Term::constant('v0.33333333333333')],
            'concatenation with more digits' => [17, '<?php function target(){$f=1 / 3;return "v" . $f;}', Term::constant('v0.33333333333333331')],
            'shortest round trip cast' => [-1, '<?php function target(){$f=0.1 + 0.2;return (string) $f;}', Term::constant('0.30000000000000004')],
            'zero precision' => [0, '<?php function target(){$f=2.5;return $f . "";}', Term::constant('2')],
            'exponent and negative zero in implode' => [14, '<?php function target(){$z=0.0;return implode(",", [0.5, 1e15, -$z]);}', Term::constant('0.5,1.0E+15,-0')],
            'sprintf string conversion' => [14, '<?php function target(){$f=1e100;return sprintf("%s", $f);}', Term::constant('1.0E+100')],
            'string length of a float' => [14, '<?php function target(){$f=1 / 3;return strlen($f);}', Term::constant(16)],
            'truncated infinity and not a number' => [2, '<?php function target(){$n=1e308 * 10;return [$n . "", ($n - $n) . ""];}', Term::fromNative(['IN', 'NA'])],
            'coercive string parameter' => [14, '<?php function text(string $v){return $v;} function target(){return text(0.1 + 0.2);}', Term::constant('0.3')],
            'comparison with a non-numeric string' => [14, '<?php function target(){$f=0.1 + 0.2;return $f <=> "0.3 x";}', Term::constant(-1)],
            'comparison with a non-numeric string and more digits' => [-1, '<?php function target(){$f=0.1 + 0.2;return $f <=> "0.3 x";}', Term::constant(1)],
            'string sorting' => [-1, '<?php function target(){$a=["0.3 x", 0.1 + 0.2];sort($a, SORT_STRING);return $a;}', Term::fromNative(['0.3 x', 0.1 + 0.2])],
        ];
    }
}
