=== FAU CRIS ===
Contributors: rrze-webteam
Tags: research, cris, fau, publications, projects
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.29.24
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Displays research data from the FAU research portal CRIS on WordPress pages.

== Description ==

Embeds data from the research information system CRIS of
Friedrich-Alexander-Universität Erlangen-Nürnberg into WordPress pages through
the `[cris]` shortcode.

The following record types are supported:

* Publications
* Awards
* Patents
* Research activities
* Research projects
* Research areas
* Research infrastructure
* Standardization work
* UN Sustainable Development Goals
* Persons

Each record type can be listed for an organisation or a person, grouped by year,
type or role, rendered as a list or an accordion, and restricted with the `hide`
attribute. Single records can be addressed by their CRIS id.

Detailed documentation is available at
https://www.wordpress.rrze.fau.de/plugins/fau-und-rrze-plugins/fau-cris/

== Installation ==

1. Upload the plugin to the `/wp-content/plugins/` directory, or install it
   through the WordPress plugin screen.
2. Activate the plugin through the Plugins screen.
3. Enter the CRIS organisation number under Settings > CRIS.
4. Add a shortcode such as `[cris show=publications]` to a page.

== Frequently Asked Questions ==

= Which PHP modules are required? =

php-curl and php-intl.

= Where do the data come from? =

From the public CRIS webservice at cris.fau.de. Responses are cached as
transients, so repeated page views do not query the webservice again.

== Changelog ==

= 3.29.24 =
* Plugin Check: added this readme, a translators comment, and documented
  suppressions for the shared RRZE logging hook
* Removed two unreferenced developer scratch scripts

= 3.29.23 =
* Hid contact details and job title on the SDG contributor cards

= 3.29.22 =
* Loaded the FAUdir stylesheet on pages that render CRIS person cards

= 3.29.19 =
* SDG tags link to the central FAU sustainability pages

= 3.29.18 =
* Showed the person UNSDGDescription statement in the person view

= 3.29.14 =
* Added the UN Sustainable Development Goals component

Earlier entries: https://github.com/RRZE-Webteam/fau-cris/releases
