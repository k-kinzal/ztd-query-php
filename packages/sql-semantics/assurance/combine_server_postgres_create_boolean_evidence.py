"""Replay native PostgreSQL Boolean declaration evidence."""

import run_server_postgres_create_boolean
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_create_boolean)
