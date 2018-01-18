<?php
# Copyright (C) 2018 Valerio Bozzolan
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as published by
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
 * Everything about a template parameter
 *
 * @param $param string Template parameter
 * @param $category_name_on_wikidata Hidden category for an argument value that it's also in Wikidata
 * @param $category_missing_on_wikidata Hidden category for an argument value that is missing from Wikidata
 * @param $callback_statement callable
 */
class Param {
	public function __construct( $param, $property, $category_same_on_wikidata, $category_missing_on_wikidata, $callback_statement ) {
		$this->param                     = $param;
		$this->property                  = $property;
		$this->categorySameOnWikidata    = $category_same_on_wikidata;
		$this->categoryMissingOnWikidata = $category_missing_on_wikidata;
		$this->callbackStatement         = $callback_statement;
	}

	public function getParam() {
		return $this->param;
	}

	public function getProperty() {
		return $this->property;
	}

	public function getCategorySameOnWikidata() {
		return $this->categorySameOnWikidata;
	}

	public function getCategoryMissingOnWikidata() {
		return $this->categoryMissingOnWikidata;
	}

	public function getStatementFromValue( $value ) {
		$func = $this->callbackStatement;
		return $func( $value, $this );
	}

	public function getParamPattern( $argument_group_name = null, $argument_closer_group_name = null ) {
		$param = preg_quote( $this->getParam() );
		$argument        = \regex\Generic::groupNamed( $this->getArgumentPattern() , $argument_group_name );
		$argument_closer = \regex\Generic::groupNamed( '[\n\t ]*' . '(\||}})', $argument_closer_group_name );
		return '/' . '[\n\t ]*' . '\|' . '[\n\t ]*' . $param . '[\n\t ]*' . '=' . '[\n\t ]*' . $argument . $argument_closer . '/';
	}

	/**
	 * TODO:
	 * @see https://www.wikidata.org/wiki/Property:P1793
	 */
	public function getArgumentPattern() {
		return '[a-zA-Z0-9.-]+';
	}
}

/**
 * A stupid Param collector
 */
class Params {
	var $params;

	public function __construct( $params ) {
		$this->params = [];
		foreach( $params as $param ) {
			$this->addParam( $param );
		}
	}

	public function getAll() {
		return $this->params;
	}

	public function addParam( Param $param ) {
		$this->params[] = $param;
	}

	public function getFromCategorySameOnWikidata( $category_same_on_wikidata ) {
		return $this->getFromCallback( function( Param $param ) use ( $category_same_on_wikidata ) {
			return $param->getCategorySameOnWikidata() === $category_same_on_wikidata;
		} );
	}

	public function getFromCategoryMissingOnWikidata( $category_missing_on_wikidata ) {
		return $this->getFromCallback( function( Param $param ) use ( $category_missing_on_wikidata ) {
			return $param->getCategoryMissingOnWikidata() === $category_missing_on_wikidata;
		} );
	}

	public function getFromParam( $param ) {
		return $this->getFromCallback( function( Param $param ) use ( $param ) {
			return $param->getParam() === $param;
		} );
	}

	public function getParams() {
		return array_map(
			$this->getAll(), function ( Param $param ) {
				return $param->getParam();
			},
			$this->getAll()
		);
	}

	public function getCategoriesSameOnWikidata() {
		return array_map(
			function ( Param $param ) {
				return $param->getCategorySameOnWikidata();
			},
			$this->getAll()
		);
	}

	public function getCategoriesMissingOnWikidata() {
		return array_map(
			function ( Param $param ) {
				return $param->getCategoryMissingOnWikidata();
			},
			$this->getAll()
		);
	}

	private function getFromCallback( $callback ) {
		foreach( $this->getAll() as $param ) {
			if( $callback( $param ) ) {
				return $param;
			}
		}
		throw new Exception('missing param with this callback');
	}
}
