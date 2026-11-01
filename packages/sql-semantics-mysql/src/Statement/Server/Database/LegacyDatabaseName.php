<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;

/**
 * A legacy database directory identity, before MySQL's filename encoding.
 *
 * The suffix is decoded name data. The fixed #mysql50# marker is supplied by
 * this role, so an upgrade cannot accidentally name an ordinary database.
 * A directory component is nonempty, at most 64 characters, has no separator,
 * period or NUL, and does not end with ASCII whitespace. Its UTF-8 directory
 * component fits 183 bytes so the complete marked identity fits 192 bytes.
 * Observed through MySQL 5.6.51 and 5.7.44 database upgrade statements.
 *
 * @visibility public
 * @example Naming a legacy directory
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Database\LegacyDatabaseName(new \SqlSemantics\Statement\Identifier\Name('old-name')))->identifier()->value // => '#mysql50#old-name'
 */
final class LegacyDatabaseName implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * The directory component after conversion to the system character set.
     */
    public readonly string $value;

    /**
     * @param Name $suffix The decoded component without the marker, before character conversion
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the converted component violates the directory name domain
     */
    public function __construct(public readonly Name $suffix, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input(self::valid($suffix->value), 'A legacy database name requires a valid directory component of at most 64 characters.');
        $this->value = self::converted($suffix->value);
    }

    /**
     * Checks the intrinsic directory component domain, without a database lookup.
     */
    public static function valid(string $suffix): bool
    {
        return self::wellFormed($suffix) && mb_strlen(self::converted($suffix), 'UTF-8') <= 64;
    }

    /**
     * Checks the directory spelling before the separate character-count bound.
     * Identifiers use the system BMP character set; supplementary input characters
     * become question marks in the directory identity; rendering retains the operand.
     */
    public static function wellFormed(string $suffix): bool
    {
        $suffix = self::converted($suffix);

        return strlen($suffix) <= 183
            && preg_match('/\A[\x{0001}-\x{FFFF}]+\z/uD', $suffix) === 1
            && strpbrk($suffix, './\\') === false
            && !str_contains(" \t\n\r\v\f", substr($suffix, -1));
    }

    /**
     * Converts supplementary Unicode code points into the system character set.
     */
    public static function converted(string $suffix): string
    {
        return preg_replace('/[\x{10000}-\x{10FFFF}]/u', '?', $suffix) ?? $suffix;
    }

    /**
     * Reports lossy identifier conversion independently of lookup state.
     */
    public function derive(\SqlSemantics\Construction\Derivation $derivation): void
    {
        if ($this->value !== $this->suffix->value) {
            $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\NameConversion(new Name('#mysql50#' . $this->suffix->value), true), $this);
        }
    }

    /**
     * Answers the identifier used for lookup and diagnostic display.
     */
    public function identifier(): Name
    {
        return new Name('#mysql50#' . $this->value);
    }

    /**
     * Writes the legacy identity as one quoted identifier.
     */
    public function render(Output $out): void
    {
        $out->name(new Name('#mysql50#' . $this->suffix->value), NameUse::Qualifier);
    }
}
