# Importer of multimedia files from https://iconoteca.arc.usi.ch/

## Description

Welcome in the importer of multimedia files for https://iconoteca.arc.usi.ch/.

For more information about the consensus:

https://it.wikipedia.org/wiki/Wikipedia:Raduni/Biblioteca_dell%27Accademia_di_Mendrisio_4_ottobre_2020

Example:

https://commons.wikimedia.org/w/index.php?title=File:Arnolfo_di_Cambio._Busto_di_Bonifacio_VIII_presso_le_Grotte_vaticane.jpg&action=submit

## Installation

From this directory:

```
git clone https://github.com/phpquery/phpquery
```

## Usage ##

First download locally one of their collections.

Note that the server does not allow more than ~2000 images at time. So do it in two tranches using some available HTTP parameters. For example:

```
wget 'https://iconoteca.arc.usi.ch/it/ricerca?isPostBack=1&id_fondo=212&start=0&step=2000'
wget 'https://iconoteca.arc.usi.ch/it/ricerca?isPostBack=1&id_fondo=212&start=2000&step=2000'
```

Then you can examine that HTML page and bulk-download the available images from it:

```
./parse-html-and-import.php collection-asd.html
```

The you can bulk-upload your files just selecting your directory with the images/metadata and selecting a template:

```
./upload.php images/ template/collezione-biblioteca.php
```

Here all the options of the `upload.php` script:

```
Usage:
 ./upload.php [OPTIONS] path/data/ path/template/name.tpl

Allowed OPTIONS:
 --porcelain           Do nothing
 --preview             Show a preview of the saved wikitext
 --force-upload        Force a re-upload even if the page exists
 --no-report           Don't create a report
 --no-update           Do not try to update something that already exists
 --start-from=VALUE    Start from a specific row (default to 1)
 --limit=VALUE         Process only this number of results
 --nick=VALUE          Nickname used to prefix indexes
 --help|-h             Show this help and quit
```

You can make a script to process a limited batch:

```
./process-corboz.sh
```

In short. Happy hacking!

## License

Copyright (C) 2020 Valerio Bozzolan

This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.
