"""Replay exact PostgreSQL typed Boolean write and result observations."""

import run_server_postgres_boolean_writes
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_boolean_writes)
