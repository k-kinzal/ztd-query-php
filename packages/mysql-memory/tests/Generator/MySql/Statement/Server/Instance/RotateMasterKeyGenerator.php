<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Instance;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\RotateMasterKey;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Semantics\Statement\Identifier\NameQuoteGenerator;

/**
 * Generates RotateMasterKey values from the generators of its operands.
 *
 * @implements ErisGenerator<RotateMasterKey>
 */
final class RotateMasterKeyGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RotateMasterKey> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): RotateMasterKey => new RotateMasterKey(keyring: new Name($parts[0], $parts[1]), inputSource: null),
            Generators::tuple(Generators::elements(['INNODB', 'innodb', 'BINLOG', 'binlog']), Generators::oneOf(Generators::constant(null), new NameQuoteGenerator($release)))
        );
        $this->delegate = Generators::suchThat(
            static function (RotateMasterKey $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<RotateMasterKey>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<RotateMasterKey> $element The generated counterexample
     * @return GeneratedValue<RotateMasterKey>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
