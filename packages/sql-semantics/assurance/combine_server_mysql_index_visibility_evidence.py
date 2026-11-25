"""Replay native MySQL secondary index visibility under the frozen contract."""

from combine_server_join_evidence import main
import run_server_mysql_index_visibility

if __name__ == "__main__":
    main(run_server_mysql_index_visibility)
