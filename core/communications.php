<?php
// core/communications.php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/app_notifications.php';
require_once __DIR__ . '/push.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/mail.php';

function comm_table_exists(mysqli $db,string $table):bool{
    $safe=$db->real_escape_string($table);
    $res=$db->query("SHOW TABLES LIKE '{$safe}'");
    if($res instanceof mysqli_result){$ok=$res->num_rows>0;$res->free();return $ok;}
    return false;
}

function comm_get_all_recipients():array{
    $db=db_connect();$rows=[];
    $res=$db->query("SELECT id,nome,cognome,email FROM dipendenti ORDER BY cognome ASC,nome ASC");
    if($res){while($r=$res->fetch_assoc()){$rows[]=['id'=>(int)$r['id'],'label'=>trim(($r['cognome']??'').' '.($r['nome']??'')),'email'=>trim((string)($r['email']??''))];}}
    return $rows;
}

function comm_get_selected_recipients(array $ids):array{
    $ids=array_values(array_unique(array_filter(array_map('intval',$ids))));
    if(!$ids)return [];
    $db=db_connect();
    $sql='SELECT id,nome,cognome,email FROM dipendenti WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY cognome ASC,nome ASC';
    $types=str_repeat('i',count($ids));
    $stmt=$db->prepare($sql);if(!$stmt)return [];
    $stmt->bind_param($types,...$ids);$stmt->execute();$res=$stmt->get_result();$rows=[];
    while($r=$res->fetch_assoc()){$rows[]=['id'=>(int)$r['id'],'label'=>trim(($r['cognome']??'').' '.($r['nome']??'')),'email'=>trim((string)($r['email']??''))];}
    $stmt->close();return $rows;
}

function comm_get_role_recipients(string $role):array{
    $role=normalize_role($role);$db=db_connect();if(!comm_table_exists($db,'users'))return [];
    $stmt=$db->prepare("SELECT DISTINCT d.id,d.nome,d.cognome,d.email FROM users u INNER JOIN dipendenti d ON d.id=u.dipendente_id WHERE u.role=? ORDER BY d.cognome ASC,d.nome ASC");
    if(!$stmt)return [];$stmt->bind_param('s',$role);$stmt->execute();$res=$stmt->get_result();$rows=[];
    while($r=$res->fetch_assoc()){$rows[]=['id'=>(int)$r['id'],'label'=>trim(($r['cognome']??'').' '.($r['nome']??'')),'email'=>trim((string)($r['email']??''))];}
    $stmt->close();return $rows;
}

function comm_get_destination_recipients(int $destinationId,string $from,string $to):array{
    $db=db_connect();if($destinationId<=0||!comm_table_exists($db,'eventi_turni'))return [];
    $from=normalize_date_iso($from)?:today_date();$to=normalize_date_iso($to)?:$from;
    $stmt=$db->prepare("SELECT DISTINCT d.id,d.nome,d.cognome,d.email FROM eventi_turni e INNER JOIN dipendenti d ON d.id=e.id_dipendente WHERE e.id_cantiere=? AND e.data BETWEEN ? AND ? ORDER BY d.cognome ASC,d.nome ASC");
    if(!$stmt)return [];$stmt->bind_param('iss',$destinationId,$from,$to);$stmt->execute();$res=$stmt->get_result();$rows=[];
    while($r=$res->fetch_assoc()){$rows[]=['id'=>(int)$r['id'],'label'=>trim(($r['cognome']??'').' '.($r['nome']??'')),'email'=>trim((string)($r['email']??''))];}
    $stmt->close();return $rows;
}

function comm_email_html(string $subject,string $body,string $sender):string{
    return '<div style="font-family:Arial,sans-serif;line-height:1.55;color:#111827">'
        . '<h2 style="margin:0 0 12px">'.htmlspecialchars($subject,ENT_QUOTES,'UTF-8').'</h2>'
        . '<div style="white-space:pre-line">'.htmlspecialchars($body,ENT_QUOTES,'UTF-8').'</div>'
        . '<hr style="border:0;border-top:1px solid #e5e7eb;margin:20px 0">'
        . '<p style="font-size:12px;color:#6b7280;margin:0">Inviato da '.htmlspecialchars($sender,ENT_QUOTES,'UTF-8').' tramite Turnar.</p>'
        . '</div>';
}

function comm_send(string $subject,string $body,array $recipients,bool $sendApp=true,bool $sendEmail=false):array{
    $db=db_connect();$subject=trim($subject);$body=trim($body);
    if($subject===''||$body==='')return ['ok'=>false,'message'=>'Oggetto e testo obbligatori.'];
    if(!$recipients)return ['ok'=>false,'message'=>'Nessun destinatario selezionato.'];
    if(!comm_table_exists($db,'communications'))return ['ok'=>false,'message'=>'Esegui prima la migration comunicazioni.'];
    $senderId=(int)(auth_id()??0);$sender=current_user_label();$app=$sendApp?1:0;$email=$sendEmail?1:0;$mode='selected';$status='sent';$sentAt=now_datetime();
    $stmt=$db->prepare("INSERT INTO communications(sender_user_id,sender_label,subject,body,channel_app,channel_email,target_mode,status,sent_at) VALUES(?,?,?,?,?,?,?,?,?)");
    if(!$stmt)return ['ok'=>false,'message'=>'Errore DB comunicazione.'];
    $stmt->bind_param('isssiisss',$senderId,$sender,$subject,$body,$app,$email,$mode,$status,$sentAt);$ok=$stmt->execute();$commId=(int)$db->insert_id;$stmt->close();
    if(!$ok||$commId<=0)return ['ok'=>false,'message'=>'Comunicazione non salvata.'];
    $appCount=0;$pushCount=0;$emailCount=0;$html=comm_email_html($subject,$body,$sender);
    foreach($recipients as $rec){
        $dip=(int)($rec['id']??0);if($dip<=0)continue;$label=(string)($rec['label']??'');$mail=trim((string)($rec['email']??''));
        $appOk=0;$pushOk=0;$mailOk=0;$link='communication_view.php?id='.$commId;
        if($sendApp){$appOk=app_notification_create($dip,$subject,$body,'comunicazione',$link)?1:0;$pushOk=send_browser_push_to_dipendente($dip,$subject,$body,$link)?1:0;$appCount+=$appOk;$pushCount+=$pushOk;}
        if($sendEmail&&$mail!==''&&function_exists('send_email')){$mailOk=send_email($mail,$subject,$html,$body)?1:0;$emailCount+=$mailOk;}
        $st=$db->prepare("INSERT IGNORE INTO communication_recipients(communication_id,dipendente_id,recipient_label,recipient_email,app_notification_created,push_sent,email_sent) VALUES(?,?,?,?,?,?,?)");
        if($st){$st->bind_param('iissiii',$commId,$dip,$label,$mail,$appOk,$pushOk,$mailOk);$st->execute();$st->close();}
    }
    audit_log('COMUNICAZIONE_INVIATA','Comunicazione inviata: '.$subject,'communication',$commId,['destinatari'=>count($recipients),'app'=>$appCount,'push'=>$pushCount,'email'=>$emailCount]);
    return ['ok'=>true,'message'=>'Comunicazione inviata.','id'=>$commId,'app'=>$appCount,'push'=>$pushCount,'email'=>$emailCount];
}
