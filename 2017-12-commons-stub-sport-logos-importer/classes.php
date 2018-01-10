<?php
# Copyright (C) 2017 Valerio Bozzolan
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <http://www.gnu.org/licenses/>.

/**
 * A CSV row of a Sport.
 */
class Sport {
	function __construct( $itwikiprefix, $ensport, $ensportmen, $itsport, $itsportmen, $commonscat ) {
		$this->itwikiprefix = $itwikiprefix;
		$this->ensport      = $ensport;
		$this->ensportmen   = $ensportmen;
		$this->itsport      = $itsport;
		$this->itsportmen   = $itsportmen;
		$this->commonscat   = $commonscat;
	}

	static function createFromData( $data ) {
		return new self(
			$data[0],
			$data[1],
			$data[2],
			$data[3],
			$data[4],
			$data[5]
		);
	}
}

/**
 * A CSV row of a Nation.
 */
class Nation {
	function __construct( $itpeople, $ennation ) {
		$this->itpeople = $itpeople;
		$this->ennation = $ennation;
	}

	static function createFromData( $data ) {
		return new self(
			$data[0],
			$data[1]
		);
	}
}
