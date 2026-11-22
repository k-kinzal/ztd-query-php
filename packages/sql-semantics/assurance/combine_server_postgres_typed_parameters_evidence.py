"""Replay checked PostgreSQL typed-parameter observations."""

from combine_server_join_evidence import main
import run_server_postgres_typed_parameters

if __name__ == "__main__":
    main(run_server_postgres_typed_parameters)
