<?php

class Tools {

	/*
	 * Begriffe
	 */
	private static $pubNames = array(
		'zeitschriftenartikel' => array (
			'de' => 'Zeitschriftenartikel',
			'en' => 'Journal article'),
		'sammelbandbeitraege' => array (
			'de' => 'Beiträge in Sammelbänden',
			'en' => 'Article in Edited Volumes'),
		'uebersetzungen' => array (
			'de' => 'Übersetzungen',
			'en' => 'Translation'),
		'buecher' => array (
			'de' => "Bücher",
			'en' => 'Book'),
		'herausgeberschaften' => array (
			'de' => 'Herausgeberschaften',
			'en' => 'Editorial'),
		'konferenzbeitraege' => array (
			'de' => 'Konferenzbeiträge',
			'en' => 'Conference Contribution'),
		'abschlussarbeiten' => array (
			'de' => 'Abschlussarbeiten',
			'en' => 'Thesis'),
		'andere' => array (
			'de' => 'Sonstige',
			'en' => 'Other')
	);

	public static function getPubName($pub, $lang) {
		return self::$pubNames[$pub][$lang];
	}

	public static function getPubTranslation($pub) {
		foreach (self::$pubNames as $pubindex) {
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

	/*
	 * Array sortieren (strings)
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

}