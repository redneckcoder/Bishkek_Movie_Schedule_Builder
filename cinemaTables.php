<?php
/*
Copyright (c) 2013 redneckcoder@namba.kg
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
require_once('DB.php');
$URL = 'http://www.cinematica.kg';

function curl_get_string($url) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_VERBOSE, 1);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_REFERER, "");
  curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
  curl_setopt($ch, CURLOPT_MAXREDIRS, 1);
  $result = curl_exec($ch);
  curl_close($ch);
  return $result;
}

function strp($string) {
  $purifierLibPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'HTMLPurifier' . DIRECTORY_SEPARATOR;
  require_once $purifierLibPath . 'HTMLPurifier.path.php';
  require_once $purifierLibPath . 'HTMLPurifier.includes.php';
  $config = HTMLPurifier_Config::createDefault();
  $config->set('Core.HiddenElements', array('script' => true, 'style' => true, 'embed' => true, 'applet' => true, 'object' => true,));
  $config->set('Cache.SerializerPath', '/tmp');
  //$config->set('HTML.AllowedAttributes', array('a.href'));
  $config->set('Attr.EnableID', true);
  $config->set('HTML.AllowedElements', array('a', 'div', 'p', 'h3', 'h1', 'table', 'tr', 'td'));
  //$config->set('AutoFormat.RemoveEmpty', true);
  $purifier = new HTMLPurifier($config);
  $clean_html = $purifier->purify($string);
  return $clean_html;
}

function get_data_string($filename, $url) {
  $currentTimestamp = time();
  if (file_exists($filename) && ($currentTimestamp - filemtime($filename)) < 4500) {
    $st = file_get_contents($filename);
  } else {
    $st = curl_get_string($url);
    $st = get_stripped_xml_data($st, $filename);
    file_put_contents($filename, $st);
  }
  return $st;
}

function get_stripped_xml_data($st, $filename) {
  $b = strpos($st, '<body>');
  $e = strpos($st, '</body>');
  $st = substr($st, $b, $e - $b + 7);
  $st = '<html>' . $st . '</html>';
  $stripped = '<html>' . strp($st) . '</html>';
  return $stripped;
}

function parse_to_correct_date($st) {
  if (preg_match('/(\d+)\.(\d+)/', $st, $matches)) {
    $mday = $matches[1];
    $mon = $matches[2];
    $now = getdate();
    $currYear = $now['year'];
    $d = date('Y-m-d', mktime(0, 0, 0, $mon, $mday, $currYear));
    return $d;
  }
  return false;
}

function parse_to_correct_price($st) {
  if (preg_match('/([\d\-]+)\s(.+)/', $st, $matches)) {
    $price = $matches[1];
    return $price;
  }
  return false;
}

$st = get_data_string('/tmp/cache_cinematica', $URL);
$xml = simplexml_load_string($st);
if ($xml) {
  try {
    $db = new DB();
    $topNav = $xml->xpath('//div[@id="top-nav"]/a');
    foreach ($topNav as $cinemaObject) {
      $cinemaName = (string)$cinemaObject;
      $cinemaDivId = str_replace('#', '', (string)$cinemaObject['href']);
      $cinemaDiv = array_pop($xml->xpath("//div[@id='$cinemaDivId']"));
      if ($cinemaDiv) {
        $i = 0;
        foreach ($cinemaDiv->div[0]->a as $date) {
          $movieDate = parse_to_correct_date((string)$date);
          $dateItem = $cinemaDiv->div[1]->div[$i]->div->table;
          if (count($dateItem->tr) > 0) {
            $db->clearByCinemaAndDate($cinemaName, $movieDate);
            foreach ($dateItem->tr as $row) {
              if (count($row->td)) {
                $dataArray = array();
                $dataArray['cinemaName'] = $cinemaName;
                $dataArray['movieDate'] = $movieDate;
                $dataArray['movieTime'] = ((string)$row->td[0]);
                $dataArray['movieName'] = ((string)$row->td[1]->a[0]);
                $dataArray['movieHall'] = ((string)$row->td[3]);
                $dataArray['moviePrice'] = parse_to_correct_price((string)$row->td[4]);
                $dataArray['movieLink'] = $URL . (string)$row->td[1]->a[0]->attributes()->href;
                $db->insert($dataArray);
              }
            }
          }
          $i++;
        }
      }
    }
    $db->close();
  } catch (Exception $e) {
    echo($e);
  }
}
