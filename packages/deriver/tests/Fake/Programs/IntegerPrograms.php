<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

/**
 * Independent PHP 8.3 observations for integer conversion.
 * @visibility root
 */
final class IntegerPrograms
{
    /**
     * Supplies trusted programs and observations obtained from PHP 8.3.
     * @return array<string, array{string, string, string, bool}> Source, normal returns, exception class, diagnostic presence
     */
    public static function cases(): array
    {
        return [
            '0:%' => ['<?php function target(){return 7 % (8.289777048786028E+24);}', '[7]', '', true],
            '0:<<' => ['<?php function target(){return 7 << (8.289777048786028E+24);}', '[]', 'ArithmeticError', true],
            '0:>>' => ['<?php function target(){return 7 >> (8.289777048786028E+24);}', '[]', 'ArithmeticError', true],
            '0:&' => ['<?php function target(){return 7 & (8.289777048786028E+24);}', '[0]', '', true],
            '0:|' => ['<?php function target(){return 7 | (8.289777048786028E+24);}', '[-5270498307234332665]', '', true],
            '0:^' => ['<?php function target(){return 7 ^ (8.289777048786028E+24);}', '[-5270498307234332665]', '', true],
            '0:cast' => ['<?php function target(){return (int)(8.289777048786028E+24);}', '[-5270498307234332672]', '', false],
            '0:key' => ['<?php function target(){$a=[];$a[8.289777048786028E+24]=1;return $a;}', '[{"-5270498307234332672":1}]', '', true],
            '1:%' => ['<?php function target(){return 7 % (1e30);}', '[7]', '', true],
            '1:<<' => ['<?php function target(){return 7 << (1e30);}', '[0]', '', true],
            '1:>>' => ['<?php function target(){return 7 >> (1e30);}', '[0]', '', true],
            '1:&' => ['<?php function target(){return 7 & (1e30);}', '[0]', '', true],
            '1:|' => ['<?php function target(){return 7 | (1e30);}', '[5076964154930102279]', '', true],
            '1:^' => ['<?php function target(){return 7 ^ (1e30);}', '[5076964154930102279]', '', true],
            '1:cast' => ['<?php function target(){return (int)(1e30);}', '[5076964154930102272]', '', false],
            '1:key' => ['<?php function target(){$a=[];$a[1e30]=1;return $a;}', '[{"5076964154930102272":1}]', '', true],
            '2:%' => ['<?php function target(){return 7 % (1e20);}', '[7]', '', true],
            '2:<<' => ['<?php function target(){return 7 << (1e20);}', '[0]', '', true],
            '2:>>' => ['<?php function target(){return 7 >> (1e20);}', '[0]', '', true],
            '2:&' => ['<?php function target(){return 7 & (1e20);}', '[0]', '', true],
            '2:|' => ['<?php function target(){return 7 | (1e20);}', '[7766279631452241927]', '', true],
            '2:^' => ['<?php function target(){return 7 ^ (1e20);}', '[7766279631452241927]', '', true],
            '2:cast' => ['<?php function target(){return (int)(1e20);}', '[7766279631452241920]', '', false],
            '2:key' => ['<?php function target(){$a=[];$a[1e20]=1;return $a;}', '[{"7766279631452241920":1}]', '', true],
            '3:%' => ['<?php function target(){return 7 % (-1e20);}', '[7]', '', true],
            '3:<<' => ['<?php function target(){return 7 << (-1e20);}', '[]', 'ArithmeticError', true],
            '3:>>' => ['<?php function target(){return 7 >> (-1e20);}', '[]', 'ArithmeticError', true],
            '3:&' => ['<?php function target(){return 7 & (-1e20);}', '[0]', '', true],
            '3:|' => ['<?php function target(){return 7 | (-1e20);}', '[-7766279631452241913]', '', true],
            '3:^' => ['<?php function target(){return 7 ^ (-1e20);}', '[-7766279631452241913]', '', true],
            '3:cast' => ['<?php function target(){return (int)(-1e20);}', '[-7766279631452241920]', '', false],
            '3:key' => ['<?php function target(){$a=[];$a[-1e20]=1;return $a;}', '[{"-7766279631452241920":1}]', '', true],
            '4:%' => ['<?php function target(){return 7 % (9223372036854775808.0);}', '[7]', '', true],
            '4:<<' => ['<?php function target(){return 7 << (9223372036854775808.0);}', '[]', 'ArithmeticError', true],
            '4:>>' => ['<?php function target(){return 7 >> (9223372036854775808.0);}', '[]', 'ArithmeticError', true],
            '4:&' => ['<?php function target(){return 7 & (9223372036854775808.0);}', '[0]', '', true],
            '4:|' => ['<?php function target(){return 7 | (9223372036854775808.0);}', '[-9223372036854775801]', '', true],
            '4:^' => ['<?php function target(){return 7 ^ (9223372036854775808.0);}', '[-9223372036854775801]', '', true],
            '4:cast' => ['<?php function target(){return (int)(9223372036854775808.0);}', '[-9223372036854775808]', '', false],
            '4:key' => ['<?php function target(){$a=[];$a[9223372036854775808.0]=1;return $a;}', '[{"-9223372036854775808":1}]', '', true],
            '5:%' => ['<?php function target(){return 7 % (-9223372036854775808.0);}', '[7]', '', false],
            '5:<<' => ['<?php function target(){return 7 << (-9223372036854775808.0);}', '[]', 'ArithmeticError', false],
            '5:>>' => ['<?php function target(){return 7 >> (-9223372036854775808.0);}', '[]', 'ArithmeticError', false],
            '5:&' => ['<?php function target(){return 7 & (-9223372036854775808.0);}', '[0]', '', false],
            '5:|' => ['<?php function target(){return 7 | (-9223372036854775808.0);}', '[-9223372036854775801]', '', false],
            '5:^' => ['<?php function target(){return 7 ^ (-9223372036854775808.0);}', '[-9223372036854775801]', '', false],
            '5:cast' => ['<?php function target(){return (int)(-9223372036854775808.0);}', '[-9223372036854775808]', '', false],
            '5:key' => ['<?php function target(){$a=[];$a[-9223372036854775808.0]=1;return $a;}', '[{"-9223372036854775808":1}]', '', false],
            '6:%' => ['<?php function target(){return 7 % (18446744073709551616.0);}', '[]', 'DivisionByZeroError', true],
            '6:<<' => ['<?php function target(){return 7 << (18446744073709551616.0);}', '[7]', '', true],
            '6:>>' => ['<?php function target(){return 7 >> (18446744073709551616.0);}', '[7]', '', true],
            '6:&' => ['<?php function target(){return 7 & (18446744073709551616.0);}', '[0]', '', true],
            '6:|' => ['<?php function target(){return 7 | (18446744073709551616.0);}', '[7]', '', true],
            '6:^' => ['<?php function target(){return 7 ^ (18446744073709551616.0);}', '[7]', '', true],
            '6:cast' => ['<?php function target(){return (int)(18446744073709551616.0);}', '[0]', '', false],
            '6:key' => ['<?php function target(){$a=[];$a[18446744073709551616.0]=1;return $a;}', '[[1]]', '', true],
            '7:%' => ['<?php function target(){return 7 % (1.5);}', '[0]', '', true],
            '7:<<' => ['<?php function target(){return 7 << (1.5);}', '[14]', '', true],
            '7:>>' => ['<?php function target(){return 7 >> (1.5);}', '[3]', '', true],
            '7:&' => ['<?php function target(){return 7 & (1.5);}', '[1]', '', true],
            '7:|' => ['<?php function target(){return 7 | (1.5);}', '[7]', '', true],
            '7:^' => ['<?php function target(){return 7 ^ (1.5);}', '[6]', '', true],
            '7:cast' => ['<?php function target(){return (int)(1.5);}', '[1]', '', false],
            '7:key' => ['<?php function target(){$a=[];$a[1.5]=1;return $a;}', '[{"1":1}]', '', true],
            '8:%' => ['<?php function target(){return 7 % (-0.5);}', '[]', 'DivisionByZeroError', true],
            '8:<<' => ['<?php function target(){return 7 << (-0.5);}', '[7]', '', true],
            '8:>>' => ['<?php function target(){return 7 >> (-0.5);}', '[7]', '', true],
            '8:&' => ['<?php function target(){return 7 & (-0.5);}', '[0]', '', true],
            '8:|' => ['<?php function target(){return 7 | (-0.5);}', '[7]', '', true],
            '8:^' => ['<?php function target(){return 7 ^ (-0.5);}', '[7]', '', true],
            '8:cast' => ['<?php function target(){return (int)(-0.5);}', '[0]', '', false],
            '8:key' => ['<?php function target(){$a=[];$a[-0.5]=1;return $a;}', '[[1]]', '', true],
            'octal-crash' => ['<?php function target(){$a%=03333333333333333333333333333;return $a;}', '[0]', '', true],
            'caught-modulo-zero' => ['<?php function target(){try{return 3 % 0.1;}catch(DivisionByZeroError $e){return "caught";}}', '["caught"]', '', true],
        ];
    }
}
