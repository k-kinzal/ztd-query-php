<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Transaction\Xa;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaEnd;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaEndOption;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;

/**
 * Composes XaEnd operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<XaEnd>
 */
final class XaEndGenerator implements ErisGenerator
{
    /** @var ErisGenerator<XaEnd> */
    private ErisGenerator $delegate;

    /**
     * Selects the grammar before generating operands.
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(static function (array $data) use ($release): XaEnd {
            [$xid, $option] = $data;
            if (!$xid instanceof Xid || ($option !== null && !$option instanceof XaEndOption)) {
                throw new LogicException('XA operands must have their selected SQL roles.');
            }
            return new XaEnd($xid, $option, profile: new LanguageProfile($release));
        }, Generators::tuple(new XidGenerator(), Generators::elements(null, XaEndOption::Suspend, XaEndOption::SuspendForMigrate)));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<XaEnd>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<XaEnd>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
