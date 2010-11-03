<?php
/**
 * =====================================================
 * Multisite Search Helper for EE
 * =====================================================
 * File: pi.multisite_search.php
 * 
 * For providing a very simple cross-site search
 * in multisite environments.   
 * 
 * Version:		1.1.0
 * Author:		Corey Snipes <corey@twomile.com>
 * Date:		11/11/2008
 * =====================================================
 */

$plugin_info = array (
	'pi_name' => 'Twomile Multisite Search Helper',
	'pi_version' => '1.1.0',
	'pi_author' => 'Corey Snipes',
	'pi_author_url' => 'http://www.twomile.com/',
	'pi_description' => 'Adds a very simple cross-site search function for multi-site environments.',
	'pi_usage' => Multisite_search :: usage());

class Multisite_search
{

	// ----------------------------------------
	//  Class params
	// ----------------------------------------

	var $return_data;
	

	/**
	 * For display of the user's search term.
	 */
	function search_term()
	{
		// function params

		global $OUT, $DB;

		// ----------------------------------------
		//  Parse and display search text
		// ----------------------------------------

		if (!isset ($_POST["search_term"]))
		{
			$OUT->show_user_error("general", "Search phrase could not be determined.");
		}
		return $DB->escape_str($_POST["search_term"]);

	}


	/**
	 * For performing a keyword search across all sites. 
	 */
	function search()
	{
		
		// function params

		global $TMPL, $FNS, $OUT, $DB, $PREFS;
		$search_term = NULL;
		$min_search_term_size = 3;
		$custom_fields = array ();
		$current_site_id = 1;
		$sites = array ();
		$site_pages = array ();
		$sql = NULL;
		$status_list = NULL;
		$status_count = -1;
		$status_default = "'open'"; 

		// ----------------------------------------
		//  Parse search text
		// ----------------------------------------

		if (!isset ($_POST["search_term"]))
		{
			$OUT->show_user_error("general", "Search phrase could not be determined.");
		}
		$search_term = strtolower($DB->escape_str($_POST["search_term"]));

		if (strlen($search_term) < $min_search_term_size)
		{
			$OUT->show_user_error("submission", "Please enter a search phrase at least " . $min_search_term_size . " characters long.");
		}

		// ----------------------------------------
		//  Parse status list
		// ----------------------------------------
		
		if ($TMPL->fetch_param("status"))
		{
			$status_list = explode("|", $TMPL->fetch_param("status"));
			$status_count = count($status_list);
			for ($i=0; $i<$status_count; $i++)
			{
				$status_list[$i] = "'" . $status_list[$i] . "'"; 
			}
			$status_list = implode(",", $status_list);
		}
		else
		{
			$status_list = $status_default;
		}
		

		// ----------------------------------------
		//  Get URLs for pages
		// ----------------------------------------

		$current_site_id = $PREFS->ini("site_id");
		$query = $DB->query(" SELECT site_id FROM exp_sites ");
		foreach ($query->result as $row)
		{
			array_push($sites, $row["site_id"]);
		}
		if (count($sites) < 1) array_push($sites, '1');

		foreach ($sites as $one_site)
		{
			$PREFS->site_prefs('', $one_site);
			$site_pages[$one_site] = $PREFS->ini('site_pages');
		}
		$PREFS->site_prefs('', $current_site_id);

		// ----------------------------------------
		//  Get list of searchable custom fields
		// ----------------------------------------

		$query = $DB->query(
			" SELECT f.field_id " .
			"   FROM exp_weblog_fields f " .
			"  WHERE field_search = 'y'"
		);

		foreach ($query->result as $row)
		{
			array_push($custom_fields, "field_id_" . $row["field_id"]);
		}

		// ----------------------------------------
		//  Search weblog entries
		// ----------------------------------------

		$sql =	"     SELECT wt.entry_id, wt.title, wt.url_title, wt.site_id, wt.entry_date, w.search_results_url, s.site_label " .
				"       FROM exp_weblogs w " .
				" INNER JOIN exp_weblog_titles wt ON (w.weblog_id = wt.weblog_id) " .
				" INNER JOIN exp_weblog_data wd ON (wt.entry_id = wd.entry_id) " .
				" INNER JOIN exp_sites s ON (wt.site_id = s.site_id) " .
				"      WHERE ( " .
				"            LOWER(wt.title) LIKE '%" . $search_term . "%' ";

		foreach ($custom_fields as $one_custom_field)
		{
			$sql .= " OR LOWER(wd." . $one_custom_field . ") LIKE '%" . $search_term . "%' ";
		}

		$sql .= "            ) ";
		$sql .= "        AND wt.status IN (" . $status_list . ") ";
		
		$query = $DB->query($sql);

		// -------------------------------------------
		//  Return, if empty 
		// -------------------------------------------

		if ($query->num_rows === 0)
		{
			return $TMPL->no_results();
		}

		// -------------------------------------------
		//  Set template values for each record
		// -------------------------------------------

		foreach ($query->result as $row)
		{
			$tagdata = $TMPL->tagdata;
			foreach ($TMPL->var_single as $key => $val)
			{
				if ($key == "entry-id")
				{
					$tagdata = $TMPL->swap_var_single($val, $row["entry_id"], $tagdata);
				}
				elseif ($key == "entry-title")
				{
					$tagdata = $TMPL->swap_var_single($val, $row["title"], $tagdata);
				}
				elseif ($key == "entry-date")
				{
					$tagdata = $TMPL->swap_var_single($val, date("F j, Y", $row["entry_date"]), $tagdata);
				}
				elseif ($key == "site-name")
				{
					$tagdata = $TMPL->swap_var_single($val, $row["site_label"], $tagdata);
				}
				elseif ($key == "entry-url")
				{
					$item_url = $row["search_results_url"];
					if (isset ($site_pages[$row["site_id"]]["uris"][$row["entry_id"]]))
					{
						$item_url .= $site_pages[$row["site_id"]]["uris"][$row["entry_id"]];
					} else
					{
						$item_url .= "/" . $row["url_title"];
					}
					$tagdata = $TMPL->swap_var_single($val, $item_url, $tagdata);
				}
			}
			$this->return_data .= $tagdata;
		}

		// -------------------------------------------
		//  Return
		// -------------------------------------------

		return $this->return_data;

	}

	/**
	 * For display of usage info for this plugin.
	 */
	function usage()
	{
		ob_start();
?>
		
		This plugin adds a very simple cross-site search feature for your multisite EE
		environment.  It works outside the normal EE search feature, so you may use either
		or both	as you see fit.  It is not a feature-rich plugin, but it does respect
		open-vs-closed entry statuses, and it does check your search-enabled custom
		weblog fields.  Weblog entries configured as pages in the Pages module are
		linked to their page URI.  All other entries are linked to the path provided
		in the weblog preferences.  To use this plugin, configure your weblog search
		results path, then add a results page and search form, as below.
		 
		
		1) Weblog Search Results Paths
		
		Edit the preferences for each weblog in each of your sites.  The important
		setting here is Path Settings -> Search Results URL.  If the weblog serves 
		pages for the Pages module, ensure the path is just the web root for the 
		weblog's site, e.g.,
		
		http://site-a.example.com
		
		If the weblog doesn't serve Pages, set the path to the appropriate detail 
		page for the given weblog entry type.  e.g.,
		
		http://site-a.example.com/noodles/detail   
		
		
		2) The Results Page
		
		Create a template to display your search results.  To display the user's posted 
		search term, use the tag: {exp:multisite_search:search_term}
		
		e.g., 
		
		<p>You searched for: '{exp:multisite_search:search_term}'</p>
		  
		Use the 'search' tag pair to display and format your search results.  A limited 
		set of weblog information is available for display in the search results.  Everything
		within the tag pair will be repeated for each result.  If there are no results
		found, the {if no_results} tag may be used to display something helpful.
		The available tags are:
		
		{entry-id}
		{entry-title}
		{entry-date}
		{site-name}
		{entry-url}
		
		e.g.,
		
		<p>
		{exp:multisite_search:search}
		
			&bull;   <a href='{entry-url}'>{entry-title} ({site-name})</a> - <i>{entry-date}</i><br/>
			
			{if no_results}
				Sorry, we didn't find anything to match your search.  Please try again.<br/>
			{/if}
	
		{/exp:multisite_search:search}
		</p>
		
		You may specify one or more entry statuses to include in your search.  If you don't specify one, 
		the default status "open" will be used.  e.g., if you have some entries with a custom status of 
		"approved" that you want to include as well, you could add the status parameter thusly:
		
		{exp:multisite_search:search status="open|approved"}
		...
		
		
		3) The Search Form
		 
		Add a small HTML form for the search box on your site.  Style it any way you like.  
		Be sure it contains a text input called "search_term", and be sure the form posts
		to your results page.
		
		e.g.,
		
		<form action="/site/search-post" method="post">
			Search: <input name="search_term" id="search_term" type="text" class="searchField" value="SEARCH" size="12" /> 
			<input type="submit" name="search_submit" id="search_submit"/>
		</form>
		  		
		<?php

		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	}

}
?>