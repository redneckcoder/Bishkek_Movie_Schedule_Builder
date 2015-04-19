<?php
/*
Copyright (c) 2013 redneckcoder@namba.kg
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
require_once('DB.php');
$URL = 'http://manascinema.com';
$cinemaNames = array('Манас', 'Жал Синема');

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

function get_data_string_ajax($filename, $url) {
  $currentTimestamp = time();
  if (file_exists($filename) && ($currentTimestamp - filemtime($filename)) < 4500) {
    $st = file_get_contents($filename);
  } else {
    $st = curl_get_string($url);
    if ($st[0] != '{') {
      $st[0] = ' ';
      $st[1] = ' ';
      $st[2] = ' ';
    }
    file_put_contents($filename, $st, FILE_BINARY);
  }
  return $st;
}

function get_stripped_xml_data($st, $filename) {
  $b = strpos($st, '<head>');
  $e = strpos($st, '</head>');
  $st = substr($st, $b, $e - $b + 7);
  return $st;
}

function parse_to_correct_price($st) {
  return str_replace('/', '-', $st);
}

function parse_to_resolve_hall_name($st) {
  $hallNames = array('big' => 'Большой зал', 'blue' => 'Синий зал', 'green' => 'Зелёный зал', 'red' => 'Красный зал', 'retro' => 'Ретро зал', 'aitysh' => 'Айтыш зал', 'zamandash' => 'Замандаш зал', 'hall9' => 'Зал 1', 'hall10' => 'Зал 2', 'hall11' => 'Зал 3');
  if (array_key_exists($st, $hallNames)) {
    return $hallNames[$st];
  }
  return $st;
}

function get_date_intervals($st) {
  $dates = array();
  if (preg_match('/var startDate = "([\d\.]+)";/', $st, $matches)) {
    $startDateTimestamp = strtotime($matches[1]);
    $endDateTimestamp = 0;
    if (preg_match('/var endDate = "([\d\.]+)";/', $st, $matches)) {
      $endDateTimestamp = strtotime($matches[1]);
    }
    $startDate = getdate($startDateTimestamp);
    $mdayBegin = $startDate['mday'];
    $monBegin = $startDate['mon'];
    $currYear = $startDate['year'];
    $datesInInterval = 0;
    $day = $mdayBegin;
    while ($datesInInterval < 20) {
      $timestamp = mktime(0, 0, 0, $monBegin, $day, $currYear);
      $dates[] = $timestamp;
      if ($timestamp >= $endDateTimestamp) {
        break;
      }
      $day++;
      $datesInInterval++;
    }
    return $dates;
  }
  return false;
}

$dates = null;
$st = get_data_string('/tmp/cache_manas', $URL);
$dates = get_date_intervals($st);

if ($dates) {
  try {
    $db = new DB();
    foreach ($cinemaNames as $i => $cinemaName) {
    $cinemaId = $i + 1;
    foreach ($dates as $timestampInTable) {
      $movieDate = date('Y-m-d', $timestampInTable);
      $dateParam = date('d.m.Y', $timestampInTable);
      $st = get_data_string_ajax('/tmp/cache_manas_' . $cinemaId . '_' . $timestampInTable, $URL . '/service/repertuar/index?mode=afisha_select_date&selDate=' . $dateParam . '&cinema_id=' . $cinemaId);
      $timetableData = json_decode($st);
      foreach ($timetableData->hall as $hallName => $hallTimeTable) {
        foreach ($hallTimeTable as $movieInfo) {
          $dataArray = array();
          $dataArray['cinemaName'] = $cinemaName;
          $dataArray['movieDate'] = $movieDate;
          $dataArray['movieName'] = $movieInfo->name;
          $dataArray['movieHall'] = parse_to_resolve_hall_name($hallName);
          $dataArray['moviePrice'] = parse_to_correct_price($movieInfo->price);
          $dataArray['movieTime'] = $movieInfo->time;
          $dataArray['movieLink'] = $URL . '/movies/' . $movieInfo->id;
          $db->insert($dataArray);
        }
      }
    }
    }
    $db->close();
  } catch (Exception $e) {
    echo($e);
  }
}
