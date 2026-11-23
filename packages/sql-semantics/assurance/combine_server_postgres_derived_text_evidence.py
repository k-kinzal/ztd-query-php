"""Replay PostgreSQL native text exports of independently scoped derived inputs."""

import run_server_postgres_derived_text
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_derived_text)
