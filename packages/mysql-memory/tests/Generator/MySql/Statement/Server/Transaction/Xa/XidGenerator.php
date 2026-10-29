<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Transaction\Xa;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;

/**
 * Composes Xid operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<Xid>
 */
final class XidGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Xid> */
    private ErisGenerator $delegate;

    /**
     * Selects the grammar before generating operands.
     */
    public function __construct()
    {
        $bytes = Generators::bind(Generators::choose(0, 80), static fn (int $length): ErisGenerator => Generators::map(static fn (array $bytes): string => implode('', $bytes), Generators::vector($length, Generators::map(static fn (int $byte): string => chr($byte), Generators::choose(0, 255)))));
        $this->delegate = Generators::map(static function (array $data): Xid {
            [$global, $branch, $format] = $data;
            return new Xid(new \SqlSemantics\Platform\MySql\Statement\Literal\Text($global), $branch === null ? null : new \SqlSemantics\Platform\MySql\Statement\Literal\Text($branch), $branch === null || $format === null ? null : new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral((string) $format));
        }, Generators::tuple($bytes, Generators::oneOf(Generators::constant(null), $bytes), Generators::oneOf(Generators::constant(null), Generators::nat(), Generators::elements('9223372036854775807', '9223372036854775808', '18446744073709551616'))));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<Xid>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Xid>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $transaction The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null> $branch The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null> $format The generated semantic operand
     * @return ErisGenerator<Xid>
     */
    public static function fromOperands(ErisGenerator $transaction, ErisGenerator $branch, ErisGenerator $format): ErisGenerator
    {
        return Generators::map(static fn (array $parts): Xid => new Xid(transaction: $parts[0], branch: $parts[1], format: $parts[2]), Generators::tuple($transaction, $branch, $format));
    }

}
