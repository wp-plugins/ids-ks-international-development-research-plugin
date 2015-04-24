=== IDS KS International Development Research Plugin ===
Contributors: InstituteofDevelopmentStudies
Tags: ids, import, research, international development
Requires at least: 3.0
Tested up to: 4.2
Stable tag: 1.1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Import thematically organised and hand selected academic research on poverty reduction in the developing world to your WordPress site.

== Description ==

This plugin provides a Wordpress interface for the Institute for Development Studies (IDS)'s [Open API](http://api.ids.ac.uk/).
The goal of the module is to allow retrieving data from the IDS Collections (Eldis and Bridge) for use within a WordPress site.

The IDS KS API plugin allows access to IDS Knowledge Services content of thematically organised and hand selected academic research on poverty reduction in the developing world that is freely available to access online.

IDS Knowledge Services offer two collections that can be accessed via this plugin. They are:

* BRIDGE is a research and information service supporting gender advocacy and mainstreaming efforts by bridging the gaps between theory, policy and practice. BRIDGE acts as a catalyst by facilitating the generation and exchange of relevant, accessible and diverse gender information in print, online and through other innovative forms of communication. BRIDGE hosts a global resources library on its website, which includes gender-focused information materials in a number of languages, including Arabic, Chinese, English, French, Portuguese and Spanish.
* Eldis is an online information service covering development research on a wide change of thematic areas including agriculture, climate change, conflict, gender, governance, and health. Eldis includes over 32,000 summaries and links to free full-text research and policy documents from over 8,000 publishers. Each document is editorially selected by members of our team.

= What content can I get? =
Documents are a wide range of online resources, primarily academic research, on development issues that are freely available online. 
They are editorially selected, organised thematically and are summarised, in clear, non-technical language for easy consumption.

Organisations are a wide range of organisations engaged in reducing poverty. Most of the organisations in our datasets have published documents available in the data. 
Please note: currently there are no organisations in the BRIDGE dataset. Organisational websites are recorded as "documents".

Countries are used to identify either:

* which geographic area the research document covers
* where the research was produced, based on the publisher country
* the countries in which an organisation works

Regions are broad geographic groupings, which enable users to explore our documents and organisations by more than one country.

Themes are development topics which reflect the key themes in development.

= How do I use the plugin? =
To use the plugin, you must obtain a unique Token-GUID or key for the API. Please register for your API key here. Once obtained, enter this key into the IDS API Token-GUID (key) section of the IDS Plugin Settings.

The IDS API package actually has two plugins on offer, both allow the administrator to select content from Eldis, BRIDGE or both collections, and bring relevant content easily into the site.

= IDS Import plugin =

This plugin enables the one time or periodic importing of content into the Wordpress database. IDS documents and organisations are imported as custom Wordpress content types, while IDS regions, countries and themes are imported as Wordpress custom taxonomies. Content and terms can also be imported manually.

You can select the number of items to import and assign to a specific Wordpress user. You can filter the content you import by country, region or theme. It is also possible the filter by publisher or author name. You can integrate content seamlessly into your existing content structures by mapping Eldis or BRIDGE taxonomies to existing Wordpress taxonomies.

So, if you were the administrator of a website that highlighted recent research on ICTs and Gender in Kenya, you would be able to select specific criteria to suit your content. You would select the themes ICT and Gender, as well as the country Kenya.

= IDS View plugin =

This plugin enables the display of a user defined subset of the IDS collection of documents or organisations using the IDS View widget, available in the Wordpress Widget panel.

So, if you were the administrator of a website that highlighted recent research on ICTs and Gender in Kenya, you would be able to select specific criteria to suit your content. You would select the themes ICT and Gender, as well as the country Kenya.

== Installation ==

**Automatic Install**

1. Login to your WordPress site as an Administrator

2. Navigate to Plugins->Add New from the menu on the left

3. Search for *IDS KS International Development Research*

4. Click "Install"

5. Click "Activate Now"

6. Apply for an [API key](http://api.ids.ac.uk/profiles/view) 

7. Configure the plugin

**Manual Install**

1. Download the plugin from the link in the top right corner

2. Unzip the file, and upload the resulting "ids-ks-international-development-research-plugin" folder to your "/wp-content/plugins directory" as "/wp-content/plugins/ids-ks-international-development-research-plugin"

3. Log into your WordPress install as an administrator, and navigate to the plugins screen from the left-hand menu

4. Activate *IDS KS International Development Research Plugin*

5. Apply for an [API key](http://api.ids.ac.uk/profiles/view) 

6. Configure the plugin

== Changelog ==

= 1.1 = 
* Improvements to API wrapper and common files used in other IDS applications
* Added import document types as taxonomies functionality
* Improvements to category mappings
* Bug fixes

= 1.0 =
* First commit