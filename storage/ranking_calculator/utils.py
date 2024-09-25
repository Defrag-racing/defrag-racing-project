import polars as pl
from globals import *
from records_processing import *
from dotenv import load_dotenv
import argparse
import os
import datetime

LOCAL_DB_FILE_PATH = "./defrag_racing_records.csv"
# LOCAL_DB_FILE_PATH = "./defrag_racing_records_small.csv"

def parse_args():
    # Parse command line arguments for .env file path
    parser = argparse.ArgumentParser()
    parser.add_argument('--env_path', type=str, default=None, help='Path to the .env file')
    args = parser.parse_args()

    # Load .env file if path is provided
    if args.env_path is not None:
        load_dotenv(dotenv_path=args.env_path)

def config_polars():
    pl.Config.set_tbl_cols(-1)
    pl.Config.set_tbl_rows(-1)
    pl.Config.set_fmt_str_lengths(2500)
    pl.Config.set_tbl_width_chars(2500)
    pl.Config.set_fmt_table_cell_list_len(10)
    # pl.Config.set_tbl_hide_column_data_types(True)
    # pl.Config.set_tbl_hide_dataframe_shape(True)

def add_index_col(df):
    df = df.with_row_index(name=CN_INDEX, offset=1)
    return df

def read_mysql_db(table_name, ip=None):
    username = os.getenv(ENV_DB_USERNAME, DEFAULT_DB_USERNAME)
    password = os.getenv(ENV_DB_PASSWORD, DEFAULT_DB_PASSWORD)
    port = os.getenv(ENV_DB_PORT, DEFAULT_DB_PORT)
    db_name = os.getenv(ENV_DB_DATABASE, DEFAULT_DB_DATABASE)

    if ip == None:
        ip = os.getenv(ENV_DB_HOST, DEFAULT_DB_HOST)

    db_uri = f"mysql://{username}:{password}@{ip}:{port}/{db_name}"
    query = f"SELECT * FROM {table_name}"
    df = pl.read_database_uri(query, db_uri)
    return df

def write_mysql_db(df, table_name, ip=None):
    username = os.getenv(ENV_DB_USERNAME, DEFAULT_DB_USERNAME)
    password = os.getenv(ENV_DB_PASSWORD, DEFAULT_DB_PASSWORD)
    port = os.getenv(ENV_DB_PORT, DEFAULT_DB_PORT)
    db_name = os.getenv(ENV_DB_DATABASE, DEFAULT_DB_DATABASE)

    if ip == None:
        ip = os.getenv(ENV_DB_HOST, DEFAULT_DB_HOST)

    db_uri = f"mysql+pymysql://{username}:{password}@{ip}:{port}/{db_name}"
    df.write_database(
        table_name=table_name,
        connection=db_uri,
        if_table_exists='replace'
    )

def write_csv_db(df):
    df.write_csv(LOCAL_DB_FILE_PATH, separator=";", datetime_format="%Y-%m-%d %H:%M:%S")

def read_csv_db():
    df = pl.read_csv(LOCAL_DB_FILE_PATH, separator=";", try_parse_dates=True)
    # df = df.cast({CN_TIME_MS: pl.Duration(time_unit="ms")})
    return df

def log_into_file(text, file_path='my_simple_log.txt'):
    with open(file_path, 'a') as file:
        now = datetime.datetime.now()
        file.write(f'{now}: {text}\n')

def manual_debug(df, players_rating_df):
    df = (df
          # .filter(pl.col(CN_PHYSICS) == PHYSICS_CPM)
          # .filter(pl.col(CN_MDD_PLAYER_ID) == 11254)
          .filter(pl.col(CN_MODE).str.starts_with(MODE_CTF))
          .filter(pl.col(CN_TOTAL_PARTICIPATORS) >= MIN_TOTAL_PARTICIPATORS)
          .sort([CN_MAP, CN_MODE, CN_PHYSICS, CN_RECORD_RANK],
                descending=[False, False, False, False])
          )

    players_rating_df = (players_rating_df
                         .filter(pl.col(CN_PHYSICS) == PHYSICS_CPM)
                         .sort(CN_PLAYER_RATING, descending=True)
                         .head(5)
                         )
    print(df)
    print(players_rating_df)
    exit()
