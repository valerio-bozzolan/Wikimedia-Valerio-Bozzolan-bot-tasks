# 2021-03 Wikimedia CH's members wiki OAuth analysis

This is a small script for [Wikimedia CH](https://wikimedia.ch/)'s members wiki OAuth analysis.

https://members.wikimedia.ch/

## Technical details

https://phabricator.wikimedia.org/T278140

## Installation

```
git clone --recurse-submodules https://gitpull.it/source/Wikimedia-Valerio-Bozzolan-bot-tasks/
```

## Usage

```
cd here

./step-1-download-wmch-users.php
./step-2-check-wmch-users.php
./step-3-print-wikitable.php
```

Then do something with your `report.wiki`.

## License

Copyright (C) 2021 Valerio Bozzolan

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.
