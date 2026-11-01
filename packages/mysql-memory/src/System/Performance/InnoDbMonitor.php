<?php

declare(strict_types=1);

namespace MySqlMemory\System\Performance;

use MySqlMemory\Instance;

/**
 * Reports the in-memory transaction system in the InnoDB monitor's sections.
 * Physical files, background workers, buffer pools and redo buffers have zero activity.
 * Transaction sequence, retained history and read views come from the actual instance.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-standard-monitor.html.
 *
 * @visibility MySqlMemory
 */
final class InnoDbMonitor
{
    /**
     * Formats a complete monitor report for the selected release.
     */
    public function report(Instance $instance): string
    {
        $legacy = str_starts_with($instance->version, '5.6.');
        $time = date($legacy ? 'ymd H:i:s' : 'Y-m-d H:i:s');
        $text = "\n=====================================\n" . $time . ' ' . (string) getmypid() . " INNODB MONITOR OUTPUT\n=====================================\nPer second averages calculated from the last 0 seconds\n";
        $sections = [
            'BACKGROUND THREAD' => "srv_master_thread loops: 0 srv_active, 0 srv_shutdown, 0 srv_idle\nsrv_master_thread log flush and writes: 0\n",
            'SEMAPHORES' => "OS WAIT ARRAY INFO: reservation count 0\nOS WAIT ARRAY INFO: signal count 0\nRW-shared spins 0, rounds 0, OS waits 0\nRW-excl spins 0, rounds 0, OS waits 0\n" . ($legacy ? '' : "RW-sx spins 0, rounds 0, OS waits 0\n") . 'Spin rounds per wait: 0.00 RW-shared, 0.00 RW-excl' . ($legacy ? '' : ', 0.00 RW-sx') . "\n",
            'TRANSACTIONS' => $this->transactions($instance),
            'FILE I/O' => "Pending flushes (fsync) log: 0; buffer pool: 0\n0 OS file reads, 0 OS file writes, 0 OS fsyncs\n0.00 reads/s, 0 avg bytes/read, 0.00 writes/s, 0.00 fsyncs/s\n",
            'INSERT BUFFER AND ADAPTIVE HASH INDEX' => "Ibuf: size 0, free list len 0, seg size 0, 0 merges\nmerged operations:\n insert 0, delete mark 0, delete 0\ndiscarded operations:\n insert 0, delete mark 0, delete 0\n0.00 hash searches/s, 0.00 non-hash searches/s\n",
            'LOG' => $this->log($instance),
            'BUFFER POOL AND MEMORY' => $this->buffers(),
            'ROW OPERATIONS' => '0 queries inside InnoDB, ' . count($instance->transactions->locks->waits) . " queries in queue\n" . count($instance->transactions->views) . " read views open inside InnoDB\nProcess ID=" . (string) getmypid() . ", Main thread ID=0 , state=sleeping\nNumber of rows inserted 0, updated 0, deleted 0, read 0\n0.00 inserts/s, 0.00 updates/s, 0.00 deletes/s, 0.00 reads/s\n" . (str_starts_with($instance->version, '5.') ? '' : "Number of system rows inserted 0, updated 0, deleted 0, read 0\n0.00 inserts/s, 0.00 updates/s, 0.00 deletes/s, 0.00 reads/s\n"),
        ];
        foreach ($sections as $name => $body) {
            $separator = str_repeat('-', strlen($name));
            $text .= $separator . "\n" . $name . "\n" . $separator . "\n" . $body;
        }
        return $text . "----------------------------\nEND OF INNODB MONITOR OUTPUT\n============================\n";
    }

    /**
     * Reports active transactions without inventing native memory addresses or lock allocations.
     */
    public function transactions(Instance $instance): string
    {
        $system = $instance->transactions;
        $text = 'Trx id counter ' . $system->sequence . "\nPurge done for trx's n:o < " . $system->sequence . " undo n:o < 0 state: running but idle\nHistory list length " . count($system->history) . "\nLIST OF TRANSACTIONS FOR EACH SESSION:\n";
        foreach ($system->sessions as $transaction) {
            $locks = $system->locks->owned[$transaction->id] ?? [];
            $rows = array_sum(array_map(count(...), $locks));
            $text .= '---TRANSACTION ' . $transaction->id . ', ' . ($transaction->engaged ? 'ACTIVE 0 sec' : 'not started') . "\n" . count($locks) . ' lock struct(s), heap size 0, ' . $rows . " row lock(s)\n";
        }
        return $text;
    }

    /**
     * Reports the physical redo fields present in the selected release.
     */
    public function log(Instance $instance): string
    {
        $fields = ['Log sequence number'];
        if (!str_starts_with($instance->version, '5.')) {
            $fields = [...$fields, 'Log buffer assigned up to', 'Log buffer completed up to', 'Log written up to'];
        }
        $fields[] = 'Log flushed up to';
        if (!str_starts_with($instance->version, '5.')) {
            $fields[] = 'Added dirty pages up to';
        }
        $fields = [...$fields, 'Pages flushed up to', 'Last checkpoint at'];
        if (!str_starts_with($instance->version, '5.')) {
            $fields = [...$fields, 'Log minimum file id is', 'Log maximum file id is'];
        }
        return implode("\n", array_map(static fn (string $field): string => $field . ' 0', $fields)) . "\n0 log i/o's done, 0.00 log i/o's/second\n";
    }

    /**
     * Reports zero native buffer allocations and their corresponding access rates.
     */
    public function buffers(): string
    {
        return "Total large memory allocated 0\nDictionary memory allocated 0\nBuffer pool size 0\nFree buffers 0\nDatabase pages 0\nOld database pages 0\nModified db pages 0\nPending reads 0\nPending writes: LRU 0, flush list 0, single page 0\nPages made young 0, not young 0\n0.00 youngs/s, 0.00 non-youngs/s\nPages read 0, created 0, written 0\n0.00 reads/s, 0.00 creates/s, 0.00 writes/s\nNo buffer pool page gets since the last printout\nPages read ahead 0.00/s, evicted without access 0.00/s, Random read ahead 0.00/s\nLRU len: 0, unzip_LRU len: 0\nI/O sum[0]:cur[0], unzip sum[0]:cur[0]\n";
    }
}
