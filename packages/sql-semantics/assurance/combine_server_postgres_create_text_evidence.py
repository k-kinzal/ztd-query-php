"""Replay native PostgreSQL text table declaration evidence."""

import run_server_postgres_create_text
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_create_text)
