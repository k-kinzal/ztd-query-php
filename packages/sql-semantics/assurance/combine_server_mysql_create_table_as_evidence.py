"""Recheck raw comparisons for each pinned MySQL query-backed table profile."""

import run_server_mysql_create_table_as
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_create_table_as)
