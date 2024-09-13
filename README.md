Do Not Use This Fork
====================

This fork is meant for development only. If you are looking for the *FAU CRIS Wordpress plug-in*, you will find the official release at https://github.com/RRZE-Webteam/fau-cris.

Why There Is This Fork 
======================
The purpose here is that we want to generate lists of publications by field of research that include top level publications als well as publications associated with projects within the respective field. We believe that this is a commonly desired feature; see also https://github.com/RRZE-Webteam/fau-cris/issues/317. Referring to the CRIS API, we want to merge the results from *getrelated/Forschungsbereich/.../fobe_has_top_publ* with *getrelated/Forschungsbereich/.../fobe_proj_publ*.

We have added the following features:
- the shortcut option *field_incl_proj* as an alternative to *field*; it is operational in conjunction with *show="publications"* to get the listing we are aiming for;
- the shortcut option *muteheadings* that mutes the automatic year headings; the rational here is that we want to use the existing sorting by year/author already implemented by *pubNachJahr* over a small number of years without the sometimes annoing intermediate headings;
- the custom substitution pattern *#publications_incl_projects#* as an alternative to the existing *#publications#* and *#project_publications#* to get the list we are aiming for; relative to our needs this is optional and we are happy to remove it should it be an obstacle for a merge.

Example of ussage

    [cris show="publications" field_incl_proj="123456789" start="2022" muteheadings="1" quotation="apa"]

Our edits to the original code are worth as little as about 20 lines and systematically follow the observed coding style. The edits are exclusive to *fau-cris.php*, *Publikationen.php* and *Forschungsbereiche.php*. They are all signed "LRT" for easy inspection. We hope for a merge in near future. 
