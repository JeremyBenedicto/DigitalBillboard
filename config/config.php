<?php
  $db_host = 'localhost';
  $db_username = 'root';
  $db_password = '';
  $db_name = 'digitalbillboard';

  $conn = mysqli_connect($db_host, $db_username, $db_password, $db_name);
  if(!$conn){
    // Don't output anything here - let the calling script handle the error
  }
?>