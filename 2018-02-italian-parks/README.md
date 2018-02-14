# Italian Ministry of the Environment parks data import script

## Consensus

* [ ] [Wikidata:Requests for permissions/Bot/Valerio Bozzolan bot 4](https://www.wikidata.org/wiki/Wikidata:Requests_for_permissions/Bot/Valerio_Bozzolan_bot_4)

## Usage

    ./italian-parks-uniformer.php

## Data files
* [data/italian-license-plate-codes.csv](data/italian-license-plate-codes.csv) available under public domain
    * retrieved from Wikidata using [this SPARQL query](https://query.wikidata.org/#SELECT%20%3Fcode%20%3Fitem%20%3FitemLabel%0AWHERE%20%0A%7B%0A%20%20%3Fitem%20wdt%3AP31%20wd%3AQ15089%20.%20%23%20province%20of%20Italy%0A%20%20%3Fitem%20wdt%3AP395%20%3Fcode.%20%20%23%20plate%0A%20%20SERVICE%20wikibase%3Alabel%20%7B%20bd%3AserviceParam%20wikibase%3Alanguage%20%22it%22.%20%7D%0A%7D%20ORDER%20BY%20%3Fcode):
    ```
    SELECT ?code ?item ?itemLabel
    WHERE {
    	?item wdt:P31 wd:Q15089 . # province of Italy
    	?item wdt:P395 ?code.  # plate
    	SERVICE wikibase:label { bd:serviceParam wikibase:language "it". }
    } ORDER BY ?code
    ```

* [data/italian-parks-data.ods](data/italian-parks-data.ods) available under public domain
    * retrieved from the Ministry of the Environment (Italy) <http://www.minambiente.it>
