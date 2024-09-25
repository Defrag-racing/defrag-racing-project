# CN = Column Name

# columns names in table 'records', in 'defrag-racing-project' database
CN_RECORD_ID = 'id'
CN_PLAYER_NAME = 'name'
CN_PLAYER_COUNTRY = 'country'
CN_PHYSICS = 'physics' #cpm, vq3
CN_MODE = 'mode' #run, ctf1, ctf2, etc.
CN_TYPE = 'gametype' #run_cpm, run_vq3, ctf1_cpm, etc.
CN_TIME_MS = 'time'
CN_DATE_SET = 'date_set'
CN_MDD_PLAYER_ID = 'mdd_id'
CN_DR_PLAYER_ID = 'user_id'
CN_MAP = 'mapname'
CN_RANK = 'rank' #index of time on given map and physics
CN_BEST_TIME = 'besttime'
CN_DELETED_AT = 'deleted_at'
CN_CREATED_AT = 'created_at'
CN_UPDATED_AT = 'updated_at'

# my column names
CN_INDEX = 'index'
CN_MAP_SCORE = 'map_score'
CN_RECORD_RANK = 'record_rank' #index of time on given map and physics
CN_PLAYER_SCORE_RANK = 'player_score_rank' #index of score for given player, physics and mode
CN_PLAYER_SCORE_WEIGHTED = 'player_score_weighted' #weighted score for given player, physics and mode
CN_TOTAL_PARTICIPATORS = 'total_participators' #for given map, physics and mode
CN_PLAYER_RATING = 'player_rating' #final rating for given player, physics and mode
CN_TOP1_TIME = 'top1_time'
CN_TOP2_TIME = 'top2_time'

# environment variables
ENV_DB_USERNAME = 'DB_USERNAME'
ENV_DB_PASSWORD = 'DB_PASSWORD'
ENV_DB_HOST = 'DB_HOST'
ENV_DB_PORT = 'DB_PORT'
ENV_DB_DATABASE = 'DB_DATABASE'

DEFAULT_DB_USERNAME = 'sail'
DEFAULT_DB_PASSWORD = 'password'
DEFAULT_DB_HOST = 'localhost'
DEFAULT_DB_PORT = '3306'
DEFAULT_DB_DATABASE = 'defrag-racing-project'

# table names
TABLE_RECORDS = 'records' #original table from defrag.racing
TABLE_RECORDS_SCORES = 'records_scores' #cleared up 'records' table with added scores
TABLE_PLAYERS_RATINGS = 'players_ratings' #final players ratings in all categories

# other constants
CN_TAG_BANNED = 'banned'

MODE_RUN = 'run'
MODE_CTF = 'ctf'

PHYSICS_CPM = 'cpm'
PHYSICS_VQ3 = 'vq3'

# Ranking refers to an entity's position in a sorted list.
# Rating is a measure of an entity's performance, quality, or capability.
# While both can be used to compare entities,
# a rating provides additional information about the magnitude of difference between them.

