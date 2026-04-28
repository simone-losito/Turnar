<?php
// app/communication_attachment.php
require_once __DIR__ . '/config.php';
require_mobile_login();

$db=db_connect();
$dipendenteId=(int)(auth_dipendente_id()??0);
$id=(int)($_GET['id']??0);
if($id<=0||$dipendenteId<=0){http_response_code(404);exit('File non trovato');}

$sql="SELECT a.original_name,a.file_path,a.mime_type,a.file_size
      FROM communication_attachments a
      INNER JOIN communication_recipients r ON r.communication_id=a.communication_id
      WHERE a.id=? AND r.dipendente_id=?
      LIMIT 1";
$stmt=$db->prepare($sql);
if(!$stmt){http_response_code(500);exit('Errore');}
$stmt->bind_param('ii',$id,$dipendenteId);
$stmt->execute();
$res=$stmt->get_result();
$row=$res?$res->fetch_assoc():null;
$stmt->close();
if(!$row){http_response_code(404);exit('File non trovato');}

$path=(string)($row['file_path']??'');
$base=realpath(__DIR__.'/../uploads/communications');
$real=realpath($path);
if(!$base||!$real||strpos($real,$base)!==0||!is_file($real)){http_response_code(404);exit('File non disponibile');}

$name=basename((string)($row['original_name']??'allegato'));
$mime=trim((string)($row['mime_type']??''));
if($mime==='')$mime='application/octet-stream';
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($real));
header('Content-Disposition: inline; filename="'.str_replace('"','',$name).'"');
header('X-Content-Type-Options: nosniff');
readfile($real);
exit;
