"""Replay the native PostgreSQL signed default and assignment ledger."""

import run_server_postgres_create_signed_defaults
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_create_signed_defaults)
