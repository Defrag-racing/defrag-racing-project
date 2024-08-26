#!/bin/sh

# Check if Sail containers are up
if ! ./vendor/bin/sail ps | grep -q 'Up'; then
    echo "Sail containers are not running, start Sail first and re-run the script"
    exit 1
fi

# Download and copy dummy data
if [ ! -f local_devel/dummy_data.zip ]; then
    wget https://dl.defrag.racing/downloads/defrag-racing-dummy/dummy_data.zip -P local_devel/
fi

rm -rf ./local_devel/dummy_data
unzip ./local_devel/dummy_data.zip -d local_devel/dummy_data/
cp -r ./local_devel/dummy_data/thumbs ./storage/app/public/
./vendor/bin/sail mysql < ./local_devel/dummy_data/dummy_db.sql
