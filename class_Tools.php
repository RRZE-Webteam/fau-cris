<?php
require_once("class_Dicts.php");

class Tools {

	public static function getAcronym($acadTitle) {
		$acronym = '';
		foreach (explode(' ', $acadTitle) as $actitle) {
			if (array_key_exists($actitle, CRIS_Dicts::$acronyms) && Dicts::$acronyms[$actitle] != '') {
				$acronym .= " " . CRIS_Dicts::$acronyms[$actitle];
			}
			$acronym = trim($acronym);
		}
		return $acronym;
	}

	public static function getPubName($pub, $lang) {
		return CRIS_Dicts::$pubNames[$pub][$lang];
	}

	public static function getPubTranslation($pub) {
		foreach (CRIS_Dicts::$pubNames as $pubindex) {
			//print $pub;
			//print_r($pubindex['en']);
			//print_r($pubindex['de']);
			if ($pubindex['de'] == $pub) {
				echo "de";
				return $pubindex['en'];
			} elseif ($pubindex['en'] == $pub) {
				/*echo "en";
				print $pubindex['de'];
				print_r(self::$pubNames[$pubindex]);*/
				return $pubindex['de'];
			}
		}
	}

	public static function XML2obj($xml_url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $xml_url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$xml = curl_exec($ch);
		curl_close($ch);

		libxml_use_internal_errors(true);
		try {
			$xmlTree = new SimpleXMLElement($xml);

		} catch (Exception $e) {
			// Something went wrong.
			$error_message = 'Fehler beim Einlesen der Daten.';
			foreach(libxml_get_errors() as $error_line) {
				$error_message .= "\t" . $error_line->message;
			}
			trigger_error($error_message);
			return false;
		}
		return $xmlTree;
	}

	/*
	 * Array sortieren
	 */

	public static function record_sortByName($results) {

		// Define the custom sort function
		function custom_sort ($a, $b) { return (strcasecmp ($a['lastName'],$b['lastName']));}
		// Sort the multidimensional array
		uasort($results, "custom_sort");
		return $results;
	}

	public static function record_sortByYear($results) {

		// Define the custom sort function
		function custom_sort_year($a, $b) {
			return $a['publYear'] < $b['publYear'];
		}
		// Sort the multidimensional array
		uasort($results, "custom_sort_year");
		return $results;
	}

	public static function sort_key(&$sort_array, $keys_array) {
		if(empty($sort_array) || !is_array($sort_array) || empty($keys_array)) return;
		if(!is_array($keys_array)) $keys_array = explode(',',$keys_array);
		if(!empty($keys_array)) $keys_array = array_reverse($keys_array);
		foreach($keys_array as $n){
			if(array_key_exists($n, $sort_array)){
				$newarray = array($n=>$sort_array[$n]); //copy the node before unsetting
				unset($sort_array[$n]); //remove the node
				$sort_array = $newarray + array_filter($sort_array); //combine copy with filtered array
			}
		}
		return $sort_array;
	}

	public static function person_exists($firstname, $lastname) {
		global $wpdb;

		$person = $wpdb->esc_like( $firstname ). '%' . $wpdb->esc_like( $lastname );
		$sql = "SELECT COUNT(*) FROM $wpdb->posts WHERE post_title LIKE %s AND post_type = 'person'";
		$sql = $wpdb->prepare( $sql, $person );
		$person_count = $wpdb->get_var( $sql );

		return $person_count;
	}

	public static function person_slug($firstname, $lastname) {
		global $wpdb;

		$person = $wpdb->esc_like( $firstname ). '%' . $wpdb->esc_like( $lastname );
		$sql = "SELECT post_name FROM $wpdb->posts WHERE post_title LIKE %s AND post_type = 'person'";
		$sql = $wpdb->prepare( $sql, $person );
		$person_slug = $wpdb->get_var( $sql );

		return $person_slug;
	}

}