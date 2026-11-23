"""Replay native MySQL derived VARCHAR joins and exact fault witnesses."""

from combine_server_join_evidence import main
import run_server_mysql_derived_varchar


if __name__ == "__main__":
    main(run_server_mysql_derived_varchar)
