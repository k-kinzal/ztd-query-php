"""Replay PostgreSQL Boolean literal contexts against the fixed input table."""

import run_server_postgres_boolean_write_inputs
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_boolean_write_inputs)
