#!/bin/sh
# A stupid script to generate the data/players.csv file

while read url; do
	if [ "$url" ]; then
		wget -O - "$url" | ./parse.php >> data/players.csv
		sleep 5
	fi
done < data/source.urls

# sort and unique
sort data/players.csv | uniq > data/players.csv.uniq
mv   data/players.csv.uniq     data/players.csv
