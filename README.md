End-Users Avoid This Fork
=========================

This fork is meant for development only. If you are looking for the *FAU-CRIS Wordpress plug-in*, you will find the official release at https://github.com/RRZE-Webteam/fau-cris.

Why There Is This Fork 
======================

Regarding the general scope of this Wordpress plug-in, we refer to the original *README.md*, which we moved to *README_original.md* to place the above disclaimer most prominently. The reason for our fork is that we want to generate lists of publications by field of research that include top level publications als well as publications associated with projects within the respective field. We believe that this is a commonly desired feature; see also https://github.com/RRZE-Webteam/fau-cris/issues/317. Referring to the CRIS API, we want to merge the results from *getrelated/Forschungsbereich/.../fobe_has_top_publ* with *getrelated/Forschungsbereich/.../fobe_proj_publ*.

We have added the following features:
- the shortcut option *field_incl_proj* as an alternative to *field*; it is operational in conjunction with *show="publications"* to get the listing we are aiming for;
- the shortcut option *muteheadings* that mutes the automatic year headings; the rational here is that we want to use the existing sorting by year/author already implemented by *pubNachJahr* over a small number of years without the sometimes annoing intermediate headings;
- the custom substitution pattern *#publications_incl_projects#* as an alternative to the existing *#publications#* and *#project_publications#* to get the list we are aiming for; relative to our needs this is optional and we are happy to remove it should it be an obstacle for a merge.

Example of ussage

    [cris show="publications" field_incl_proj="123456789" start="2022" muteheadings="1" quotation="apa"]

Our edits to the original code are worth as little as about 20 lines and systematically follow the observed coding style. The edits are exclusive to *fau-cris.php*, *Publikationen.php* and *Forschungsbereiche.php*. They are all signed "LRT" for easy inspection. We hope for a merge in near future. 


Implementation Detail
---------------------

Technically, we introduce *field_incl_proj* as a virtual entity that is interpreted as an ordinary *field* which implicitly includes the attribute to merge publications according to our liking. This may be regarded unelegant, but the variant *field_proj* already exists in the code and has similar semantics. So we went this way to forward the implicit attribute up the point when it comes to the method *byField* of the class *Publication* where we ultimatively need to decide whether or not to merge top level publications with associated project publications.

As an alternative, we could stick to a plain *field* and add an explicit attribute, say *mergepublications* that takes a boolean value. Tracing the way attribites are handled in the existing code, we would then like to pass the array of all attributes to the constructor of *Publicationen*. Thus, we could test for our attribute in the method *byField*. Ultimatively, many methods of the class *Publikationen* depend on the one or the other attribute and some high-level methods gets them passed explicitly while more low-level methods miss out. From this perspective, it appears natural to keep the attributes as class member. They could still be partially processed on different levels of abstraction, as it is currently implemented. If this is the preferrable way to go, we are happy to take another iteration with again only a small number of lines to edit.

