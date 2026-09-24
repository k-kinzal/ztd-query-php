<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Session;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\Profile\ProfileCategory;
use SqlSemantics\Model\Query\Inspection\TextField;
use SqlSemantics\Type\Nullability;

/**
 * Result fields of SHOW PROFILES and SHOW PROFILE; each profile category adds its own fields.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Session\ProfileField::QueryId->label() // => 'Query_ID'
 */
enum ProfileField: string implements MetadataField
{
    use TextField;

    case QueryId = 'Query_ID';
    case Duration = 'Duration';
    case Query = 'Query';
    case Status = 'Status';
    case CpuUser = 'CPU_user';
    case CpuSystem = 'CPU_system';
    case ContextVoluntary = 'Context_voluntary';
    case ContextInvoluntary = 'Context_involuntary';
    case BlockOpsIn = 'Block_ops_in';
    case BlockOpsOut = 'Block_ops_out';
    case MessagesSent = 'Messages_sent';
    case MessagesReceived = 'Messages_received';
    case PageFaultsMajor = 'Page_faults_major';
    case PageFaultsMinor = 'Page_faults_minor';
    case Swaps = 'Swaps';
    case SourceFunction = 'Source_function';
    case SourceFile = 'Source_file';
    case SourceLine = 'Source_line';

    /**
     * Counters are integers and durations are decimal seconds.
     */
    public function type(): string
    {
        return match ($this) {
            self::QueryId, self::ContextVoluntary, self::ContextInvoluntary, self::BlockOpsIn, self::BlockOpsOut, self::MessagesSent, self::MessagesReceived, self::PageFaultsMajor, self::PageFaultsMinor, self::Swaps, self::SourceLine => 'bigint',
            self::Duration, self::CpuUser, self::CpuSystem => 'numeric',
            self::Query, self::Status, self::SourceFunction, self::SourceFile => 'varchar',
        };
    }

    /**
     * Category measurements are absent when the platform does not report them.
     */
    public function nullability(): Nullability
    {
        return match ($this) {
            self::QueryId, self::Duration, self::Query, self::Status => Nullability::NotNull,
            self::CpuUser, self::CpuSystem, self::ContextVoluntary, self::ContextInvoluntary, self::BlockOpsIn, self::BlockOpsOut, self::MessagesSent, self::MessagesReceived, self::PageFaultsMajor, self::PageFaultsMinor, self::Swaps, self::SourceFunction, self::SourceFile, self::SourceLine => Nullability::MaybeNull,
        };
    }

    /**
     * @return list<self> Fields in result order for SHOW PROFILES
     */
    public static function summary(): array
    {
        return [self::QueryId, self::Duration, self::Query];
    }

    /**
     * @param list<ProfileCategory> $categories Requested categories in request order
     * @return list<self> Status, duration, and the fields of each measured category in result order
     */
    public static function detail(array $categories): array
    {
        $fields = [self::Status, self::Duration];
        foreach (ProfileCategory::measured($categories) as $category) {
            array_push($fields, ...match ($category) {
                ProfileCategory::BlockIo => [self::BlockOpsIn, self::BlockOpsOut],
                ProfileCategory::ContextSwitches => [self::ContextVoluntary, self::ContextInvoluntary],
                ProfileCategory::Cpu => [self::CpuUser, self::CpuSystem],
                ProfileCategory::Ipc => [self::MessagesSent, self::MessagesReceived],
                ProfileCategory::PageFaults => [self::PageFaultsMajor, self::PageFaultsMinor],
                ProfileCategory::Swaps => [self::Swaps],
                ProfileCategory::Source => [self::SourceFunction, self::SourceFile, self::SourceLine],
                ProfileCategory::Memory, ProfileCategory::All => [],
            });
        }
        return $fields;
    }
}
