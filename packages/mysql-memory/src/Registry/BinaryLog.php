<?php

declare(strict_types=1);

namespace MySqlMemory\Registry;

/**
 * The binary log files of the server, in the order of the binary log index.
 *
 * The files are named binlog.NNNNNN after their number. The emulator writes no event for the
 * statements it runs, so each file holds the two events every binary log starts with: the format
 * description (positions 4 to 127, see described()) and the previous GTIDs (127 to 158). A file rotated away from
 * ends with a rotate event naming the next one.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/binary-log.html,
 * https://dev.mysql.com/doc/refman/8.4/en/reset-binary-logs-and-gtids.html.
 *
 * @visibility MySqlMemory
 */
final class BinaryLog
{
    /**
     * @var list<int> The numbers of the files in the index; the last is the active file
     */
    public array $files = [1];

    /**
     * Tells whether the session's server writes a binary log: log_bin is on.
     */
    public static function enabled(\MySqlMemory\Session\Session $session): bool
    {
        return !in_array(strtoupper((string) $session->variables->read('log_bin')), ['0', 'OFF', ''], true);
    }

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
     * Answers the position after the format description event of a release.
     *
     * The event lists the header length of each event type, so it grew by a byte when MySQL 8.3
     * added the event of tagged GTIDs: it ends at 126 in MySQL 8.0 to 8.2 (verified on a live 8.0
     * server) and at 127 from 8.3.
     * Source: https://dev.mysql.com/doc/relnotes/mysql/8.3/en/news-8-3-0.html.
     */
    public static function described(string $version): int
    {
        return str_starts_with($version, '8.0.') || str_starts_with($version, '8.1.') || str_starts_with($version, '8.2.') ? 126 : 127;
    }

    /**
     * Answers the events of a file, each a list of the position, type, server id, end position and information.
     *
     * @return list<array{int, string, int, int, string}>
     */
    public function events(int $number, string $version): array
    {
        $described = self::described($version);
        $start = $described + 31;
        $events = [[4, 'Format_desc', 1, $described, 'Server ver: ' . $version . ', Binlog ver: 4'], [$described, 'Previous_gtids', 1, $start, '']];
        $index = array_search($number, $this->files, true);
        if (is_int($index) && $index < count($this->files) - 1) {
            $next = self::name($this->files[$index + 1]);
            $events[] = [$start, 'Rotate', 1, $start + 31 + strlen($next), $next . ';pos=4'];
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
