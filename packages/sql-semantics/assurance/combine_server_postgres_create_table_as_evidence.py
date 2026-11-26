"""Independently replay PostgreSQL creation of a table from a resolved query."""

import run_server_postgres_create_table_as
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_create_table_as)
