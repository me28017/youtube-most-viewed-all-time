<?php
/* Copyright (c) 2016 Michael Markidis; Licensed MIT */

set_error_handler("customError");

// YouTube Video model class
class YTVid
{
	public $id = '';
	public $title = '';
	public $totalViews = 0;
	public $durationStr = '';	
	public $seconds = 0;
}

// Error handler function
function customError ($errno, $errstr)
{
	echo "<b>Error:</b> [$errno] $errstr<br>";
	echo "Ending Script";
	die();
}

// Builds an array of video IDs for the N most viewed videos.

// Parameters:
// DEV_KEY: The Google API Key
// num_vids_to_get: How many videos to get (N).
// Returns: The array of video IDs
function getArrayOfideoIDs ($DEV_KEY, $num_vids_to_get)
{
	// array of videos so far
	$videoIDs = array();

	$url = 'https://www.googleapis.com/youtube/v3/search?part=snippet&order=viewCount';
	$url .= '&type=video&maxResults=50&key=' . $DEV_KEY;

	$response = file_get_contents($url);
	
	if ($response === false)
	{
		trigger_error("Call failed to: " . $url);
	}
	$response = json_decode($response);

	// Loop until we have all the vids we need
	while (count($videoIDs) < $num_vids_to_get)
	{
		$npt = $response->nextPageToken;

		$items = $response->items;

		foreach ($items as $item)
		{
			$kind = $item->id->kind;

			if ($kind == 'youtube#video')
			{
				array_push($videoIDs, $item->id->videoId);
			}

			if (count($videoIDs) == $num_vids_to_get)
			{
				break;
			}
		}

		if (count($videoIDs) == $num_vids_to_get)
		{
			break;
		}

		// do the next call
		$url = 'https://www.googleapis.com/youtube/v3/search?part=snippet&order=viewCount&maxResults=50';
		$url .= '&type=video&pageToken=' . $npt . '&key=' . $DEV_KEY;

		$response = file_get_contents($url);
		if ($response === false)
		{
			trigger_error("Call failed to: " . $url);
		}
		$response = json_decode($response);
	}
	return $videoIDs;
}

// Get the stats for each video in the passed in array.
// Each call to Google can only have a max of 50 vid IDs

// Parameters:
// DEV_KEY: The Google API Key
// videoIDs: Array of video IDs
// Returns: An array of type YTVid
function getStatsForEachVideo ($DEV_KEY, $videoIDs)
{
	$ytVidData = array();

	$batchNum = 0;
	$keepLooping = true;

	$numOfVids = count($videoIDs);

	while ($keepLooping)
	{
		$startIdx = $batchNum * 50;
		$endIdx = 0;
	
		if ($numOfVids <= $startIdx + 50)
		{
			$endIdx = $numOfVids;
			$keepLooping = false;
		}
		else
		{
			$endIdx = $startIdx + 50;
		}

		// Generate the URL	
		$url = 'https://www.googleapis.com/youtube/v3/videos?part=contentDetails,statistics,snippet&';
		$url .= 'id=';

		// add all the IDs for this batch
		for ($i = $startIdx; $i < $endIdx; $i++)
		{
			if ($i > $startIdx)
			{
				$url .= ',';
			}
			$url .= $videoIDs[$i];
		}

		// add the key
		$url .= '&key=' . $DEV_KEY;

		$response = file_get_contents($url);
		if ($response === false)
		{
			trigger_error("Call failed to: " . $url);
		}

		$response = json_decode($response);

		$items = $response->items;

		foreach ($items as $item)
		{
			$yt = new YTVid();
		
			$yt->id = $item->id;
		
			$yt->title = $item->snippet->title;

			$yt->totalViews = $item->statistics->viewCount;
		
			// convert duration str to seconds (of the form "PT4M13S")
			// some videos are an hour or more so they are in the form "PT1H6M5S"

			$durStr = $item->contentDetails->duration;
		
			$durStr = substr($durStr, 2); // strip off the "PT"
		
			// see if we have an "H"
			$posH = stripos($durStr, "H");

			$hours = 0;

			if ($posH !== false)
			{
				$hoursStr = substr($durStr, 0, $posH);
			
				$hours = intval($hoursStr);
			
				// put the durStr past the H
				$durStr = substr($durStr, $posH + 1);
			}

			// get index of "M"
			$posM = stripos($durStr, "M");
		
			$minStr = substr($durStr, 0, $posM);
		
			$minutes = intval($minStr);
		
			// strip off everything up to and including the "M"
			$durStr = substr($durStr, $posM + 1);
		
			$secondsStr = substr($durStr, 0, strlen($durStr) - 1);

			$seconds = intval($secondsStr) + ($minutes * 60) + ($hours * 3600);
		
			$yt->seconds = $seconds;

			$yt->durationStr = $seconds;

			// add vid to the collection
			array_push($ytVidData, $yt);
		}
		$batchNum++;
	}
	return $ytVidData;
}

// Writes out all the HTML
function writeOutHTML ($ytVidData, $num_vids_to_get, $yearsStr)
{
	echo '<!DOCTYPE html>';
	echo '<html lang="en">';

	echo '<head>';
	echo '<meta charset="utf-8">';
	echo '<title>Youtube - Most Viewed Of All Time</title>';
		
	echo '<link rel="stylesheet" media="all" type="text/css" href="css/bootstrap.min.css">';
	echo '<link rel="stylesheet" media="all" type="text/css" href="css/jumbotron-narrow.css">';

	echo '</head>';

	echo '<body>';

	echo '<div class="container" style="max-width: 100%;">';
	
	echo '<div class="jumbotron">';
	echo '<h2>These are the top ' . $num_vids_to_get . ' most viewed videos on YouTube.</h2>';

	echo '<h3>All these videos combined have been played for a total of ' . $yearsStr . ' Years!</h3>';
	echo '</div>';

	echo '<div>';
	echo '<table style="width: 100%;">';
	echo '	<thead>';
	echo '		<tr>';
	echo '			<th>#</th>';
	echo '			<th>Title</th>';
	echo '			<th>Duration (Seconds)</th>';
	echo '			<th>Total Views</th>';
	echo '		</tr>';
	echo '	</thead>';

	echo '	<tbody>';

	$ct = 1;
	foreach ($ytVidData as $vid)
	{
		$vidURL = 'https://www.youtube.com/watch?v=' . $vid->id;
		$tv = number_format($vid->totalViews, 0, '', ',');

		echo '<tr>';
		echo '<td>' . $ct++ . '</td>';
		echo '<td><a target="_blank" href="' . $vidURL . '">' . $vid->title . '</a></td>';
		echo '<td>' . $vid->durationStr . '</td>';
		echo '<td>' . $tv . '</td>';
		echo '</tr>';
	}

	echo '	</tbody>';
	echo '</table>';

	echo '</div>';
	echo '</div>';
	echo '</body>';
	echo '</html>';
}

// Main entry point function
function main ()
{
	// Get the Google API key from the URL
	$DEV_KEY = $_GET['key'];
	$DEV_KEY = trim($DEV_KEY);

	if (strlen($DEV_KEY) == 0)
	{
		trigger_error("The key parameter cannot be empty");
	}

	// Get How many videos to get from the URL
	$num_vids_to_get = $_GET['num_vids_to_get'];

	$num_vids_to_get = trim($num_vids_to_get);
	
	if (!is_numeric($num_vids_to_get))
	{
		trigger_error("The num_vids_to_get parameter must be a number", E_USER_ERROR);
	}

	$num_vids_to_get = intval($num_vids_to_get);

	if ($num_vids_to_get < 0 || $num_vids_to_get > 1000)
	{
		trigger_error("The num_vids_to_get parameter must between 1 and 1000", E_USER_ERROR);
	}

	$videoIDs = getArrayOfideoIDs($DEV_KEY, $num_vids_to_get);

	$ytVidData = getStatsForEachVideo($DEV_KEY, $videoIDs);

	$totalSeconds = 0;

	// Calculate the total years
	foreach ($ytVidData as $vid)
	{
		$vidSec = $vid->seconds;
		$vidViews = $vid->totalViews;
	
		$totalSeconds += ($vidSec * $vidViews);
	}

	$secondsInYear = 3600 * 24 * 365;

	$years = $totalSeconds / $secondsInYear;

	$yearsStr = number_format($years, 2, '.', ',');

	writeOutHTML($ytVidData, $num_vids_to_get, $yearsStr);
}

// Call main
main();
?>
