<?php

/**
 * JLARC
 *
 * A parser that gathers up every report issued by Virginia's Joint Legislative Audit and Review
 * Commission (JLARC) that's available on their website, a collection that dates back to 1975.
 * 
 * PHP version 5
 *
 * @author		Waldo Jaquith <waldo at jaquith.org>
 * @copyright	2013 Waldo Jaquith
 * @license		MIT
 * @version		0.1
 * @link		http://www.github.com/openva/jlarc
 * @since		0.1
 *
 */

class JLARC
{
	
	function gather()
	{
		
		/*
		 * Initialize the object in which we'll store the list of reports.
		 */
		$this->reports = new stdClass();
		
		$years = array('1975', '1980', '1990', '2000', '2010');
		
		foreach ($years as $year)
		{
			
			/*
			 * Initialize the object in which we'll store this year's reports.
			 */
			$this->reports->{$year} = new stdClass();
			
			/*
			 * Construct the URL for this year.
			 */
			$url = 'http://jlarc.virginia.gov/GetDataTest.asp?site=20&dataID=rpt&param=jla&param2='
				. $year;
			
			/*
			 * Via cURL, retrieve the contents of the URL.
			 */
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TRUE);
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			$allowed_protocols = CURLPROTO_HTTP | CURLPROTO_HTTPS;
			curl_setopt($ch, CURLOPT_PROTOCOLS, $allowed_protocols);
			curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, $allowed_protocols & ~(CURLPROTO_FILE | CURLPROTO_SCP));
			$html = curl_exec($ch);
			curl_close($ch);
			
			/*
			 * If our query failed, then we can't continue.
			 */
			if ($html === FALSE)
			{
				throw new Exception('cURL could not retrieve content for ' . $url . ', with the
					following error: ' . $curl_error($ch));
			}
			
			/*
			 * This HTML is invalid. Clean it up with HTML Tidy.
			 */
			if (class_exists('tidy', FALSE))
			{
	
				$tidy = new tidy;
				$tidy->parseString($html);
				$tidy->cleanRepair();
				$html = $tidy;
				
			}
	
			elseif (exec('which tidy'))
			{
	
				$filename = '/tmp/' . $period_id .'.tmp';
				file_put_contents($filename, $html);
				exec('tidy --show-errors 0 --show-warnings no -q -m ' . $filename);
				$html = file_get_contents($filename);
				unlink($filename);
	
			}
	
			/*
			 * Render this as an object with PHP Simple HTML DOM Parser.
			 */
			$dom = str_get_html($html);
			
			/*
			 * If this can't be rendered, then there's a serious HTML error.
			 */
			if ($dom === FALSE)
			{
				throw new Exception('Invalid HTML found at ' . $url . '—could not parse.');
			}
			
			
			/*
	 		 * Iterate through the table rows -- each row is a single registration.
			 */
			$i=0;
			foreach ($dom->find('tr.report') as $report)
			{
				
				$this->reports->{$year}->{$i}->id = trim($report->find('td', 0)->plaintext);
				$this->reports->{$year}->{$i}->title = trim($report->find('td', 1)->plaintext);
				$this->reports->{$year}->{$i}->date = trim($report->find('td', 2)->plaintext);
				$this->reports->{$year}->{$i}->fact_sheet_url = trim($report->find('td', 3));
				$this->reports->{$year}->{$i}->report_url = trim($report->find('td', 4));
				$this->reports->{$year}->{$i}->briefing_url = trim($report->find('td', 5));
				
				/*
				 * Perform some cleanup on each field.
				 */
				foreach ($this->reports->{$year}->{$i} as &$field)
				{
					
					$field = trim(str_replace('&nbsp;', '', $field));
					
					/*
					 * If this field only contains an empty data cell, delete it.
					 */
					if ($field == '<td></td>')
					{
						$field = '';
						continue;
					}
					
					/*
					 * If this field contains a URL, replace its content with the content of
					 * the URL.
					 */
					if (strstr($field, 'a href') !== FALSE)
					{
						
						/*
						 * Factsheet URLs don't include the protocol or domain.
						 */
						$field = str_replace('href=\'factsheets', 'href=\'http://jlarc.virginia.gov/factsheets', $field);
						
						$pattern = '/http\:\/\/(\S+)/';
						if (preg_match($pattern, $field, $matches))
						{
							$field = $matches[0];
						}
						
						/*
						 * Fix a couple of problems that I can't seem to manage via regular
						 * expression at this late hour.
						 */
						if (substr($field, -1) == "'")
						{
							$field = substr($field, 0, -1);
						}
						if (substr($field, -6) == "'>Fact")
						{
							$field = substr($field, 0, -6);
						}
						
					}
					
				}

				$i++;
				
			} // end foreach $tr
			
			/*
			 * Now get the abstracts, which are stored in alternate rows.
			 */
// THERE MAY NOT BE ANY ABSTRACTS.
// There were none until 2005. So we have pages and pages without abstracts, and then they finally
// start in the middle of a list. As a result, this method of matching just isn't going to work.
			foreach ($dom->find('tr.materials') as $materials)
			{
		
				$i = 0;
				foreach ($materials->find('div') AS $abstract)
				{
					$this->reports->{$year}->{$i}->abstract = trim($abstract->plaintext);
					$i++;
				}
				
			}
			
		} // end foreach ($years as $year)
		
	} // end method gather()
	
} // end class JLARC
