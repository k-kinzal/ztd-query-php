"""Replay checked native MySQL B-tree index evidence."""

from combine_server_join_evidence import main
import run_server_mysql_create_btree_index

if __name__ == "__main__":
    main(run_server_mysql_create_btree_index)
