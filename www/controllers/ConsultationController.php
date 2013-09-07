<?php

class ConsultationController {

  // main entry point for crawling public consultations

  public static function showConsultationContent($id) {
    $row = getDatabase()->one(" select * from consultation where id = :id ",array('id'=>$id));
    $html = file_get_contents(OttWatchConfig::FILE_DIR."/consultationmd5/".$row['md5']);
    ?>
    <html>
    <head>
    <base href="http://ottawa.ca" target="_blank">
    <style>
    body {
      font-family: Verdana;
      font-size: 10pt;
    }
    a:hover {
      text-decoration: underline;
    }
    a {
      text-decoration: none;
    }
    </style>
    </head>
    <body>
    <?php
    print $html;
    ?>
    </body>
    </html>
    <?php

  }

  public static function showConsultation($id) {
    $row = getDatabase()->one("
	    select 
	      c.*,
	      datediff( CURRENT_TIMESTAMP, c.updated) delta
	    from consultation c
	      left join (select consultationid,max(updated) docupdated from consultationdoc group by consultationid) d on d.consultationid = c.id
	    where id = :id 
      ",array('id'=>$id));
    top($row['title']);
    ?>

		<h1><?php print $row['title']; ?></h1>
		Last updated <?php print $row['delta']; ?> day(s) ago.<br/>

    <div class="row-fluid">

    <div class="span6">
		<h2>Documents at a glance</h2>
    <table class="table table-bordered table-hover table-condensed" style="width: 100%;">
	  <tr>
	  </tr>
    <?php
    $docs = getDatabase()->all(" select *,datediff(CURRENT_TIMESTAMP,updated) delta from consultationdoc where consultationid = :id order by updated desc ",array('id'=>$row['id']));
    foreach ($docs as $doc) {
      ?>
	    <tr>
	    <td><?php print $doc['delta']; ?> day(s) ago</td>
	    <td><a target="_blank" href="<?php print $doc['url']; ?>"><?php print $doc['title']; ?></a></td>
	    </tr>
      <?php
    }
    ?>
    </table>
    </div><!-- col -->

    <?php
    $frameSrc = "{$row['id']}/content";
    ?>
    <div class="span6">
    <h2>Overview</h2>
    <i>
    The overview provided below will have formatting and readability problems.
    You're better off <a target="_new" href="<?php print $row['url']; ?>">viewing the actual page on ottawa.ca</a> instead.</i>
    <iframe src="<?php print $frameSrc ?>" style="margin-top: 10px; width: 100%; height: 600px; border: 2px solid #000000;"></iframe>
    </div><!-- col -->

    </div><!-- row -->


    <?php
    bottom();
  }

  public static function showMain() {
    top();
    ?>
    <table class="table table-bordered table-hover table-condensed" style="width: 100%;">
    <tr>
    <th>Consultation/Document Title</th>
    <th>Updated (days ago)</th>
    <!-- <th>Created</th> -->
    <th>Category</th>
    </tr>
    <?php
    $rows = getDatabase()->all(" 
    select 
      c.*,
      datediff( CURRENT_TIMESTAMP, c.updated) delta
    from consultation c
      left join (select consultationid,max(updated) docupdated from consultationdoc group by consultationid) d on d.consultationid = c.id
    order by 
      case when d.docupdated is null then c.updated else greatest(c.updated,d.docupdated) end desc,
			title
    ");
    foreach ($rows as $row) {
      ?>
	    <tr>
	    <!-- <th><a target="_blank" href="<?php print $row['url']; ?>"><?php print $row['title']; ?></a></th> -->
	    <th><a target="_blank" href="<?php print $row['id']; ?>"><?php print $row['title']; ?></a></th>
	    <td><?php print $row['delta']; ?></td>
	    <td><?php print $row['category']; ?></td>
	    </tr>
      <?php
      $docs = getDatabase()->all(" select *,datediff(CURRENT_TIMESTAMP,updated) delta from consultationdoc where consultationid = :id order by updated desc ",array('id'=>$row['id']));
      foreach ($docs as $doc) {
        ?>
		    <tr>
		    <td style="padding-left: 20px;"><a target="_blank" href="<?php print $doc['url']; ?>"><?php print $doc['title']; ?></a></td>
		    <td><?php print $doc['delta']; ?></td>
		    <!-- <td><?php print $doc['created']; ?></td> -->
		    <td></td>
		    </tr>
        <?php
      }
    }
    ?>
    </table>
    <?php
    bottom();
  }

  public static function crawlConsultations() {
    # start at the stop level consultation listing.
    $html = file_get_contents("http://ottawa.ca/en/city-hall/public-consultations");
    $html = strip_tags($html,"<a>");
    $matches = array();
    preg_match_all("/<a[^h]+href=\"([^\"]+)\"[^>t]+title=\"([^\"]+)\"[^<]+<\/a>/",$html,$matches);
    #print_r($matches);
    for ($x = 0; $x < count($matches[0]); $x++) {
      if (! preg_match('/Learn/',$matches[2][$x])) { continue; }
      $matches[2][$x] = preg_replace('/Learn more about /','',$matches[2][$x]);
      $url = $matches[1][$x];
      $category = $matches[2][$x];
      self::crawlCategory($category,"http://ottawa.ca{$url}");
    }
  }

  // crawl a category for its consultations

  public static function crawlCategory ($category, $url) {
    #print "CATEGORY: $category\n";
    $html = file_get_contents($url);

		$html = self::getCityContent($html);

    $xml = simplexml_load_string($html);
    $div = $xml->xpath('//div[@id="cityott-content"]');
    $div = simplexml_load_string($div[0]->asXML());

    $links = $div->xpath('//a');
    foreach ($links as $a) {
      $url = $a->attributes();
      if (substr($url,0,1) == '/') {
        $url = "http://ottawa.ca{$url}";
      }

      $title = $a[0];
      self::crawlConsultation($category,$title,$url);
    }

  }

  // crawl a specific consultation 

  public static function crawlConsultation ($category, $title, $url) {
    #print "  CONSULT: $title\n";

    $html = file_get_contents($url);
		$html = self::getCityContent($html);

    $xml = simplexml_load_string($html);
    $div = $xml->xpath('//div[@id="cityott-content"]');
    $contentMD5 = md5($div[0]->asXML());
    file_put_contents(OttWatchConfig::FILE_DIR."/consultationmd5/".$contentMD5,$div[0]->asXML());

    $row = getDatabase()->one(" select * from consultation where url = :url ",array('url'=>$url));
    if ($row['id']) {
      if ($row['md5'] != $contentMD5) {
        print "consultation.id = {$row['id']} md5 changed: {$row['md5']} $contentMD5\nurl: $url\n\n";
        getDatabase()->execute(" update consultation set md5 = :md5, updated = CURRENT_TIMESTAMP where id = :id ",array('id'=>$row['id'],'md5'=>$contentMD5));
      }
    } else {
      db_insert("consultation",array( 'category'=>$category, 'title'=>$title, 'url'=>$url, 'md5'=>$contentMD5)); 
    }

    # reset from database, may have updated/inserted
    $row = getDatabase()->one(" select * from consultation where url = :url ",array('url'=>$url));

    # read individual documents.
    $div = simplexml_load_string($div[0]->asXML());
    $links = $div->xpath('//a');
    foreach ($links as $a) {
      $docLink = $a->attributes();
      if (substr($docLink,0,1) == '/') {
        $docLink = "http://ottawa.ca{$docLink}";
      }
      $docTitle = $a[0];

			# skip things that go outside public consultations
			if (!preg_match('/^http/',$docLink)) { continue; }
			if (preg_match('/cgi-bin\/docs.pl/',$docLink)) { continue; }
			if (preg_match('/mailto/',$docLink)) { continue; }
			if ($docLink == '') { continue; }

      // self::crawlConsultationLink($row,$docTitle,$docLink);
    }
  }

  // crawl all links within the consultation page itself; might be links to other ottawa.ca/drupal nodes
  // or to PDFs

  public static function crawlConsultationLink ($parent, $title, $url) {
    #print "    LINK: $title\n";

    $data = file_get_contents($url);
    $md5 = md5($data);
    if (preg_match('/<html/',$data)) {
      $data = self::getCityContent($data);
      $md5 = md5($data);
    }

    $row = getDatabase()->one(" select * from consultationdoc where url = :url ",array('url'=>$url));
    if ($row['id']) {
      if ($row['md5'] != $md5) {
        print "consultation.id = {$parent['id']} doc.id = {$row['id']} md5 changed: {$row['md5']} $md5\nurl: $url\n\n";
        getDatabase()->execute(" update consultationdoc set md5 = :md5, updated = CURRENT_TIMESTAMP where id = :id ",array('id'=>$row['id'],'md5'=>$md5));
      }
    } else {
      db_insert("consultationdoc",array('consultationid'=>$parent['id'],'title'=>$title, 'url'=>$url, 'md5'=>$md5)); 
    }

    file_put_contents(OttWatchConfig::FILE_DIR."/consultationmd5/".$md5,$data);
  }

  // given an HTML page served up by ottawa.ca drupal, return just the HTML that is the content region,
  // and discard menues and navigation, etc

  public static function getCityContent ($html) {

		if (!preg_match('/cityott-content/',$html)) {
			# not a drupal node
			return $html;
		}

		$o = $html;
    $html = preg_replace("/\n/","KEVINO_NEWLINE",$html);

    # remove things that break XML parsing
    $html = preg_replace("/<head.*<body/","<body",$html);
    $html = preg_replace("/&lang=en/","",$html); # not all HTML is escaped property, avoids <a href="...&lang=en" crap
    $html = preg_replace("/<script[^<]+<\/script>/"," ",$html);
    $html = preg_replace("/KEVINO_NEWLINE/","\n",$html);
    $html = preg_replace("/ & /"," and ",$html);
    $html = preg_replace("/<p[^>]+>/","",$html);
    $html = preg_replace("/<\/p>/","__BR__",$html);
    $html = strip_tags($html,"<div><br><a><h1><h2><h3><h4><h5>");
    $html = preg_replace("/__BR__/","<br/><br/>",$html);

    # the view-dom-id CLASS changes randomly, so remove it
    # example: view-dom-id-b477d62d0bdb286acc260d50c820060d
    $html = preg_replace("/view-dom-id-[a-z0-9]+/","",$html);

    $xml = simplexml_load_string($html);
    $div = $xml->xpath('//div[@id="cityott-content"]');
		if (count($div) == 0) {
			# return original HTML
			return $html;
		}
    return $div[0]->asXML();
  }
  
}

?>
