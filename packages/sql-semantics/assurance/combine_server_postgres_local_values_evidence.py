"""Independently replay local PostgreSQL VALUES comparisons."""

import run_server_postgres_local_values
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_local_values)
