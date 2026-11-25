"""Replay the mixed MySQL index alteration ledgers."""

from combine_server_join_evidence import main
import run_server_mysql_mixed_index_alteration

if __name__ == "__main__":
    main(run_server_mysql_mixed_index_alteration)
