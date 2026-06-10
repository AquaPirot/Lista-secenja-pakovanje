<?php
require_once __DIR__.'/config.php';

function db(){
  static $pdo = null;
  if($pdo) return $pdo;
  $pdo = new PDO(
    'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE       => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
  );
  return $pdo;
}

function cors(){
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: GET,POST,OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');
  if($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
}

function json_out($data, $code = 200){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function auth(){
  $k = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['key'] ?? '');
  if($k !== API_KEY) json_out(['ok'=>false,'err'=>'Unauthorized'], 401);
}

function body(){
  return json_decode(file_get_contents('php://input'), true) ?? [];
}
