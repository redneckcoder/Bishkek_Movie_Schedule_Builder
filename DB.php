<?php
/*
Copyright (c) 2013 redneckcoder@namba.kg
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

class DB {
  protected $db;
  protected $statement;
  protected $clearStatement;

  public function __construct() {
    $this->db = new PDO ('sqlite:movie.sqlite3');
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->db->exec('CREATE TABLE IF NOT EXISTS timeTable (id INTEGER PRIMARY KEY, theaterName VARCHAR(50) NOT NULL, movieName VARCHAR(50), movieDate CHAR(10) NOT NULL, movieTime CHAR(5) NOT NULL, movieHall VARCHAR(50) NOT NULL, moviePrice VARCHAR(10), movieLink VARCHAR(255), UNIQUE (theaterName, movieHall, movieDate, movieTime) ON CONFLICT REPLACE ) ');
    $sql = 'INSERT INTO timeTable ( theaterName,  movieName,  movieDate,  movieTime,  movieHall,  moviePrice,  movieLink)
								 VALUES (:theaterName, :movieName, :movieDate, :movieTime, :movieHall, :moviePrice, :movieLink)';
    $this->statement = $this->db->prepare($sql);
    $sql = 'DELETE FROM timeTable WHERE theaterName=:theaterName AND movieDate=:movieDate';
    $this->clearStatement = $this->db->prepare($sql);
  }

  public function insert(Array $data) {
    $this->statement->bindParam(':theaterName', $data['cinemaName']);
    $this->statement->bindParam(':movieName', $data['movieName']);
    $this->statement->bindParam(':movieDate', $data['movieDate']);
    $this->statement->bindParam(':movieTime', $data['movieTime']);
    $this->statement->bindParam(':movieHall', $data['movieHall']);
    $this->statement->bindParam(':moviePrice', $data['moviePrice']);
    $this->statement->bindParam(':movieLink', $data['movieLink']);
    $this->statement->execute();
  }

  public function clearByCinemaAndDate($cinemaName, $movieDate) {
    $this->clearStatement->bindParam(':theaterName', $cinemaName);
    $this->clearStatement->bindParam(':movieDate', $movieDate);
    $this->clearStatement->execute();
  }

  public function close() {
    $this->db = null;
  }

  public function dump() {
    $result = $this->db->query('SELECT * FROM timeTable');
    foreach ($result as $row) {
      var_dump($row);
    }
  }


}