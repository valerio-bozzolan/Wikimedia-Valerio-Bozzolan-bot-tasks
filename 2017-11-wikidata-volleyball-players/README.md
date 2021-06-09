# 2017, 2018, 2019, 2020, 2021 Wikidata/Commons volleyball players bot

This PHP bot was developed to uniform volleyball players in Wikimedia Commons and Wikidata.

This bot is powered by `boz-mw`:

* https://gitpull.it/w/first_steps_with_boz-mw/ 

## Usage

```
./bot.php
```

## Help

```
 ________________________________________________________________ 
|                                                                |
| Welcome in the Legavolley Commons/Wikidata bot                 |
| Designed by Cristian Cenci and implemented by Valerio Bozzolan |
| Since 2017 at your service! bip.                               |
|________________________________________________________________|

Usage: ./bot.php [OPTIONS] 

All the OPTIONS:
 --players-file=VALUE  CSV file with your volleyball players
 --players-cat=VALUE   The name of your category without namespace containing files in Wikimedia Commons
 --nat-file=VALUE      Nationalities CSV
 --year=VALUE          CSV year (default VALUE: 2021)
 --from=VALUE          Starting point (row)
 --wikidata-sandbox=VALUE  Wikidata QID element to be used as sandbox
 --sparql=VALUE        SPARQL file
 --always              Always save without ask
 --debug               Enable debug mode
 --inspect             Enable inspect mode
```

## Changeset

* [Wikimedia Commons](https://commons.wikimedia.org/w/index.php?limit=500&title=Special%3AContributions&contribs=user&target=Valerio+Bozzolan+bot&namespace=&tagfilter=&start=2019-01-17&end=2019-01-22)
* [Wikidata](https://www.wikidata.org/w/index.php?limit=500&title=Special%3AContributions&contribs=user&target=Valerio+Bozzolan+bot&namespace=&tagfilter=&start=2019-01-14&end=2019-01-22)

## Consensus

* [Upstream discussion on the Italian Wikipedia about Wikimedia Commons](https://it.wikipedia.org/wiki/Speciale:PermaLink/93103795#Categorie_e_descrizioni)
* [Upstream discussion on the Italian Wikipedia about Wikidata](https://it.wikipedia.org/wiki/Speciale:PermaLink/92672746#Collegamento_a_Wikidata)
* [Bot consensus and discussion on Wikimedia Commons](https://commons.wikimedia.org/wiki/Commons:Bots/Requests/Valerio_Bozzolan_bot)
* [Bot consensus for Wikidata](https://www.wikidata.org/wiki/Wikidata:Requests_for_permissions/Bot/Valerio_Bozzolan_bot_2)
* [Bot consensus for Wikimedia Commons](https://commons.wikimedia.org/wiki/Commons:Bots/Requests/VolleyballBot)
* [Bot consensus for Wikidata](https://www.wikidata.org/wiki/Wikidata:Requests_for_permissions/Bot/VolleyballBot)

## Wikimedia files involved

* [Category:2018 files from Legavolley stream](https://commons.wikimedia.org/wiki/Category:2018_files_from_Legavolley_stream)
* [Category:2017 files from Legavolley stream](https://commons.wikimedia.org/wiki/Category:2017_files_from_Legavolley_stream)
* [Category:Volleyball by country](https://commons.wikimedia.org/wiki/Category:Volleyball_by_country)
* Wikimedia Common's [Module:Depicted person](https://commons.wikimedia.org/wiki/Module:Depicted_people) (created)
* Wikimedia Common's [Template:Depicted person](https://commons.wikimedia.org/wiki/Template:Depicted_person) ([diff](https://commons.wikimedia.org/w/index.php?title=Template%3ADepicted_person&type=revision&diff=265201552&oldid=233297362))

## License

Copyright (C) 2017, 2018, 2019, 2020, 2021 Valerio Bozzolan and contributors

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.
