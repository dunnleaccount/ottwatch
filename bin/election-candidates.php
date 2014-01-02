<?php

$dirname = `dirname $argv[0]`;
$dirname = preg_replace("/\n/","",$dirname);

set_include_path(get_include_path() . PATH_SEPARATOR . "$dirname/../lib");
set_include_path(get_include_path() . PATH_SEPARATOR . "$dirname/../www");
require_once('include.php');


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

foreach ($lines as $l) {
  if (!(preg_match('/<h3>/',$l) || preg_match('/^<tr/',$l))) { continue; }
  if (preg_match('/<th/',$l)) { continue; }
  if (preg_match('/No Candidate/',$l)) { continue; }

  $matches = array();
  if (preg_match('/^<h3.*Ward (\d+)/',$l,$matches)) {
    #print "$l\n";
    $ward = $matches[1];
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
  $sql[] = array('name'=>$key,'insert'=>$i,'update'=>$u,'count'=>$c);

  #pr($candidate);

}

foreach ($sql as $key) {
  $u = $key['update'];
  $i = $key['insert'];
  $c = $key['count'];
  $key = $key['name'];

  $row = getDatabase()->one($c);

  if ($row['c'] == 0) {
    print "Adding $key\n";
    getDatabase()->execute($i);
  }

  $c = getDatabase()->execute($u);
  print "Updating $key (rows changed; $c)\n";
}


?>
