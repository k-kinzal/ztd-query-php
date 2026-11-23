"""Independently replay native PostgreSQL table and column rename evidence."""

import run_server_postgres_table_rename
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_table_rename)
