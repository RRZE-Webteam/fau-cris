<?php

namespace FAU\CRIS;



defined('ABSPATH') || exit;

class Tools {
	public function __construct() {
		include plugin_dir_path(__FILE__) . "dictionary.php";
		$this->typeinfos = $typeinfos;
		$this->cris_publicweb = $cris_publicweb;
		$this->base_uri = $base_uri;
		$this->univis_path = $univis_path;
		$this->bibtex_uri = $bibtex_uri;
		$this->doi = $doi;
	}


	public function getPageLanguage( $postID ) {
		$page_lang_meta = get_post_meta( $postID, 'fauval_langcode', true );
		if ( $page_lang_meta != '' ) {
			$page_lang = ( $page_lang_meta == 'de' ) ? 'de' : 'en';
		} else {
			$page_lang = strpos( get_locale(), 'de' ) === 0 ? 'de' : 'en';
		}

		return $page_lang;
	}

	public function getOrder( $object, $type = '' ) {
		if ( $type == '' ) {
			foreach ( $this->typeinfos[ $object ] as $k => $v ) {
				$order[ $v['order'] ] = $k;
			}
		} else {
			foreach ( $this->typeinfos[ $object ][ $type ]['subtypes'] as $k => $v ) {
				$order[ $v['order'] ] = $k;
			}
		}
		ksort( $order );

		return $order;
	}

	public function getXType( $object, $short, $type = '' ) {
		if ( $type == '' ) {
			foreach ( $this->typeinfos[ $object ] as $k => $v ) {
				if ( $v['short'] == $short ) {
					return $k;
				}
				if ( array_key_exists( 'short_alt', $v ) && $v['short_alt'] == $short ) {
					return $k;
				}
			}
		} else {
			foreach ( $this->typeinfos[ $object ][ $type ]['subtypes'] as $k => $v ) {
				if ( $v['short'] == $short ) {
					return $k;
				}
			}
		}
	}

	public function getName($object, $type, $lang, $subtype = '') {
		$lang = strpos($lang, 'de') === 0 ? 'de' : 'en';
		if ($subtype == '') {
			if (array_key_exists($type, $this->typeinfos[$object])) {
				return $this->typeinfos[$object][$type][$lang]['name'];
			} else {
				return $type;
			}
		} else {
			if (array_key_exists($subtype, $this->typeinfos[$object][$type]['subtypes'])) {
				return $this->typeinfos[$object][$type]['subtypes'][$subtype][$lang]['name'];
			} else {
				return $subtype;
			}
		}
		return $type;
	}

	public function getTitle($object, $name, $lang, $type = '') {
		$lang = strpos($lang, 'de') === 0 ? 'de' : 'en';
		if ($type == '') {
			if (isset($this->typeinfos[$object][$name][$lang]['title']))
				return $this->typeinfos[$object][$name][$lang]['title'];
		} else {
			if (isset($this->typeinfos[$object][$type]['subtypes'][$name][$lang]['title']))
				return $this->typeinfos[$object][$type]['subtypes'][$name][$lang]['title'];
		}
		return $name;
	}

	public function getPersonLink( $id, $firstname, $lastname, $target, $inv = 0, $shortfirst = 0, $nameorder = '' ) {
		$person = '';
		switch ( $target ) {
			case 'cris' :
				if ( is_numeric( $id ) ) {
					$link_pre  = "<a href=\"" . $this->cris_publicweb . "Person/" . $id . "\" class=\"extern\">";
					$link_post = "</a>";
				} else {
					$link_pre  = '';
					$link_post = '';
				}
				break;
			case 'person':
				if ( personExists( $firstname, $lastname, $nameorder ) ) {
					$link_pre  = "<a href=\"" . $this->univis_path . personSlug( $firstname, $lastname, $nameorder ) . "\">";
					$link_post = "</a>";
				} else {
					$link_pre  = '';
					$link_post = '';
				}
				break;
			default:
				$link_pre  = '';
				$link_post = '';
		}
		if ( $id == 0 && $target == 'cris' ) {
			$link_pre  = '';
			$link_post = '';
		}
		if ( $shortfirst == 1 ) {
			if ( strpos( $firstname, ' ' ) !== false ) {
				$firstnames = explode( ' ', $firstname );
			} elseif ( strpos( $firstname, '-' ) !== false ) {
				$firstnames = explode( '-', $firstname );
			} else {
				$firstnames[] = $firstname;
			}
			foreach ( $firstnames as $_fn ) {
				$fn_shorts[] = mb_substr( $_fn, 0, 1 );
			}
			$firstname = implode( '', $fn_shorts ) . '.';
		}
		$name   = $inv == 0 ? $firstname . " " . $lastname : $lastname . " " . $firstname;
		$person = "<span class=\"author\" itemprop=\"author\">" . $link_pre . $name . $link_post . "</span>";

		return $person;
	}

	public function personExists( $firstname = '', $lastname = '', $nameorder = 'firstname-lastname' ) {
		if ( $firstname == '' && $lastname == '' ) {
			return false;
		}
		global $wpdb;
		if ( $nameorder == 'lastname-firstname' ) {
			$person = '%' . $wpdb->esc_like( $lastname ) . '%' . $wpdb->esc_like( $firstname ) . '%';
		} else {
			$person = '%' . $wpdb->esc_like( $firstname ) . '%' . $wpdb->esc_like( $lastname ) . '%';
		}
		$sql       = "SELECT ID FROM $wpdb->posts WHERE post_title LIKE %s AND post_type = 'person' AND post_status = 'publish'";
		$sql       = $wpdb->prepare( $sql, $person );
		$person_id = $wpdb->get_var( $sql );

		return $person_id;
	}

	public function personId( $cms = '', $firstname = '', $lastname = '', $nameorder = 'firstname-lastname'  ) {
		if ( $cms == 'wp' ) {
			global $wpdb;
			if ( $nameorder == 'lastname-firstname' ) {
				$person = '%' . $wpdb->esc_like( $lastname ) . '%' . $wpdb->esc_like( $firstname ) . '%';
			} else {
				$person = '%' . $wpdb->esc_like( $firstname ) . '%' . $wpdb->esc_like( $lastname ) . '%';
			}
			$sql       = "SELECT ID FROM $wpdb->posts WHERE post_title LIKE %s AND post_type = 'person' AND post_status = 'publish'";
			$sql       = $wpdb->prepare( $sql, $person );
			$person_id = $wpdb->get_var( $sql );
		}

		return $person_id;
	}

	public function personSlug( $firstname = '', $lastname = '', $nameorder = '' ) {
		global $wpdb;
		if ( $nameorder == 'lastname-firstname' ) {
			$person = '%' . $wpdb->esc_like( $lastname ) . '%' . $wpdb->esc_like( $firstname ) . '%';
		} else {
			$person = '%' . $wpdb->esc_like( $firstname ) . '%' . $wpdb->esc_like( $lastname ) . '%';
		}
		$sql         = "SELECT post_name FROM $wpdb->posts WHERE post_title LIKE %s AND post_type = 'person' AND post_status = 'publish'";
		$sql         = $wpdb->prepare( $sql, $person );
		$person_slug = $wpdb->get_var( $sql );

		return $person_slug;
	}

	public function getItemUrl( $item, $title, $cris_id, $page_id = '', $lang = 'de' ) {
		// First search in subpages
		$pages = get_pages( array( 'child_of' => $page_id, 'post_status' => 'publish' ) );
		foreach ( $pages as $page ) {
			if ( $page->post_title == $title && ! empty( $page->guid ) ) {
				return get_permalink( $page->ID );
			}
		}
		// No subpage -> search all pages
		$page = get_page_by_title( $title );
		if ( $page && ! empty( $page->ID ) ) {
			return get_permalink( $page->ID );
		} else {
			return $this->cris_publicweb . $item . "/" . $cris_id . ( $lang == 'de' ? '?lang=de_DE' : '?lang=en_GB' );
		}
	}

	public function XML2obj($xml_url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $xml_url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$xml = curl_exec($ch);
		curl_close($ch);

		$xmlTree = '';

		libxml_use_internal_errors(true);
		try {
			$xmlTree = new \SimpleXMLElement($xml);
		} catch (Exception $e) {
			// Something went wrong.

			$error_message = '<strong>' . __('Fehler beim Einlesen der Daten: Bitte überprüfen Sie die CRIS-ID.', 'fau-cris') . '</strong>';
			if (defined('WP_DEBUG') && true === WP_DEBUG) {
				print '<p>';
				foreach (libxml_get_errors() as $error_line) {
					$error_message .= "<br>" . $error_line->message;
				}
				trigger_error($error_message);
				print '</p>';
			} else {
				//print $error_message;
			}
			return false;
		}
		return $xmlTree;
	}


	/*
	* Array zur Definition des Filters für Publikationen
	*/
	public function publication_filter( $parameter ) {
		//var_dump($parameter);
		$filter = array();
		if ( $parameter['year'] !== '' ) {
			$filter['publyear__eq'] = $parameter['year'];
		}
		if ( $parameter['start'] !== '' ) {
			$filter['publyear__ge'] = $parameter['start'];
		}
		if ( $parameter['end'] !== '' ) {
			$filter['publyear__le'] = $parameter['end'];
		}
		if ( $parameter['type'] !== '' ) {
			if ( strpos( $parameter['type'], ',' ) ) {
				$type  = str_replace( ' ', '', $parameter['type'] );
				$types = explode( ',', $type );
				foreach ( $types as $v ) {
					$pubTyp[] = getType( 'publications', $v );
				}
			} else {
				$pubTyp = (array) getType( 'publications', $parameter['type'] );
			}
			if ( empty( $pubTyp ) ) {
				$output = '<p>' . __( 'Falscher Parameter für Publikationstyp', 'fau-cris' ) . '</p>';

				return $output;
			}
			$filter['publication type__eq'] = $pubTyp;
		}
		if ( $parameter['subtype'] !== '' ) {
			$subtype  = str_replace( ' ', '', $parameter['subtype'] );
			$subtypes = explode( ',', $subtype );
			foreach ( $subtypes as $v ) {
				$pubSubTyp[] = getType( 'publications', $v, $pubTyp[0] );
			}
			if ( empty( $pubSubTyp ) ) {
				$output = '<p>' . __( 'Falscher Parameter für Publikationssubtyp', 'fau-cris' ) . '</p>';

				return $output;
			}
			$filter['subtype__eq'] = $pubSubTyp;
		}
		if ( $parameter['fau'] !== '' ) {
			if ( $parameter['fau'] == 1 ) {
				$filter['fau publikation__eq'] = 'yes';
			} elseif ( $parameter['fau'] == 0 ) {
				$filter['fau publikation__eq'] = 'no';
			}
		}
		if ( $parameter['peerreviewed'] !== '' ) {
			if ( $parameter['peerreviewed'] == 1 ) {
				$filter['peerreviewed__eq'] = 'Yes';
			} elseif ( $parameter['peerreviewed'] == 0 ) {
				$filter['peerreviewed__eq'] = 'No';
			}
		}
		if ( $parameter['language'] !== '' ) {
			$language               = str_replace( ' ', '', $parameter['language'] );
			$pubLanguages           = explode( ',', $language );
			$filter['language__eq'] = $pubLanguages;
		}
		if ( $parameter['curation'] == 1 ) {
			$filter['relation curationsetting__eq'] = 'curation_accepted';
		}
		if ( count( $filter ) ) {
			return $filter;
		}

		return null;
	}

	public function numeric_xml_encode( $text, $double_encode = true ) {
		/*
		 * Deliver numerically encoded XML representation of special characters.
		 * E.g. use &#8211; instead of &ndash;
		 *
		 * Adopted from user-contributed notes of
		 * http://php.net/manual/de/function.htmlentities.php
		 *
		 * @param string $text Input text
		 * @param bool $double_encode flag for double encoding (defaults to true)
		 *
		 * @return string $encoded Encoded text representation
		 */
		if ( ! $double_encode ) {
			$text = html_entity_decode( stripslashes( $text ), ENT_QUOTES, 'UTF-8' );
		}
		$html_specials = array( '&', '<', '>', '"' );
		// array of chars (multibyte aware)
		$mbchars = preg_split( '/(?<!^)(?!$)/u', $text );
		$encoded = '';
		foreach ( $mbchars as $char ) {
			if ( in_array( $char, $html_specials ) ) {
				$encoded .= htmlentities( $char );
				continue;
			}
			$o = ord( $char );
			if ( ( mb_strlen( $char ) > 1 ) || /* multi-byte [unicode] */
			     ( $o < 32 || $o > 126 ) || /* <- control / latin weird os -> */
			     ( $o > 33 && $o < 40 ) ||/* quotes + ambersand */
			     ( $o > 59 && $o < 63 ) /* html */
			) {
				// convert to numeric entity
				$char = mb_encode_numericentity( $char,
					array( 0x0, 0xffff, 0, 0xffff ), 'UTF-8' );
			}
			$encoded .= $char;
		}

		return $encoded;
	}

}