=== ElasticSearch WordPress Plugin ===
Contributors: @cubuzoa & SearchBox.io team
Donate link: 
Tags: search lucene elasticsearch
Requires at least: 3.4
Tested up to: 3.4
Stable tag: trunk
License: Apache License, Version 2.0
License URI: http://www.apache.org/licenses/LICENSE-2.0

ElasticSearch WordPress Plugin replaces the default Wordpress search with ElasticSearch. 

== Description ==

By moving to ElasticSearch you will be able to benefit from Lucene's capabilities which provides a more relevant search experience and you will reduce the load on your SQL server by avoiding the search on the database.


== Installation ==

1) Download zip or tar file and extract it as wp-elasticsearch to "wp-content/plugins/". 
2) Next you need to go to the Wordpress dashboard and activate the plugin.
3) You need to set the url of your ElasticSearch server. This plugin uses Elastica under the hood, so the comminucation needs to be in HTTP. If you run ElasticSearch on your local computer with the default settings, this ourl will be "http://localhost:9200" You can also sign up for a free account at www.searchbox.io get a valid API key and configure this plugin to use SearchBox.io as well.
4) Indexing Operations
After the initial install, you might want to sync your posts with the ElasticSearch server, for this click "Index All Posts"
You can delete all the documents from ElasticSearch by clicking "Delete Documents". This will not delete anything from your database but only delete the ElasticSearch server contents.

For all the other cases, such as posting a new article or deleting one, the plugin will aotumatically sync the operation to ElasticSearch by default, you can change this behaviour from "Indexing Configurations"
5) Indexing Configurations
The index name on ElasticSearch is "Wordpress" by default, but you can change this default name to anything you like
6) Search Result Configurations and Enabling Faceted Search
To enable faceted search on the search results page, navigate to "Appereance -> Widgets", then simply drag and drop "ElasticSearch Facet Widget" to toe right, on to the Primary Widget Area.



== Screenshots ==

1. (http://searchbox-io.github.com/wp-elasticsearch/images/s01.png)
2. (http://searchbox-io.github.com/wp-elasticsearch/images/s02.png)


== Frequently Asked Questions ==

Nothing So far

 == Changelog ==

1.0 - initial release

== Upgrade Notice ==

Nothing So far






