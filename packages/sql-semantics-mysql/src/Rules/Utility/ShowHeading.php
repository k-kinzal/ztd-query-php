<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Utility;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Server\ForeignStrings;
use SqlSemantics\Platform\MySql\Rules\Text\NameEncoding;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Notice\TrimmedName;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

/**
 * Derives computed database and table inspection labels and their conditions.
 *
 * Labels retain at most 256 UTF-8 bytes, ending at a character boundary. Modern
 * releases warn before shortening; a table inspection refers to its original
 * label as well, which no longer resolves after shortening. Legacy releases
 * convert the pattern to the system character set and shorten without warning.
 * Observed on MySQL 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ShowHeading
{
    /**
     * Decodes the pattern for a label, retaining modern database-label NULs until naming.
     */
    public function pattern(Derivation $derivation, Text $pattern, bool $table): string
    {
        $release = $derivation->context->profile->grammar;
        $legacy = in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $decoded = (new NameEncoding())->decode($pattern->value, Settings::of($derivation->context)->client);
        if ($legacy || $derivation->inProgram()) {
            $warning = (new ForeignStrings())->conversion($decoded, $release);
            if ($warning !== null) {
                $derivation->warn($warning);
                if ($legacy && $derivation->inProgram()) {
                    $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\NameConversion($warning->name, $warning->legacy));
                }
            }
        }

        if ($legacy) {
            $decoded = (string) preg_replace('/[\xF0-\xF4][\x80-\xBF]{3}/', '?', $decoded);
        }

        return $legacy || $table ? explode("\0", $decoded, 2)[0] : $decoded;
    }

    /**
     * Retains the bounded name and records warnings and unresolved original references.
     */
    public function name(Derivation $derivation, string $name, bool $table): string
    {
        $legacy = in_array($derivation->context->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        if (!$legacy && strlen($name) > 256) {
            $derivation->warn(new TrimmedName(new Name($name)));
            if ($table) {
                $derivation->warn(new TrimmedName(new Name($name)));
                $derivation->report(new MissingColumn(new Name($name)));
            }
        }

        return mb_strcut(explode("\0", $name, 2)[0], 0, 256, 'UTF-8');
    }
}
