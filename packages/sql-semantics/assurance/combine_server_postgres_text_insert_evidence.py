"""Replay PostgreSQL typed VALUES cell and physical effect observations."""

import run_server_postgres_text_insert
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_text_insert)
