"""Replay the UTF-8 case table and local column binding evidence."""

from combine_server_join_evidence import main
import run_server_mysql_native_local_column_names


if __name__ == "__main__":
    main(run_server_mysql_native_local_column_names)
