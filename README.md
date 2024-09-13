Do Not Use This Fork
====================

This fork is meant for experimental use only. If you are looking for the *FAU CRIS Wordpress plug-in*, you will find the official release at https://github.com/RRZE-Webteam/fau-cris.

The purpose here is that we want to generate publication lists by field that include top level publications als well as publications associated with projects within the field; see also https://github.com/RRZE-Webteam/fau-cris/issues/317. Referring to the CRIS API, we want to merge the results from *getrelated/Forschungsbereich/123455789/fobe_has_top_publ* with *getrelated/Forschungsbereich/123455789/fobe_proj_publ*.

We opted for the absolute minmum of changes in the code, in the hope that it can be merged with the official release in near future. The edits are only a view lines and all signed "LRT". We have added the following features
- the shortcut option *field_incl_proj* as an alternative to *field*; it is operational in conjunction with *show="publications* to get the listing we are aiming for;
- the shortcut option *muteheadings* that mutes the automatic year headings; the rational here is that want to use the existing sorting by year/author already implemented by *pubNachJahr* over a number of years without the sometimes anoing intermediate headings
- the custom substitue *#publications_incl_projects#* as an alternative to the existing *#project_publications#* 
