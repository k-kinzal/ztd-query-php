<?php

declare(strict_types=1);

namespace MySqlMemory\Registry;

/**
 * The binary log files of the server, in the order of the binary log index.
 *
 * The files are named binlog.NNNNNN after their number. The emulator writes no event for the
 * statements it runs, so each file holds the two events every binary log starts with: the format
 * description (positions 4 to 127) and the previous GTIDs (127 to 158). A file rotated away from
 * ends with a rotate event naming the next one.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/binary-log.html,
 * https://dev.mysql.com/doc/refman/8.4/en/reset-binary-logs-and-gtids.html.
 *
 * @visibility MySqlMemory
 */
final class BinaryLog
{
    /**
     * The position after the events every binary log file starts with.
     */
    public const START = 158;

    /**
     * @var list<int> The numbers of the files in the index; the last is the active file
     */
    public array $files = [1];

    /**
     * Answers the name of a file.
     */
    public static function name(int $number): string
    {
        return sprintf('binlog.%06d', $number);
    }

    /**
     * Answers the name of the active file.
     */
    public function active(): string
    {
        return self::name($this->files[count($this->files) - 1]);
    }

    /**
     * Answers the events of a file, each a list of the position, type, server id, end position and information.
     *
     * @return list<array{int, string, int, int, string}>
     */
    public function events(int $number, string $version): array
    {
        $events = [[4, 'Format_desc', 1, 127, 'Server ver: ' . $version . ', Binlog ver: 4'], [127, 'Previous_gtids', 1, self::START, '']];
        $index = array_search($number, $this->files, true);
        if (is_int($index) && $index < count($this->files) - 1) {
            $next = self::name($this->files[$index + 1]);
            $events[] = [self::START, 'Rotate', 1, self::START + 31 + strlen($next), $next . ';pos=4'];
        }

        return $events;
    }

    /**
     * Answers the size of a file in bytes.
     */
    public function size(int $number, string $version): int
    {
        $events = $this->events($number, $version);

        return $events[count($events) - 1][3];
    }

    /**
     * Answers the number of a file named in the index, or null when the index has none of that name.
     *
     * A name may be written with a leading `./`, as the index lists the files.
     */
    public function find(string $name): ?int
    {
        $bare = str_starts_with($name, './') ? substr($name, 2) : $name;
        foreach ($this->files as $number) {
            if (self::name($number) === $bare) {
                return $number;
            }
        }

        return null;
    }

    /**
     * Closes the active file and opens the next.
     */
    public function rotate(): void
    {
        $this->files[] = $this->files[count($this->files) - 1] + 1;
    }

    /**
     * Deletes every file and starts again from a number.
     */
    public function reset(int $first = 1): void
    {
        $this->files = [$first];
    }

    /**
     * Deletes the files before one.
     */
    public function purge(int $number): void
    {
        $this->files = array_values(array_filter($this->files, static fn (int $file): bool => $file >= $number));
    }
}
