"""Recheck VARCHAR table creation receipts for each pinned MySQL release."""

import run_server_mysql_create_table_as_varchar
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_create_table_as_varchar)
