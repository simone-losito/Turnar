<?php
// badge operatori

function op_user_role_label($op){
    $role = trim((string)($op['user_role'] ?? ''));
    if ($role === '') return '';
    return function_exists('role_label') ? role_label($role) : ucfirst($role);
}

function op_absence_percent($op){
    $attendance = is_array($op['attendance'] ?? null) ? $op['attendance'] : [];
    return (float)($attendance['percentage_absence'] ?? 0);
}

function op_badges($op){
    $html = '';
    $hasAccount = (int)($op['user_id'] ?? 0) > 0;
    $roleLabel = op_user_role_label($op);

    if ($hasAccount) {
        $html .= '<span class="mini-pill ' . (!empty($op['user_is_active']) ? 'account-on' : 'account-off') . '">';
        $html .= !empty($op['user_is_active']) ? 'Account attivo' : 'Account disattivo';
        $html .= '</span>';

        if ($roleLabel !== '') {
            $html .= '<span class="mini-pill user-role">' . op_h($roleLabel) . '</span>';
        }

        if (!empty($op['can_login_web'])) {
            $html .= '<span class="mini-pill app-flag">Web</span>';
        }

        if (!empty($op['can_login_app'])) {
            $html .= '<span class="mini-pill app-flag">App</span>';
        }
    } else {
        $html .= '<span class="mini-pill account-off">Nessun account</span>';
    }

    if (!empty($op['preposto'])) {
        $html .= '<span class="mini-pill flag-role">Preposto</span>';
    }

    if (!empty($op['capo_cantiere'])) {
        $html .= '<span class="mini-pill flag-role">Responsabile</span>';
    }

    $html .= '<span class="mini-pill attendance">Assenze ' . op_h(number_format(op_absence_percent($op), 1, ',', '.')) . '%</span>';

    return $html;
}
