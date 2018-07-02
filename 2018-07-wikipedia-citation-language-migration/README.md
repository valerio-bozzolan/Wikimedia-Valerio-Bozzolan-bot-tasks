# Italian Wikipedia languages migration bot

This is the source code of an Italian Wikipedia bot designed to migrate all the `|language = it en us` parameters to `|language = it, en, us`.

## Consensus

* [X] [Discussion on Italian Wikipedia "Modulo:Citazione" page](https://it.wikipedia.org/wiki/Discussioni_modulo:Citazione#Gestione_lingue_composte_da_pi%C3%B9_di_una_parola) ([permalink](https://it.wikipedia.org/wiki/Speciale:LinkPermanente/98190278#Gestione_lingue_composte_da_pi%C3%B9_di_una_parola))

## Usage

    ./bot.php

If you want to manually check every page, toggle the `$ALWAYS` flag. It's hardcoded in top of the script :^)

## License

Copyright (C) 2018 Valerio Bozzolan

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.
