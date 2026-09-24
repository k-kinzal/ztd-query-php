<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication\Source;

use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Configuration\Replication\ReplicationText;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Checks the option list of CHANGE REPLICATION SOURCE TO as a whole.
 * @visibility SqlSemantics
 */
final class SourceSettings
{
    /**
     * Options that set a log coordinate.
     */
    public const COORDINATES = ['SOURCE_LOG_FILE', 'SOURCE_LOG_POS', 'RELAY_LOG_FILE', 'RELAY_LOG_POS'];

    /**
     * Requires each option once, in a release that accepts it, a password of at most 32 bytes from MySQL 5.7 once the release is known, and coordinates that do not conflict.
     * @param list<SourceSetting> $settings
     * @throws InvalidStructure
     */
    public static function check(Origin $origin, array $settings): void
    {
        Collections::objects($settings, SourceSetting::class);
        $release = ReplicationRelease::of($origin);
        $options = [];
        foreach ($settings as $setting) {
            $option = $setting->option();
            if (isset($options[$option->value]) || ($release ?? PHP_INT_MAX) < $option->since()) {
                throw new InvalidStructure($option->value . ' appears once, in a release that accepts it.');
            }
            $options[$option->value] = $setting;
        }
        if ($release !== null) {
            self::password($release, $settings);
        }
        self::coordinates($options);
    }

    /**
     * Limits SOURCE_PASSWORD to 32 bytes from MySQL 5.7; the binder applies this before the release is attached to the statement.
     * @param list<SourceSetting> $settings
     * @throws InvalidStructure
     */
    public static function password(int $release, array $settings): void
    {
        foreach ($settings as $setting) {
            if ($setting instanceof SourceText && $setting->option === SourceOption::Password && $release >= 50700 && strlen(ReplicationText::check($setting->value, 'A password')) > 32) {
                throw new InvalidStructure('A replication password has at most 32 characters.');
            }
        }
    }

    /**
     * Rejects log coordinates together with SOURCE_AUTO_POSITION = 1, and source log coordinates together with relay log coordinates.
     * @param array<string, SourceSetting> $options
     * @throws InvalidStructure
     */
    public static function coordinates(array $options): void
    {
        $coordinates = array_intersect_key($options, array_flip(self::COORDINATES));
        $automatic = $options[SourceOption::AutoPosition->value] ?? null;
        if ($coordinates !== [] && $automatic instanceof SourceFlag && $automatic->enabled) {
            throw new InvalidStructure('Log coordinates cannot be set together with SOURCE_AUTO_POSITION = 1.');
        }
        $source = isset($coordinates['SOURCE_LOG_FILE']) || isset($coordinates['SOURCE_LOG_POS']);
        $relay = isset($coordinates['RELAY_LOG_FILE']) || isset($coordinates['RELAY_LOG_POS']);
        if ($source && $relay) {
            throw new InvalidStructure('Source log coordinates cannot be set together with relay log coordinates.');
        }
    }
}
