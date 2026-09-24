<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Orders MySQL grammar releases so replication and server commands can check release-specific forms.
 * @visibility SqlSemantics
 */
final class ReplicationRelease
{
    /**
     * Numbers a release tag as MySQL does (major, minor and patch in two digits each); no tag means the newest release.
     */
    public static function number(?string $grammarVersion): int
    {
        if ($grammarVersion === null || preg_match('/^mysql-(\d+)\.(\d+)\.(\d+)$/D', $grammarVersion, $parts) !== 1) {
            return PHP_INT_MAX;
        }
        return (int) $parts[1] * 10000 + (int) $parts[2] * 100 + (int) $parts[3];
    }

    /**
     * Numbers the release a statement was bound against, or null while its binding context is not attached yet.
     */
    public static function of(Origin $origin): ?int
    {
        $context = $origin->context;
        return $context === null ? null : self::number($context->schema()->grammarVersion);
    }

    /**
     * Whether the release spells replication commands only with the MASTER and SLAVE vocabulary.
     */
    public static function legacy(Origin $origin): bool
    {
        return (self::of($origin) ?? PHP_INT_MAX) < 80000;
    }

    /**
     * Requires MySQL and, once the binding context is attached, a release inside the given bounds.
     * @throws InvalidStructure
     */
    public static function require(Origin $origin, string $form, int $since = 0, int $until = PHP_INT_MAX): void
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure($form . ' requires MySQL.');
        }
        $release = self::of($origin);
        if ($release !== null && ($release < $since || $release > $until)) {
            throw new InvalidStructure($form . ' is not available in this MySQL release.');
        }
    }
}
