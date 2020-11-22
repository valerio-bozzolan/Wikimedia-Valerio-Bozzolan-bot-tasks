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

/**
 * Metadata
 *
 * Basically the Titolo opera, Titolo originale etc. from:
 *   https://iconoteca.arc.usi.ch/it/inventario/51630
 */
class Metadata {

	public $label;

	public $valueAdapter;

	/**
	 * Constructor
	 *
	 * @param string   $label Metadata label e.g. 'Titolo opera'
	 * @param function $value_adapter Optional callable
	 */
	public function __construct( $label, $value_adapter = null ) {
		$this->label = $label;
		$this->valueAdapter = $value_adapter;
	}

	/**
	 * Get the text of the label
	 *
	 * Basically from 'foo' its 'foo:'
	 *
	 * @return string
	 */
	public function getLabel() {
		return $this->label . ':';
	}

	/**
	 * Check if a label matches the one of this metadata
	 *
	 * @return bool
	 */
	public function matchesLabel( $label ) {
		return $this->getLabel() === $label;
	}

	/**
	 * Create a MetadataValue object from a value
	 *
	 * Note that the value will be adapted.
	 *
	 * @param mixed $value
	 * @return Metadatavalue
	 */
	public function createValue( $value ) {

		// eventually apply the custom value adapter
		if( $this->valueAdapter ) {
			$user_adapter = $this->valueAdapter;
			$value = $user_adapter( $value );
		} else {
			// otherwise apply the default value adapter
			$value = self::defaultValueAdapter( $value );
		}

		// eventually fix HTML links
		$value = html_link_2_wikitext( $value );

		return new MetadataValue( $this, $value );
	}

	/**
	 * Default value adapter
	 *
	 * Note: as default the value is the paragraph selector. So we strip the label and get the clean data.
	 *
	 * @param string $img_metadata_p
	 * @return string
	 */
	private static function defaultValueAdapter( $img_metadata_p ) {

		// text displayed after the label (manually stripping the label)
		$img_metadata_p_text = $img_metadata_p->html();

		// label
		// it contains 'Titolo originale:'
		$img_metadata_p_label = $img_metadata_p->find( 'label' );

		// label text
		// e.g. 'Titolo originale:'
		$img_metadata_p_label_html = $img_metadata_p_label->html();

		// complete text of the paragraph stripping its label
		$img_metadata_p_text = trim( str_replace( "<label>$img_metadata_p_label_html</label>", '', $img_metadata_p_text ) );

		return $img_metadata_p_text;

	}
}
