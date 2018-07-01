# 2018 Wikidata soccer players Lega Serie A importer bot

This PHP bot was developed to import Lega Serie A soccer players IDs.

## Consensus
* [X] [Discussion on Italian Wikipedia](https://it.wikipedia.org/wiki/Discussioni_template:Lega_Calcio#Non_funziona?)
* [X] [Bot consensus for Wikidata](https://www.wikidata.org/wiki/Wikidata:Requests_for_permissions/Bot/Valerio_Bozzolan_bot_5)
* [X] [Lega Serie A soccer player ID consensus on Wikidata](https://www.wikidata.org/wiki/Wikidata:Property_proposal/Legaseriea.it_ID)

## Data
These files are available in public domain because they consists in informations obtained from public [URLs](https://en.wikipedia.org/wiki/URL) ineligibles for copyright.

* [players.csv](data/players.csv) ([sources](data/source.urls))

## Usage

    ./scrape.sh  # generate the CSV file

## License

Copyright (C) 2018 Valerio Bozzolan

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.
