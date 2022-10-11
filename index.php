<?php

$xml = simplexml_load_file($argv[1]);

$publishers = [];
$games = [];
$gameToPublisherMapping = [];

function download_publisher($publisherId) {
	mkdir("publishers/" . $publisherId);
	$page = 1;
	do {
		echo "[INFO] $publisherId/$page\n";
		$url ="https://api.geekdo.com/api/geekitem/linkeditems?ajax=1&linkdata_index=boardgame&nosession=1&objectid=$publisherId&objecttype=company&pageid=$page&showcount=50&sort=rank&subtype=boardgamepublisher";
		$c = file_get_contents($url);
		$data = json_decode($c, true);
		file_put_contents("publishers/$publisherId/$page.json", $c);
		$page+=1;
	} while (count($data['items']) > 0);
}

function add_to_list($publisherId) {
	global $games;
	global $gameToPublisherMapping;
	foreach(glob("publishers/$publisherId/*.json") as $f) {
		$d = json_decode(file_get_contents($f), true);
		$games = array_merge($games, $d['items']);
		foreach($d['items'] as $game) {
			$gameId = (string)$game['objectid'];
			if (isset($gameToPublisherMapping[$gameId])) {
				$gameToPublisherMapping[$gameId][] = $publisherId;
			} else {
				$gameToPublisherMapping[$gameId] = [$publisherId];
			}
		}
	}
}

foreach($xml->item as $item) {
	if ($item['subtype'] == 'boardgamepublisher') {
		$publisherId = (string)$item['objectid'];
		$publishers[$publisherId] = [
			'name' => (string) $item['objectname'],
			'link' => (string) "https://boardgamegeek.com/geeklist/269452/asmodee-publisher-list?itemid=" . $item['id']
		];
		// download_publisher($publisherId);
		add_to_list($publisherId);
	}
}

$x = [];

$games = array_filter($games, function($value) {
   global $x;
   if (isset($x[$value['objectid']])) {
   	return false;
   } else {
   	$x[$value['objectid']] = true;
   	return true;
   }
}, ARRAY_FILTER_USE_BOTH);

usort($games, function($a, $b){
 if ($b['rank'] == 0) {
 	return -1;
 }
 if ($a['rank'] == 0) {
 	return 1;
 }
 return $a['rank'] - $b['rank'];
});

foreach($games as $game) {
	if ((int)$game['rank'] == 0) {
		continue;
	}
	$link = "https://boardgamegeek.com/boardgame/" . $game['objectid'];
	echo $game['rank'] . " | [". $game['name'] . "]($link) |";
	$gameId = (string)$game['objectid'];
	$p = [];
	foreach ($gameToPublisherMapping[$gameId] as $publisherId) {
		$n = $publishers[$publisherId]['name'];
		$link = $publishers[$publisherId]['link'];
		$p[] = "[$n][p-$publisherId]";
	}
	echo implode(", ", array_unique($p));
	echo "\n";
}

echo "\n\n";

foreach($publishers as $publisherId => $p) {
	$link = $p['link'];
	$name = $p['name'];
	echo "[p-$publisherId]: $link '$name'\n";
}