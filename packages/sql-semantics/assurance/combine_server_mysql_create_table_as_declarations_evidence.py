"""Recheck creation with explicit signed definitions on each pinned MySQL release."""

import run_server_mysql_create_table_as_declarations
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_mysql_create_table_as_declarations)
