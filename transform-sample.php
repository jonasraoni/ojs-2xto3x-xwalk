<?php
$opts = getopt('', ['in:', 'out:']);

$db = new mysqli('MYSQL_HOST', 'USER', 'PASSWORD', 'OJS DATABASE');
if ($db->connect_error) {
	die('Connection failed: ' . $db->connect_error);
}

$rs = $db->query('
	select j.path as name, (
		select min(volume) from issues i
		where i.journal_id = j.journal_id
	) as volume
	from journals j
');
$context = [];
while($row = $rs->fetch_assoc()) {
	$context[$row['name']] = [
		'volume' => $row['volume'],
		'seq' => 0
	];
}

$transform = new DOMDocument();
$transform->load('transform.xsl');
$xsl = new XSLTProcessor();
$xsl->importStyleSheet($transform);

if (is_dir($opts['in']) && is_dir($opts['out'])) {
	$dh = opendir($opts['in']);
	while ($file = readdir($dh)) {
		if ($file[0] === '.')
			continue;

		if (!preg_match('/^([a-z]+)_[a-z]*(\d+)([a-z]*)_([a-z0-9-]+)_\d+\.xml$/i', $file, $match)) {
			die('Failed:' . $file);
		}
		[, $journal, $year, $extra, $section] = $match;
		$year += 2000;
		$volume = $context[$journal]['volume'] - (2019 - $year);

		$xml = new DOMDocument();
		$xml->load(rtrim($opts['in'], '/') . '/' . $file);

		$xsl->setParameter('', 'seq', $context[$journal]['seq']++);
		$xsl->setParameter('', 'volume', $volume);
		$xsl->setParameter('', 'year', $year);
		$xsl->setParameter('', 'access_status', '0');
		$xsl->setParameter('', 'section_ref', $section);
		$xsl->setParameter('', 'number', $extra ? '2' : '1');
		if(!($data = $xsl->transformToXML($xml))) {
			echo 'Failed ' . $file . "\n";
			exit;
		}
		file_put_contents(rtrim($opts['out'], '/') . '/' . $file, $data) . "\n";
		echo 'Converted ' . $file . "\n";
	}
	closedir($dh);
}
