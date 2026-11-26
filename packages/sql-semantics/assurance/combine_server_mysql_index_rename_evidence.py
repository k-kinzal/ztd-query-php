"""Replay the ordered native MySQL index-rename ledgers."""

from combine_server_join_evidence import main
import run_server_mysql_index_rename

if __name__ == "__main__":
    main(run_server_mysql_index_rename)
