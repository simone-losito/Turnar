<?php
// helper base operatori

function op_h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function op_name($op){
    $nome = trim((string)($op['nome'] ?? ''));
    $cognome = trim((string)($op['cognome'] ?? ''));
    $full = trim($nome . ' ' . $cognome);
    return $full !== '' ? $full : 'Senza nome';
}

function op_initials($op){
    $n = (string)($op['nome'] ?? '');
    $c = (string)($op['cognome'] ?? '');
    $out = '';

    if ($n !== '') $out .= strtoupper(substr($n,0,1));
    if ($c !== '') $out .= strtoupper(substr($c,0,1));

    return $out !== '' ? $out : 'PS';
}
