<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Notice;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Statement\Snapshot;

/**
 * The warning the server raises for a deprecated or converted construct while it reads a statement.
 *
 * Rule: MYSQL-DEPRECATION-001. The server raises the warning of a construct
 * when it parses the construct, so the warnings come before any problem of
 * the statement and in the order the constructs are written; a statement
 * that fails keeps them. Each construct warns once per occurrence, in the
 * releases that deprecate it (Deprecated::warnedIn()). Terminates: no
 * recursion. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/show-warnings.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the warning of BINARY
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT BINARY 1');
 *     [$query->facts->warnings[0]->construct, $query->facts->warnings[0]->code()] // => [\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::BinaryOperator, 1287]
 */
final class Deprecation implements CodedWarning
{
    use Snapshot;

    /**
     * @param Deprecated $construct The construct warned about
     * @param string $text The text of the warning when it names the construct, or an empty string for the text of the construct
     * @param bool $aborts Whether completing this construct stops parsing after an earlier operand error
     */
    public function __construct(public readonly Deprecated $construct, public readonly string $text = '', public readonly bool $aborts = false)
    {
    }

    /**
     * Records the warning of a system variable the release deprecates, which names the variable and the one to use instead, if any.
     */
    public static function variable(string $name, Derivation $derivation): void
    {
        $text = (new \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\DeprecatedVariables())->warning($name, $derivation->context->profile->grammar);
        if ($text !== null) {
            $derivation->warn(new self(Deprecated::SystemVariable, $text));
        }
    }

    /**
     * Records the replacement name for a legacy spatial property function in MySQL 5.7.
     */
    public static function spatial(string $name, Derivation $derivation): void
    {
        $warning = self::spatialWarning($name, $derivation->context->profile->grammar);
        if ($warning !== null) {
            $derivation->warn($warning, after: false);
        }
    }

    /**
     * Answers the versioned deprecation of an unqualified legacy spatial function name.
     */
    public static function spatialWarning(string $name, \SqlSemantics\Contract\GrammarRelease $release): ?self
    {
        $name = strtoupper($name);

        $replacement = match ($name) {
            'CONTAINS' => 'MBRCONTAINS',
            'X', 'Y', 'GEOMETRYN', 'POINTN', 'STARTPOINT', 'ENDPOINT', 'EXTERIORRING', 'INTERIORRINGN', 'NUMPOINTS', 'NUMINTERIORRINGS', 'ISCLOSED', 'SRID', 'DIMENSION', 'GEOMETRYTYPE', 'ISEMPTY', 'NUMGEOMETRIES', 'ASTEXT', 'ASWKT', 'ASBINARY', 'ASWKB', 'GEOMFROMTEXT', 'GEOMETRYFROMTEXT', 'POINTFROMTEXT', 'LINEFROMTEXT', 'LINESTRINGFROMTEXT', 'POLYFROMTEXT', 'POLYGONFROMTEXT', 'MPOINTFROMTEXT', 'MULTIPOINTFROMTEXT', 'MLINEFROMTEXT', 'MULTILINESTRINGFROMTEXT', 'MPOLYFROMTEXT', 'MULTIPOLYGONFROMTEXT', 'GEOMCOLLFROMTEXT', 'GEOMETRYCOLLECTIONFROMTEXT', 'GEOMFROMWKB', 'GEOMETRYFROMWKB', 'POINTFROMWKB', 'LINEFROMWKB', 'LINESTRINGFROMWKB', 'POLYFROMWKB', 'POLYGONFROMWKB', 'MPOINTFROMWKB', 'MULTIPOINTFROMWKB', 'MLINEFROMWKB', 'MULTILINESTRINGFROMWKB', 'MPOLYFROMWKB', 'MULTIPOLYGONFROMWKB', 'GEOMCOLLFROMWKB', 'GEOMETRYCOLLECTIONFROMWKB' => 'ST_' . $name,
            default => null,
        };

        return Deprecated::SpatialFunction->warnedIn($release) && $replacement !== null
            ? new self(Deprecated::SpatialFunction, "'" . $name . "' is deprecated and will be removed in a future release. Please use " . $replacement . ' instead') : null;
    }

    /**
     * Records the warning of a construct when the release of the derivation warns about it.
     */
    public static function raise(Deprecated $construct, Derivation $derivation, ?\SqlSemantics\Statement\Node $at = null, bool $after = true): void
    {
        if ($construct->warnedIn($derivation->context->profile->grammar)) {
            $derivation->warn(new self($construct), $at, $after);
        }
    }

    /**
     * Records the warning of a character set name an expression writes: the alias utf8, utf8mb3, or ucs2.
     *
     * An introducer, CONVERT ... USING and CAST ... CHARACTER SET warn about the name; a
     * collation name does not (verified on a live 8.4 server).
     */
    public static function charset(string $name, Derivation $derivation, ?\SqlSemantics\Statement\Node $at = null): void
    {
        $construct = self::charsetWarning($name);
        if ($construct !== null) {
            self::raise($construct, $derivation, $at);
        }
    }

    /**
     * Answers the warning descriptor shared by parsed literals and lexical introducers.
     */
    public static function charsetWarning(string $name): ?Deprecated
    {
        return match (strtolower($name)) {
            'utf8' => Deprecated::Utf8Alias,
            'utf8mb3' => Deprecated::Utf8mb3,
            'ucs2' => Deprecated::Ucs2,
            default => null,
        };
    }

    /**
     * Answers when the construct raises its warning.
     * FOUND_ROWS() and system variables warn during contextualization, after grammar operands
     * have been read. Observed with unknown COLLATE operands on MySQL 8.0–9.1.
     */
    public function phase(): NoticePhase
    {
        return in_array($this->construct, [Deprecated::FoundRows, Deprecated::SystemVariable], true) ? NoticePhase::Contextualization : NoticePhase::Syntax;
    }

    /**
     * Answers the error number of the warning.
     */
    public function code(): int
    {
        return $this->construct->code();
    }

    /**
     * Answers the text of the warning.
     */
    public function message(): string
    {
        return $this->text === '' ? $this->construct->value : $this->text;
    }
}
