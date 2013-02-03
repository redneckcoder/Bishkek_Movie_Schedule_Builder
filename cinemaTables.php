<?php
/*
Copyright (c) 2013 redneckcoder@namba.kg
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
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
  $config->set('HTML.AllowedElements', array('a', 'div', 'p', 'ul', 'li', 'h3', 'h1'));
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
  $filename1 = $filename . '.stripped';
  file_put_contents($filename1, $stripped);
  return $stripped;
}

function parse_to_correct_date($st) {
  if (preg_match('/(\d+)\s(.+)/', $st, $matches)) {
    $mday = $matches[1];
    $mstr = $matches[2];
    switch ($mstr) {
      case 'января':
        $mon = 1;
        break;
      case 'февраля':
        $mon = 2;
        break;
      case 'марта':
        $mon = 3;
        break;
      case 'апреля':
        $mon = 4;
        break;
      case 'мая' :
        $mon = 5;
        break;
      case 'июня':
        $mon = 6;
        break;
      case 'июля':
        $mon = 7;
        break;
      case 'августа':
        $mon = 8;
        break;
      case 'сентября':
        $mon = 9;
        break;
      case 'октября':
        $mon = 10;
        break;
      case 'ноября':
        $mon = 11;
        break;
      case 'декабря':
        $mon = 12;
        break;
      default:
        $mon = 0;
    }
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

$st = get_data_string('/tmp/cache', 'http://m.cinematica.kg');
$xml = simplexml_load_string($st);
if ($xml) {
  try {
    $db = new PDO ('sqlite:movie.sqlite3');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE IF NOT EXISTS timeTable (id INTEGER PRIMARY KEY, theaterName VARCHAR(50), movieName VARCHAR(50), movieDate CHAR(10), movieTime CHAR(5), movieHall VARCHAR(50), moviePrice VARCHAR(10), movieLink VARCHAR(255) ) ');
    $sql = 'INSERT INTO timeTable ( theaterName,  movieName,  movieDate,  movieTime,  movieHall,  moviePrice,  movieLink)
								 VALUES (:theaterName, :movieName, :movieDate, :movieTime, :movieHall, :moviePrice, :movieLink)';
    $statement = $db->prepare($sql);
    $theatersCount = count($xml->div);
    for ($th = 1; $th < $theatersCount; $th++) {
      $cinema = $xml->div[$th];
      $cinemaName = (string)$cinema->div[0]->h1;
      var_dump($cinemaName);
      $timeTableLength = count($cinema->div[1]->div->div);
      for ($i = 0; $i < $timeTableLength; $i++) {
        $dayInTable = $cinema->div[1]->div->div[$i];
        $dayDate = (string)$dayInTable->h3;

        $movieDate = parse_to_correct_date($dayDate);
        $moviesByDayCount = count($dayInTable->ul->li);
        for ($j = 0; $j < $moviesByDayCount; $j++) {
          $movieInfo = $dayInTable->ul->li[$j];
          $movieName = (string)$movieInfo->h3->a;
          $movieHall = (string)$movieInfo->p[0]->a;
          $moviePrice = parse_to_correct_price((string)$movieInfo->p[1]->a);
          $movieTime = (string)$movieInfo->p[2]->a;
          $movieLink = 'http://cinematica.kg' . (string)$movieInfo->a[0]->attributes()->href;
          $statement->bindParam(':theaterName', $cinemaName);
          $statement->bindParam(':movieName', $movieName);
          $statement->bindParam(':movieDate', $movieDate);
          $statement->bindParam(':movieTime', $movieTime);
          $statement->bindParam(':movieHall', $movieHall);
          $statement->bindParam(':moviePrice', $moviePrice);
          $statement->bindParam(':movieLink', $movieLink);
          $statement->execute();
        }
      }
    }
    /*
    $result = $db->query('SELECT * FROM timeTable');
    foreach ($result as $row) {
      var_dump($row);
    }
    */
    $db = null;
  } catch (Exception $e) {
    echo($e);
  }
}
