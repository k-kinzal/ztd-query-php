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
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaStart;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaStartOption;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;

/**
 * Composes XaStart operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<XaStart>
 */
final class XaStartGenerator implements ErisGenerator
{
    /** @var ErisGenerator<XaStart> */
    private ErisGenerator $delegate;

    /**
     * Selects the grammar before generating operands.
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(static function (array $data) use ($release): XaStart {
            [$xid, $option] = $data;
            if (!$xid instanceof Xid || ($option !== null && !$option instanceof XaStartOption)) {
                throw new LogicException('XA operands must have their selected SQL roles.');
            }
            return new XaStart($xid, $option, profile: new LanguageProfile($release));
        }, Generators::tuple(new XidGenerator(), Generators::elements(null, XaStartOption::Join, XaStartOption::Resume)));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<XaStart>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<XaStart>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
