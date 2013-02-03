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
  $config->set('HTML.AllowedAttributes', array('a.href'));
  $config->set('HTML.AllowedElements', array('a', 'div', 'p', 'table', 'tr', 'td'));
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
    mb_internal_encoding('UTF-8');
    if (detect_charset(substr($st, 0, 300)) !== 'UTF-8') {
      $st = mb_convert_encoding($st, 'UTF-8', 'cp1251');
    }
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

function get_month_number($monthName) {
  switch ($monthName) {
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
  return $mon;
}

function get_date_intervals($st) {
  if (preg_match('/с\s+(\d+)\s*(.*)\s+по\s+(\d+)\s+(.+)/', $st, $matches)) {
    $mdayEnd = $matches[3];
    $monEnd = get_month_number($matches[4]);
    $mdayBegin = $matches[1];
    if (empty($matches[2])) {
      $monBegin = $monEnd;
    } else {
      $monBegin = get_month_number($matches[2]);
    }
    $now = getdate();
    $currYear = $now['year'];
    $datesInInterval = 0;
    $day = $mdayBegin;
    $dates = array();
    $endIntervalTimestamp = mktime(0, 0, 0, $monEnd, $mdayEnd, $currYear);
    while ($datesInInterval < 20) {
      $timestamp = mktime(0, 0, 0, $monBegin, $day, $currYear);
      $dates[] = date('Y-m-d', $timestamp);
      if ($timestamp >= $endIntervalTimestamp) {
        break;
      }
      $day++;
      $datesInInterval++;
    }
    return $dates;
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

function detect_charset($st) {
  if (preg_match('/charset=([^\"]+)/', $st, $matches)) {
    return strtoupper($matches[1]);
  }
  return false;
}

$st = get_data_string('/tmp/cache_moviestar', 'http://moviestar.kg/');
$xml = simplexml_load_string($st);

if ($xml) {
  try {
    $db = new PDO ('sqlite:movie.sqlite3');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE IF NOT EXISTS timeTable (id INTEGER PRIMARY KEY, theaterName VARCHAR(50), movieName VARCHAR(50), movieDate CHAR(10), movieTime CHAR(5), movieHall VARCHAR(50), moviePrice VARCHAR(10), movieLink VARCHAR(255) ) ');
    $sql = 'INSERT INTO timeTable ( theaterName,  movieName,  movieDate,  movieTime,  movieHall,  moviePrice,  movieLink)
								 VALUES (:theaterName, :movieName, :movieDate, :movieTime, :movieHall, :moviePrice, :movieLink)';
    $statement = $db->prepare($sql);

    $cinemaName = 'Мувистар';

    $cinema = $xml->div->div[1]->div->div->div;

    $price3D = parse_to_correct_price((string)$cinema->table[2]->tr[0]->td[1]);
    $price2D = parse_to_correct_price((string)$cinema->table[2]->tr[1]->td[1]);
    $dateIntervals = get_date_intervals((string)$cinema->div[0]);
    foreach ($dateIntervals as $date) {
      $movieDate = $date;
      for ($hallNumber = 1; $hallNumber < 3; $hallNumber++) {
        $moviesByDayCount = count($cinema->table[$hallNumber - 1]->tr);
        for ($j = 1; $j < $moviesByDayCount; $j++) {
          $movieInfo = $cinema->table[$hallNumber - 1]->tr[$j];
          $movieName = (string)$movieInfo->td[1];
          $movieHall = 'Зал ' . $hallNumber;
          if (strpos($movieName, '3D') !== false) {
            $moviePrice = $price3D;
          } else {
            $moviePrice = $price2D;
          }
          $movieTime = (string)$movieInfo->td[0];
          $movieLink = null;
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
