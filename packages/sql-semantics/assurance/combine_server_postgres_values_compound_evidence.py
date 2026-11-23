"""Independently replay PostgreSQL VALUES compound observations."""

import run_server_postgres_values_compound
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_values_compound)
