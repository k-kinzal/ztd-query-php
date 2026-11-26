"""Recheck effects and row-selection relations for every pinned MySQL source profile."""

import run_server_mysql_create_table_as_source
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_create_table_as_source)
