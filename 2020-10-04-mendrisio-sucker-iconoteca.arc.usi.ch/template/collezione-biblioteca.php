<?php
# Copyright (C) 2020 Valerio Bozzolan
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <http://www.gnu.org/licenses/>.

// this is a template to build a generic Commons file description

?>
=={{int:filedesc}}==
{{Information
|description=<?= $DESCRIPTION ?>

|date=<?= $DATE ?>

|source=<?= $SOURCE ?>

|author=<?= $AUTHOR ?>

|permission=
|other versions=
}}

=={{int:license-header}}==
<?= $LICENSE_TEMPLATES ?>

== {{int:metadata}} ==
{| class="wikitable"
<?php
	$first = true;
	foreach( $METADATA as $key => $value ) {

		// line row
		if( !$first ) {
			echo "|-\n";
		}

		echo "! $key\n";
		echo "| $value\n";

		$first = false;
	}
?>
|}

[[Category:Collezione Biblioteca - Iconoteca dell'architettura in Mendrisio, Switzerland]]
