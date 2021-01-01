<?php
$episode_versions = [
	2 => [1, 2, 3, 4, 5, 6, 8, 9, 10, 12, 13, 14, 16, 17, 23, 24, 26, 27, 31, 40, 41, 46],
];
$target_dir = realpath(__DIR__ . "/../Temp/") . DIRECTORY_SEPARATOR;
for ($i = 1; $i <= 48; $i++) {
	$version = null;
	foreach ($episode_versions as $v_ => $episodes)
		if (in_array($i, $episodes, true))
			$version = "_v{$v_}";
	if (null === $version)
		continue;
	$sub_credits = $i <= 6 ? "C-M&PDSG" : "Silver Castle";
	$i_ = substr("0$i", -2);
	$v = __DIR__ . "/../Videos/Yuusha Exkaiser - Episode {$i_}.mkv";
	$a = __DIR__ . "/../Videos/Yuusha Exkaiser - Episode {$i_}.flac";
	$s1 = __DIR__ . "/../English Subtitles/Yuusha Exkaiser - Episode {$i_}.ass";
	$s2 = __DIR__ . "/../Temp/en/Yuusha Exkaiser - Episode {$i_}.srt";
	if (preg_match('/EpisodeTitle,[^,]*,[^,]*,[^,]*,[^,]*,[^,]*,(.*)/m', explode("\r\n\r\n", file_get_contents($s1))[3], $regs)) {
		$title = trim(preg_replace('/(\\s|\\\\N)+/', ' ', preg_replace('/\{.*?\}/', '', $regs[1])));
	} else {
		die("A");
	}

	$cmd = [
		"ffmpeg",
		"-i", escapeshellarg($v),
	];
	array_push($cmd, "-i", escapeshellarg($s1));
	array_push($cmd, "-i", escapeshellarg($s2));
	if (file_exists($a))
		array_push($cmd, "-i", escapeshellarg($a));
	array_push($cmd, "-c", "copy");
	array_push($cmd, "-map", "0:v");
	array_push($cmd, "-map", "0:a");
	array_push($cmd, "-metadata:s:a:0", "language=jpn");
	array_push($cmd, "-metadata:s:a:0", "title=\"DVD Audio\"");
	if (file_exists($a)) {
		array_push($cmd, "-map", "3:a");
		array_push($cmd, "-metadata:s:a:1", "language=jpn");
		array_push($cmd, "-metadata:s:a:1", "title=\"VHS Audio\"");
		array_push($cmd, "-disposition:a:0", "none");
		array_push($cmd, "-disposition:a:1", "default");
	}
	array_push($cmd, "-map", "1");
	array_push($cmd, "-map", "2");
	array_push($cmd, "-metadata:s:s:0", "language=eng");
	array_push($cmd, "-metadata:s:s:0", "title=\"ASS ($sub_credits)\"");
	array_push($cmd, "-metadata:s:s:1", "language=eng");
	array_push($cmd, "-metadata:s:s:1", "title=\"SRT ($sub_credits)\"");
	array_push($cmd, "-metadata", "title=\"Yuusha Exkaiser - Episode {$i_} - {$title}\"");
	array_push($cmd, "\"{$target_dir}_tmp.mkv\"");
	$cmd = implode(" ", $cmd);
	echo $cmd . "\n";
	system($cmd);
	
	$hash=hash_file('crc32b', "{$target_dir}_tmp.mkv");
	$hash=strtoupper(substr("0000000$hash",-8,8));
	rename("{$target_dir}_tmp.mkv", $target_dir . "Yuusha Exkaiser - Episode {$i_}{$version}[$hash].mkv");
}