<?php

declare(strict_types=1);

namespace SqlCatalog\Extension;

use Override;
use SqlCatalog\Sql\StatementKind;

/**
 * WordPress's `wpdb`, where nearly all of the SQL an installation issues is written.
 *
 * `wpdb::prepare()` interpolates a statement and hands it back for one of the
 * reading or writing methods to run, so it is recognised as the place the
 * statement text comes from rather than as a call the analyzer cannot see
 * through. That is what keeps the familiar
 * `$wpdb->get_results($wpdb->prepare(…))` readable as the statement it is.
 *
 * @visibility root
 */
final class WordPressExtension implements ExtensionInterface
{
    private const DATABASE = 'wpdb';

    /**
     * The name the command line selects this extension by.
     */
    #[Override]
    public function name(): string
    {
        return 'wordpress';
    }

    /**
     * What the extension covers.
     */
    #[Override]
    public function description(): string
    {
        return 'WordPress wpdb, including the statements wpdb::prepare() interpolates';
    }

    /**
     * The wpdb calls that carry a statement or hand one back.
     *
     * @return list<SinkSpec>
     */
    #[Override]
    public function sinks(): array
    {
        $sinks = [
            new SinkSpec(
                'wordpress.prepare',
                SinkCallKind::Method,
                self::DATABASE,
                'prepare',
                SinkRole::Compose,
                sqlParameter: 0,
                valuesFrom: 1,
            ),
        ];
        foreach ($this->methods() as $method => $kind) {
            $sinks[] = new SinkSpec(
                'wordpress.' . $method,
                SinkCallKind::Method,
                self::DATABASE,
                $method,
                SinkRole::Query,
                sqlParameter: 0,
                kind: $kind,
            );
        }

        return $sinks;
    }

    /**
     * The global the handle is reached through.
     *
     * Almost every statement WordPress issues is written after `global $wpdb;`,
     * which says nothing about what the name holds. Declaring it here is what
     * lets those calls be read as the database calls they are.
     *
     * @return array<string, string>
     */
    #[Override]
    public function globals(): array
    {
        return [self::DATABASE => self::DATABASE];
    }

    /**
     * The wpdb methods that take a statement, and the kind each implies.
     *
     * @return array<string, StatementKind|null>
     */
    public function methods(): array
    {
        return [
            'query' => null,
            'get_results' => StatementKind::Select,
            'get_row' => StatementKind::Select,
            'get_col' => StatementKind::Select,
            'get_var' => StatementKind::Select,
            'get_col_info' => StatementKind::Select,
        ];
    }
}
