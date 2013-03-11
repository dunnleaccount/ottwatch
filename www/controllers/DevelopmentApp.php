<?php

class DevelopmentAppController {

  static public function viewDevApp($devid) {
    $a = getDatabase()->one(" select * from devapp where devid = :devid ",array("devid"=>$devid));
    if (!$a['id']) {
      top();
      print "$devid not found in the database.\n";
      bottom();
      return;
    }
    top();

    $html = file_get_contents(self::getLinkToApp($a['appid']));
    $lines = explode("\n",$html);
    $add = 0;
    $buf = array();
    foreach ($lines as $l) {
      if (preg_match('/class="box"/',$l)) {
        $add = 1;
      }
      if (preg_match("/CONTENT ENDS/",$l)) {
        $add = 0;
      }
      if ($add) {
        $buf[] = $l;
      }
    }
    array_pop($buf); // pop last DIV, which is not part of the "class=box" div

    print "<h1>{$a['devid']}</h1>";
    print implode("\n",$buf);
    ?>
    <?php
    bottom();
  }

  static public function listAll() {
    top();

    $apps = getDatabase()->all(" select * from devapp order by updated desc ");

    ?>

    <h1>Development Applications</h1>

    <div class="row-fluid">

    <div class="span8">


    <table class="table table-bordered table-hover table-condensed" style="width: 100%;">
    <tr>
    <th>Application #</th>
    <th>Application</th>
    <th>Status</th>
    <th>Address(es)</th>
    <th>Updated</th>
    <th>Started</th>
    </tr>
    <?php
    foreach ($apps as $a) {
      $url = self::getLinkToApp($a['appid']);
      ?>
      <tr>
      <td><a target="_blank" href="<?php print $url; ?>"><?php print $a['devid']; ?></a></td>
      <td><?php print $a['apptype']; ?></td>
      <td><?php print $a['status']; ?></td>
      <td>
      <?php
      $addr = json_decode($a['address']);
      foreach ($addr as $t) {
        print "<a target=\"_blank\" href=\"http://maps.google.com/?q={$t->lat},{$t->lon}\">{$t->addr}</a><br/>\n";
      }
      ?>
      </td>
      <td><?php print strftime("%Y-%m-%d",strtotime($a['statusdate'])); ?></td>
      <td><?php print strftime("%Y-%m-%d",strtotime($a['receiveddate'])); ?></td>
      </tr>
      <?php
    }
    ?>
    </table>
    </div>

    <div class="span4">
    <div id="map_canvas" style="width:100%; height:600px;"></div>
    <script>
      $(document).ready(function() {
        var mapOptions = { center: new google.maps.LatLng(45.420833,-75.69), zoom: 8, mapTypeId: google.maps.MapTypeId.ROADMAP };
        var map = new google.maps.Map(document.getElementById("map_canvas"), mapOptions);

        <?php
        foreach ($apps as $a) {
          $addr = json_decode($a['address']);
          $addr = $addr[0];
          if (count($addr) == 0) {
            continue;
          }
          $lat = $addr->lat;
          $lon = $addr->lon;
          ?>
          {
	        var myLatlng<?php print $a['id']; ?> = new google.maps.LatLng(<?php print $lat; ?>,<?php print $lon; ?>);
	        var contentString<?php print $a['id']; ?> = 
            '<div>' + 
            '<b><a target="_blank" href="<?php print $url; ?>"><?php print $a['devid']; ?></a>: ' +
            '<?php print $a['apptype']; ?></b><br/>' +
            '<?php print $a['status']; ?><br/>' +
            'Updated: <?php print strftime("%Y-%m-%d",strtotime($a['statusdate'])); ?>' +
            '</div>';
	        var infowindow<?php print $a['id']; ?> = new google.maps.InfoWindow({ content: contentString<?php print $a['id']; ?> });
	        var marker<?php print $a['id']; ?> = new google.maps.Marker({ position: myLatlng<?php print $a['id']; ?>, map: map, title: '<?php print $a['devid']; ?>' }); 
	        google.maps.event.addListener(marker<?php print $a['id']; ?>, 'click', function() {
	          infowindow<?php print $a['id']; ?>.open(map,marker<?php print $a['id']; ?>);
	        });
          }
          <?php
        }
        ?>



      });
    </script>
    </div>

    </div><!-- row -->
    <?php
    bottom();
  }

  static public function getLinkToApp($appid) {
    return "http://app01.ottawa.ca/postingplans/appDetails.jsf?lang=en&appId=$appid";
  }

  static public function scanDevApps() {

    # get dev-apps sorted by status update.
    # results are sorted with oldtest date first, so then jump to last page, and start scanning backwards
    # until no dates on page are "new"
    $html = file_get_contents('http://app01.ottawa.ca/postingplans/searchResults.jsf?lang=en&newReq=true&action=sort&sortField=objectCurrentStatusDate&keyword=.');
    #file_put_contents("t.html",$html);
    #$html = file_get_contents("t.html");

    # parse out all of the pages of results
    $lines = explode("\n",$html);
    $add = 0;
    $span = "";
    foreach ($lines as $l) {
      if (preg_match("/span/",$l)) {
        if ($add) {
          $span = $l;
          break;
        }
      }
      if (preg_match("/searchpaging/",$l)) {
        $add = 1;
      }
    }

    $data = explode("<a",$span);
    $pages = array();
    foreach ($data as $d) {
      if (preg_match('/page=(\d+)"/',$d,$match)) {
        $pages[$match[1]] = 1;
      }
    }
    $pages = array_keys($pages);
    $pages = array_reverse($pages);

    # obtain all search results until a page has no relatively new DevApps
    foreach ($pages as $p) {
      $changed = 0;
      $url="http://app01.ottawa.ca/postingplans/searchResults.jsf?lang=en&action=sort&sortField=objectCurrentStatusDate&keyword=.&page=$p";
      $html = file_get_contents($url);
      #file_put_contents("p.html",$html);
      #$html = file_get_contents("p.html");
      $lines = explode("\n",$html); 
      foreach ($lines as $l) {
        # <a href="appDetails.jsf;jsessionid=D49D6B525184BD8711CED3AFDE61A2D2?lang=en&appId=__866MYU" class="app_applicationlink">D01-01-12-0006           </a>
        $matches = array();
        if (preg_match('/appDetails.jsf.*appId=([^"]+)".*>(D[^ <]+)/',$l,$matches)) {
          $appid = $matches[1];
          $devid = $matches[2];
        }
        if (preg_match('/<td class="subRowGray15">(.*)</',$l,$matches)) {
          $statusdate = $matches[1];
          $statusdate = strftime("%Y-%m-%d",strtotime($statusdate));
          $app = getDatabase()->one(" select id,date(statusdate) statusdate from devapp where appid = :appid ",array("appid"=>$appid));
          $action = '';
          if ($app['id']) {
            if ($app['statusdate'] != $statusdate) {
              $changed = 1;
              self::injestApplication($appid,'update');
            }
          } else {
            $changed = 1;
            self::injestApplication($appid,'insert');
          }
        }
      }
      if (! $changed) {
        # nothing changed on this search results page;
        # no need to keep going on other serach pages
        break;
      }
    }

  }

  static function injestApplication ($appid,$action) {
    print "injestApplication($appid,$action)\n";
    $url = "http://app01.ottawa.ca/postingplans/appDetails.jsf?lang=en&appId=$appid";
    $html = file_get_contents($url);
    #file_put_contents("a.html",$html);
    #$html = file_get_contents("a.html");
    $html = preg_replace("/&nbsp;/"," ",$html);
    $html = preg_replace("/\r/"," ",$html);
    $lines = explode("\n",$html);

		$labels = array();
		$labels['Application #'] = '';
		$labels['Date Received'] = '';
		#$labels['Address'] = '';
		$labels['Ward'] = '';
		$labels['Application'] = '';
		$labels['Review Status'] = '';
		$labels['Status Date'] = '';
		#$labels['Description'] = '';

    $addresses = array();

    $label = '';
    $value = '';
    for ($x = 0; $x < count($lines); $x++) {
      $matches = array();
      if (preg_match('/apps104.*LAT=([-\d\.]+).*LON=([-\d\.]+).*>([^<]+)</',$lines[$x],$matches)) {
        # <li><a href="http://apps104.ottawa.ca/emap?emapver=lite&LAT=45.278462&LON=-75.570191&featname=5640+Bank+Street&amp;lang=en" target="_emap">5640 Bank Street</a></li>
        print_r($matches);
        $addr = array();
        $addr['lat'] = $matches[1];
        $addr['lon'] = $matches[2];
        $addr['addr'] = $matches[3];
        $addresses[] = $addr;
        #$addresses[0] = $matches[1];
      }
      if (preg_match('/div.*class="label"/',$lines[$x])) {
        $x++;
        $label = self::suckToNextDiv($lines,$x);
      }
      if (preg_match('/div.*class="appDetailValue"/',$lines[$x])) {
        $x++;
        $value = self::suckToNextDiv($lines,$x);
        if (array_key_exists($label,$labels)) {
          $labels[$label] = $value;
        }
      }
    }

    $labels['status_date'] = strftime('%Y-%m-%d',strtotime($labels['Status Date']));
    unset($labels['Status Date']);
    $labels['date_received'] = strftime('%Y-%m-%d',strtotime($labels['Date Received']));
    unset($labels['Date Received']);

    getDatabase()->execute(" delete from devapp where appid = :appid ",array("appid"=>$appid));
    getDatabase()->execute(" 
      insert into devapp 
      (address,appid,devid,ward,apptype,status,statusdate,receiveddate,created,updated)
      values
      (:address,:appid,:devid,:ward,:apptype,:status,:statusdate,:receiveddate,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)",array(
        'devid'=> $labels['Application #'],
        'address'=> json_encode($addresses),
        'appid'=> $appid,
        'ward' => $labels['Ward'],
        'apptype' => $labels['Application'],
        'status' => $labels['Review Status'],
        'statusdate' => $labels['status_date'],
        'receiveddate' =>$labels['date_received'],
    ));

    $url = "http://app01.ottawa.ca/postingplans/appDetails.jsf?lang=en&appId=$appid";

    if ($action == 'insert') {
      $tweet = "New {$labels['Application']}: ".implode("/",$addresses)." {$labels['Application #']}";
    } else {
      $tweet = "Updated {$labels['Application']}: ".implode("/",$addresses)." {$labels['Application #']}";
    }

    $newtweet = tweet_txt_and_url($tweet,$url);
		print "$newtweet\n";

		# allow dups because a devapp will be updated multiple times
		tweet($newtweet,1);

#  id mediumint not null auto_increment,
#  appid varchar(10),
#  ward varchar(100),
#  apptype varchar(100),
#  status varchar(100),
#  statusdate datetime,
#  receiveddate datetime,
#  created datetime,
#  updated datetime,
#  primary key (id)

  }

  static function suckToNextDiv ($lines,$x) {
        $snippet = '';
        while (!preg_match('/div>/',$lines[$x])) {
          $snippet .= $lines[$x];
          $x++;
        }
        $snippet = preg_replace("/:/","",$snippet);
        $snippet = preg_replace("/ +/"," ",$snippet);
        $snippet = preg_replace("/^\s+/","",$snippet);
        $snippet = preg_replace("/\s$/","",$snippet);
        return $snippet;
  }


  
}

?>
