<?php

declare(strict_types=1);

namespace SqlCatalog\Cli;

use SqlCatalog\Core\Extension\ExtensionRegistry;
use SqlCatalog\Core\Reporter\ReporterRegistry;

/**
 * What the command prints when it is asked how to be used.
 *
 * @visibility root
 */
final class UsageText
{
    private ExtensionRegistry $extensions;

    private ReporterRegistry $reporters;

    /**
     * Builds the help over the extensions and reporters a run has available.
     */
    public function __construct(ExtensionRegistry $extensions, ReporterRegistry $reporters)
    {
        $this->extensions = $extensions;
        $this->reporters = $reporters;
    }

    /**
     * The full help text.
     */
    public function help(): string
    {
        return implode("\n", [
            'sql-catalog — catalog the SQL statements a PHP application issues',
            '',
            'Usage:',
            '  sql-catalog [options] <path>...',
            '',
            'Output:',
            '  -o, --output=DIR       Write the report into DIR instead of standard output',
            '  -r, --reporter=NAME    Render with NAME (default: text on standard output, json into a directory)',
            '',
            'What to read:',
            '  -c, --config=FILE      Read catalog settings (default: .catalog.yaml)',
            '  -e, --extension=NAME   Recognise the database calls of NAME; repeatable (default: pdo,mysqli)',
            '      --dialect=NAME     Framework builder grammar: mysql, pgsql or sqlite',
            '      --exclude=PATTERN  Skip source files whose reported path matches; repeatable',
            '      --root=DIR         Report paths relative to DIR (default: the working directory)',
            '',
            'What to keep:',
            '      --namespace=NS     Keep statements issued under a namespace; repeatable',
            '      --method=NAME      Keep statements issued in a function, as name or Class::method; repeatable',
            '      --path=PATTERN     Keep statements from files matching a pattern; repeatable',
            '      --kind=KIND        Keep statements of a kind, such as select or insert; repeatable',
            '      --table=NAME       Keep statements naming a table; repeatable',
            '      --sink=ID          Keep statements found at a database call, such as pdo.prepare; repeatable',
            '      --severity=LEVEL   Keep statements reported at LEVEL or above',
            '',
            'Exit status:',
            '      --fail-on=LEVEL    Exit with 1 when a statement is reported at LEVEL or above',
            '',
            'Information:',
            '      --list-extensions  List the extensions this build recognises',
            '      --list-reporters   List the reporters this build can render with',
            '  -h, --help             Show this help',
            '',
            'Values given to a repeatable option may also be written separated by commas.',
            '',
        ]);
    }

    /**
     * The extensions, one per line.
     */
    public function extensions(): string
    {
        $lines = [];
        foreach ($this->extensions->all() as $extension) {
            $lines[] = sprintf('  %-10s %s', $extension->name(), $extension->description());
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * The reporters, one per line.
     */
    public function reporters(): string
    {
        $lines = [];
        foreach ($this->reporters->all() as $reporter) {
            $lines[] = sprintf('  %-10s %s', $reporter->name(), $reporter->description());
        }

        return implode("\n", $lines) . "\n";
    }
}
