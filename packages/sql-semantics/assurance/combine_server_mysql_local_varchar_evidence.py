"""Replay the native MySQL local VARCHAR column metadata and row evidence."""

from combine_server_join_evidence import main
import run_server_mysql_local_varchar


if __name__ == "__main__":
    main(run_server_mysql_local_varchar)
