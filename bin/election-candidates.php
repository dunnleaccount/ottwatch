<?php

$dirname = `dirname $argv[0]`;
$dirname = preg_replace("/\n/","",$dirname);

set_include_path(get_include_path() . PATH_SEPARATOR . "$dirname/../lib");
set_include_path(get_include_path() . PATH_SEPARATOR . "$dirname/../www");
require_once('include.php');

##########################################################################################
# TRUSTEE
##########################################################################################

$url = "http://ottawa.ca/en/city-hall/your-city-government/elections/school-board-trustee";

$html = file_get_contents($url);
$html = ConsultationController::getCityContent($html,"<h3><table><tr><td><th>");

$html = preg_replace("/\n/",'',$html);
$html = preg_replace("/<tr/","\n<tr",$html);
$html = preg_replace("/<\/tr>/","<\/tr>\n",$html);
$html = preg_replace("/<h2/","\n<h2",$html);
$html = preg_replace("/<\/h2>/","<\/h2>\n",$html);
$html = preg_replace("/<h3/","\n<h3",$html);
$html = preg_replace("/<\/h3>/","<\/h3>\n",$html);
$lines = explode("\n",$html);

foreach ($lines as $l) {

	if (preg_match('/<h2/',$l)) {
		$board = strip_tags($l);
		continue;
	}
	if (preg_match('/<h3>/',$l)) {
		$matches = array();
		if (!preg_match('/<h3>Zone (\d+)/',$l,$matches)) {
			print "ERROR: $l\n";
		}
		$zone = $matches[1];
		continue;
	}

	if (!preg_match('/^<tr/',$l)) { continue; }
	if (preg_match('/Email Address/',$l)) { continue; }
	if (preg_match('/No candidate/i',$l)) { continue; }
  $l = preg_replace('/<td>/',"\t",$l);
  $l = preg_replace('/not provided/',"",$l);
  $l = preg_replace('/not provided/',"",$l);
  $l = strip_tags($l);
  $l = trim($l);
  $row = explode("\t",$l);

  $names = explode(" ",$row[0]);

  $candidate['ward'] = $zone;
  $candidate['wardname'] = $board;
  $candidate['first'] = trim($names[0]);
  $candidate['last'] = trim($names[count($names)-1]);
  $candidate['phone'] = trim(@$row[1]);
  $candidate['fax'] = trim(@$row[2]);
  $candidate['email'] = trim(@$row[3]);

  $c = " select count(1) c from candidate where ward = ${candidate['ward']} and first = '{$candidate['first']}' and last = '{$candidate['last']}'; ";
  $i = "insert into candidate (ward,year,first,last) values ({$candidate['ward']},2014,'{$candidate['first']}','{$candidate['last']}'); ";
  $u = "update candidate set nominated = (case when nominated is null then now() else nominated end) , phone = '{$candidate['phone']}', email = '{$candidate['email']}'  where ward = {$candidate['ward']} and first = '{$candidate['first']}' and last = '{$candidate['last']}'; ";
  $key = "{$candidate['ward']} {$candidate['first']} {$candidate['last']}";
  $sql[] = array('ward'=>$candidate['ward'],'insert'=>$i,'update'=>$u,'count'=>$c,'details'=>$candidate);

}

pr($sql);

return;

##########################################################################################
# MAYOR
##########################################################################################

$url = "http://ottawa.ca/en/city-hall/your-city-government/elections/mayor";
$html = file_get_contents($url);
$html = ConsultationController::getCityContent($html,"<h3><table><tr><td><th>");

$html = preg_replace("/\n/",'',$html);
$html = preg_replace("/<tr/","\n<tr",$html);
$html = preg_replace("/<\/tr>/","<\/tr>\n",$html);
$html = preg_replace("/<h3/","\n<h3",$html);
$html = preg_replace("/<\/h3>/","<\/h3>\n",$html);
$lines = explode("\n",$html);

$ward = 0;
$wardname = 'Mayor';

foreach ($lines as $l) {
	if (!preg_match('/^<tr/',$l)) { continue; }
	if (preg_match('/Email Address/',$l)) { continue; }
  $l = preg_replace('/<td>/',"\t",$l);
  $l = preg_replace('/not provided/',"",$l);
  $l = preg_replace('/not provided/',"",$l);
  $l = strip_tags($l);
  $l = trim($l);
  $row = explode("\t",$l);


  $names = explode(" ",$row[0]);

  $candidate['ward'] = $ward;
  $candidate['wardname'] = $wardname;
  $candidate['first'] = $names[0];
  $candidate['last'] = $names[count($names)-1];
  $candidate['phone'] = @$row[1];
  $candidate['fax'] = @$row[2];
  $candidate['email'] = @$row[3];

  $c = " select count(1) c from candidate where ward = $ward and first = '{$candidate['first']}' and last = '{$candidate['last']}'; ";
  $i = "insert into candidate (ward,year,first,last) values ($ward,2014,'{$candidate['first']}','{$candidate['last']}'); ";
  $u = "update candidate set nominated = (case when nominated is null then now() else nominated end) , phone = '{$candidate['phone']}', email = '{$candidate['email']}'  where ward = $ward and first = '{$candidate['first']}' and last = '{$candidate['last']}'; ";
  $key = "$ward {$candidate['first']} {$candidate['last']}";
  $sql[] = array('ward'=>$ward,'name'=>$key,'insert'=>$i,'update'=>$u,'count'=>$c,'details'=>$candidate);
}

##########################################################################################
# CANDIDATES
##########################################################################################

$url = "http://ottawa.ca/en/city-hall/your-city-government/elections/councillor";
$html = file_get_contents($url);
$html = ConsultationController::getCityContent($html,"<h3><table><tr><td><th>");

$html = preg_replace("/\n/",'',$html);
$html = preg_replace("/<tr/","\n<tr",$html);
$html = preg_replace("/<\/tr>/","<\/tr>\n",$html);
$html = preg_replace("/<h3/","\n<h3",$html);
$html = preg_replace("/<\/h3>/","<\/h3>\n",$html);
$lines = explode("\n",$html);

$ward = -1;
$wardname = '';

foreach ($lines as $l) {
  if (!(preg_match('/<h3>/',$l) || preg_match('/^<tr/',$l))) { continue; }
  if (preg_match('/<th/',$l)) { continue; }
  if (preg_match('/No Candidate/',$l)) { continue; }

  $matches = array();
  if (preg_match('/^<h3/',$l)) {
		$l = preg_replace('/-/',' - ',$l);
		$l = preg_replace('/–/',' - ',$l);
		$l = preg_replace('/  /',' ',$l);
		$l = preg_replace('/  /',' ',$l);
		$l = preg_replace('/  /',' ',$l);
		$l = preg_replace('/  /',' ',$l);
	  if (preg_match('/Ward (\d+) - ([^<]+)/',$l,$matches)) {
	    #print "$l\n";
	    $ward = $matches[1];
	    $wardname = $matches[2];
			#print "$l\n";
	  } else {
			print "FAIL: $l\n";
		}
    continue;
	}

  $l = preg_replace('/<td>/',"\t",$l);
  $l = preg_replace('/not provided/',"",$l);
  $l = preg_replace('/not provided/',"",$l);
  $l = strip_tags($l);
  $l = trim($l);
  $row = explode("\t",$l);


  $names = explode(" ",$row[0]);

  $candidate['ward'] = $ward;
  $candidate['wardname'] = $wardname;
  $candidate['first'] = $names[0];
  $candidate['last'] = $names[count($names)-1];
  $candidate['phone'] = @$row[1];
  $candidate['fax'] = @$row[2];
  $candidate['email'] = @$row[3];
  foreach ($candidate as $k => $v) {
    $v = preg_replace('/ /','',$v);
    $candidate[$k] = $v;
    #print "$k = '$v'\n";
  }

  $c = " select count(1) c from candidate where ward = $ward and first = '{$candidate['first']}' and last = '{$candidate['last']}'; ";
  $i = "insert into candidate (ward,year,first,last) values ($ward,2014,'{$candidate['first']}','{$candidate['last']}'); ";
  $u = "update candidate set nominated = (case when nominated is null then now() else nominated end) , phone = '{$candidate['phone']}', email = '{$candidate['email']}'  where ward = $ward and first = '{$candidate['first']}' and last = '{$candidate['last']}'; ";
  $key = "$ward {$candidate['first']} {$candidate['last']}";
  $sql[] = array('ward'=>$ward,'name'=>$key,'insert'=>$i,'update'=>$u,'count'=>$c,'details'=>$candidate);

  #pr($candidate);

}

foreach ($sql as $key) {

	$c = $key['details'];

	$tweet = "NEW candidate: {$c['first']} {$c['last']} ({$c['wardname']}) http://ottwatch.ca/election/ward/{$key['ward']} #ottvote";

	#pr($key['details']);

  $u = $key['update'];
  $i = $key['insert'];
  $c = $key['count'];
  $key = $key['name'];

  $row = getDatabase()->one($c);

  if ($row['c'] == 0) {
    print "$key added: NEW candidate\n";
		print "$tweet\n";
    getDatabase()->execute($i);
		getDatabase()->execute($u);
		continue;
  }

  $c = getDatabase()->execute($u);
	if ($c > 0) {
		print "$key updated\n";
	}
}

$no = array('sympatico.ca','gmail.com','hotmail.com','yahoo.com','gmail.com');
$rows = getDatabase()->all(" select id,email from candidate where (url is null or url = '') and (email is not null and email != '') ");
foreach ($rows as $r) {
	$url = $r['email'];
	$url = preg_replace('/.*@/','',$url);
	$yes = 1;
	foreach ($no as $n) {
		if (preg_match("/$n/",$url)) {
			$yes = 0;
		}
	}
	if ($yes) {
		$sql = " update candidate set url = '$url' where id = {$r['id']}; ";
		print "$sql\n";
	}
}

?>
