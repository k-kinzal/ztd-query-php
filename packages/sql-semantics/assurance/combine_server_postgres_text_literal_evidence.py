"""Replay native PostgreSQL text literal observations for both pinned releases."""

import run_server_postgres_text_literal
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_text_literal)
