<?php
if (!defined('ABSPATH')) { exit; }

class BCND_Annual_Forms {

    private static function get_or_create($member, $year) {
        global $wpdb;
        $af = BCND_Database::t('annual_forms');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $af WHERE member_id = %d AND year = %d", $member['id'], $year), ARRAY_A);
        if (!$row) {
            $now = BCND_Core::now();
            $wpdb->insert($af, [
                'member_id' => $member['id'], 'member_name' => $member['name'], 'year' => $year,
                'status' => 'concept', 'deviation_reason' => '', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $af WHERE id = %d", $wpdb->insert_id), ARRAY_A);
        }
        return $row;
    }

    public static function overview($req) {
        $m = BCND_Core::member_for_user(get_current_user_id());
        if (!$m) { return new WP_Error('bcnd_no_member', 'Geen lidprofiel', ['status' => 404]); }
        return rest_ensure_response(BCND_Core::build_year_overview($m, (int) $req->get_param('year')));
    }

    public static function get_form($req) {
        global $wpdb;
        if (BCND_Roles::is_admin_user()) {
            return new WP_Error('bcnd_invalid', 'Gebruik het beheerdersoverzicht', ['status' => 400]);
        }
        $m = BCND_Core::member_for_user(get_current_user_id());
        if (!$m) { return new WP_Error('bcnd_no_member', 'Geen lidprofiel', ['status' => 404]); }
        $year = (int) $req->get_param('year');
        $row = self::get_or_create($m, $year);
        $ov = BCND_Core::build_year_overview($m, $year);
        $tt = BCND_Database::t('training');
        $trs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tt WHERE member_id = %d AND year = %d ORDER BY date ASC", $m['id'], $year), ARRAY_A);
        $trainings = array_map(['BCND_Core', 'format_training'], $trs);
        return rest_ensure_response([
            'form' => BCND_Core::format_annual($row), 'overview' => $ov, 'trainings' => $trainings, 'member' => $m,
        ]);
    }

    public static function submit($req) {
        global $wpdb;
        $m = BCND_Core::member_for_user(get_current_user_id());
        if (!$m) { return new WP_Error('bcnd_no_member', 'Geen lidprofiel', ['status' => 404]); }
        $year = (int) $req['year'];
        $row = self::get_or_create($m, $year);
        if (in_array($row['status'], ['ingediend', 'in_beoordeling', 'goedgekeurd'], true)) {
            return new WP_Error('bcnd_invalid', 'Jaarformulier is al ingediend', ['status' => 400]);
        }
        $ov = BCND_Core::build_year_overview($m, $year);
        $reason = sanitize_textarea_field($req->get_param('deviation_reason'));
        if (!$ov['all_complete'] && trim($reason) === '') {
            return new WP_Error('bcnd_reason', 'De norm is niet behaald. Een toelichting is vereist om in te dienen.', ['status' => 400]);
        }
        $af = BCND_Database::t('annual_forms');
        $wpdb->update($af, [
            'status' => 'ingediend', 'deviation_reason' => $reason, 'submitted_at' => BCND_Core::now(),
            'submitted_by' => wp_get_current_user()->display_name, 'updated_at' => BCND_Core::now(),
        ], ['id' => $row['id']]);
        BCND_Audit_Log::add('annual_form', $row['id'], 'ingediend', ['from' => $row['status'], 'to' => 'ingediend',
            'remark' => 'Jaarformulier ingediend' . ($ov['all_complete'] ? '' : ' (norm niet behaald)')]);
        BCND_Notifications::notify_admins('annual_submitted', 'Jaarformulier ingediend',
            $m['name'] . ' heeft het jaarformulier ' . $year . ' ingediend.', ['form_id' => $row['id']]);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $af WHERE id = %d", $row['id']), ARRAY_A);
        return rest_ensure_response(BCND_Core::format_annual($row));
    }

    public static function admin_list($req) {
        global $wpdb;
        $af = BCND_Database::t('annual_forms');
        $where = '1=1'; $args = [];
        if ($req->get_param('year')) { $where .= ' AND year = %d'; $args[] = (int) $req->get_param('year'); }
        if ($req->get_param('status')) { $where .= ' AND status = %s'; $args[] = sanitize_text_field($req->get_param('status')); }
        if ($req->get_param('member_id')) { $where .= ' AND member_id = %d'; $args[] = (int) $req->get_param('member_id'); }
        $sql = "SELECT * FROM $af WHERE $where ORDER BY updated_at DESC";
        if ($args) { $sql = $wpdb->prepare($sql, $args); }
        $rows = $wpdb->get_results($sql, ARRAY_A);
        $out = [];
        foreach ($rows as $r) {
            $f = BCND_Core::format_annual($r);
            $m = BCND_Core::get_member($r['member_id']);
            if ($m) {
                $ov = BCND_Core::build_year_overview($m, (int) $r['year']);
                $f['norm_met'] = $ov['all_complete'];
                $f['achieved_points_live'] = $ov['points']['achieved'];
                $f['required_points_live'] = $ov['points']['required'];
                $f['achieved_consults_live'] = $ov['consults']['achieved'];
                $f['required_consults_live'] = $ov['consults']['required'];
            }
            $out[] = $f;
        }
        return rest_ensure_response($out);
    }

    public static function admin_detail($req) {
        global $wpdb;
        $af = BCND_Database::t('annual_forms');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $af WHERE id = %d", (int) $req['id']), ARRAY_A);
        if (!$row) { return new WP_Error('bcnd_not_found', 'Jaarformulier niet gevonden', ['status' => 404]); }
        $m = BCND_Core::get_member($row['member_id']);
        $ov = BCND_Core::build_year_overview($m, (int) $row['year']);
        $tt = BCND_Database::t('training');
        $trs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tt WHERE member_id = %d AND year = %d ORDER BY date ASC", $m['id'], (int) $row['year']), ARRAY_A);
        $trainings = [];
        foreach ($trs as $t) {
            $item = BCND_Core::format_training($t);
            $item['documents'] = BCND_Core::training_documents($item['id']);
            $trainings[] = $item;
        }
        $ct = BCND_Database::t('consultations');
        $consult = $wpdb->get_row($wpdb->prepare("SELECT * FROM $ct WHERE member_id = %d AND year = %d", $m['id'], (int) $row['year']), ARRAY_A);
        return rest_ensure_response([
            'form' => BCND_Core::format_annual($row), 'member' => $m, 'overview' => $ov,
            'trainings' => $trainings, 'consult' => $consult,
        ]);
    }

    public static function history($req) {
        global $wpdb;
        $af = BCND_Database::t('annual_forms');
        $row = $wpdb->get_row($wpdb->prepare("SELECT member_id FROM $af WHERE id = %d", (int) $req['id']), ARRAY_A);
        if (!$row) { return new WP_Error('bcnd_not_found', 'Niet gevonden', ['status' => 404]); }
        if (!BCND_Roles::is_admin_user()) {
            $m = BCND_Core::member_for_user(get_current_user_id());
            if (!$m || (int) $row['member_id'] !== (int) $m['id']) {
                return new WP_Error('bcnd_forbidden', 'Geen toegang', ['status' => 403]);
            }
        }
        return rest_ensure_response(BCND_Audit_Log::history('annual_form', (int) $req['id']));
    }

    public static function review($req) {
        global $wpdb;
        $af = BCND_Database::t('annual_forms');
        $id = (int) $req['id'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $af WHERE id = %d", $id), ARRAY_A);
        if (!$row) { return new WP_Error('bcnd_not_found', 'Jaarformulier niet gevonden', ['status' => 404]); }
        $m = BCND_Core::get_member($row['member_id']);
        $action = sanitize_text_field($req->get_param('action'));
        $remark = sanitize_textarea_field($req->get_param('remark'));
        $from = $row['status'];
        $data = ['reviewed_by' => wp_get_current_user()->display_name, 'reviewed_at' => BCND_Core::now(),
                 'updated_at' => BCND_Core::now(), 'admin_remark' => $remark];

        if ($action === 'approve') {
            $ov = BCND_Core::build_year_overview($m, (int) $row['year']);
            $norms = BCND_Core::compute_norms($m['license_since'], (int) $row['year']);
            $data['status'] = 'goedgekeurd';
            $data['applied_points_norm'] = $norms['points_norm'];
            $data['applied_consults_norm'] = $norms['consults_norm'];
            $data['achieved_points'] = $ov['points']['achieved'];
            $data['achieved_consults'] = $ov['consults']['achieved'];
            $wpdb->update($af, $data, ['id' => $id]);
            self::snapshot_items($id, $m, (int) $row['year']);
            BCND_Audit_Log::add('annual_form', $id, 'goedgekeurd', ['from' => $from, 'to' => 'goedgekeurd', 'remark' => $remark]);
            if ($m) {
                $msg = BCND_Notifications::tpl('annual_approved', ['name' => $m['name'], 'year' => $row['year']]);
                BCND_Notifications::notify($m['user_id'], 'annual_approved', 'Jaarformulier goedgekeurd', $msg, ['form_id' => $id]);
            }
        } elseif ($action === 'request_correction') {
            $data['status'] = 'aanpassing_gevraagd';
            $wpdb->update($af, $data, ['id' => $id]);
            BCND_Audit_Log::add('annual_form', $id, 'correctie_gevraagd', ['from' => $from, 'to' => 'aanpassing_gevraagd', 'remark' => $remark]);
            if ($m) {
                $msg = BCND_Notifications::tpl('annual_correction', ['name' => $m['name'], 'year' => $row['year'], 'remark' => $remark]);
                BCND_Notifications::notify($m['user_id'], 'annual_correction', 'Jaarformulier: correctie gevraagd', $msg, ['form_id' => $id]);
            }
        } elseif ($action === 'reject') {
            $data['status'] = 'afgekeurd';
            $wpdb->update($af, $data, ['id' => $id]);
            BCND_Audit_Log::add('annual_form', $id, 'afgewezen', ['from' => $from, 'to' => 'afgekeurd', 'remark' => $remark]);
            if ($m) {
                BCND_Notifications::notify($m['user_id'], 'annual_rejected', 'Jaarformulier afgewezen',
                    'Uw jaarformulier ' . $row['year'] . ' is afgewezen. ' . $remark, ['form_id' => $id]);
            }
        } else {
            return new WP_Error('bcnd_invalid', 'Onbekende actie', ['status' => 400]);
        }
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $af WHERE id = %d", $id), ARRAY_A);
        return rest_ensure_response(BCND_Core::format_annual($row));
    }

    private static function snapshot_items($form_id, $member, $year) {
        global $wpdb;
        $items = BCND_Database::t('annual_form_items');
        $wpdb->delete($items, ['annual_form_id' => $form_id]);
        $tt = BCND_Database::t('training');
        $trs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tt WHERE member_id = %d AND year = %d ORDER BY date ASC", $member['id'], $year), ARRAY_A);
        foreach ($trs as $t) {
            $wpdb->insert($items, [
                'annual_form_id' => $form_id, 'training_id' => (int) $t['id'], 'date' => $t['date'],
                'hours' => $t['hours'], 'organization' => $t['organization'], 'subject' => $t['subject'],
                'content_explanation' => $t['content_explanation'], 'speaker' => $t['speaker'],
                'activity_type' => $t['activity_type'], 'points' => $t['points'],
            ]);
        }
    }

    public static function generate_pdf($req) {
        global $wpdb;
        $af = BCND_Database::t('annual_forms');
        $id = (int) $req['id'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $af WHERE id = %d", $id), ARRAY_A);
        if (!$row) { return new WP_Error('bcnd_not_found', 'Jaarformulier niet gevonden', ['status' => 404]); }
        $m = BCND_Core::get_member($row['member_id']);
        $ov = BCND_Core::build_year_overview($m, (int) $row['year']);
        $norms = BCND_Core::compute_norms($m['license_since'], (int) $row['year']);
        $tt = BCND_Database::t('training');
        $trs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tt WHERE member_id = %d AND year = %d ORDER BY date ASC", $m['id'], (int) $row['year']), ARRAY_A);
        $trainings = array_map(['BCND_Core', 'format_training'], $trs);

        $pdf = BCND_PDF::generate($m, BCND_Core::format_annual($row), $trainings, $ov, $norms);
        BCND_Database::ensure_private_dir();
        $filename = 'jaarformulier-' . $id . '-' . wp_generate_uuid4() . '.pdf';
        $path = trailingslashit(BCND_Database::private_dir()) . $filename;
        file_put_contents($path, $pdf);
        @chmod($path, 0640);
        $wpdb->update($af, ['pdf_filename' => $filename, 'updated_at' => BCND_Core::now()], ['id' => $id]);
        BCND_Audit_Log::add('annual_form', $id, 'pdf_gegenereerd', ['remark' => 'Definitieve PDF gegenereerd']);
        return rest_ensure_response([
            'id' => $filename,
            'original_filename' => 'BCND_Jaarformulier_' . $row['year'] . '_' . $m['member_number'] . '.pdf',
            'size' => strlen($pdf),
        ]);
    }

    public static function get_pdf($req) {
        global $wpdb;
        $af = BCND_Database::t('annual_forms');
        $id = (int) $req['id'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $af WHERE id = %d", $id), ARRAY_A);
        if (!$row) { return new WP_Error('bcnd_not_found', 'Niet gevonden', ['status' => 404]); }
        if (!BCND_Roles::is_admin_user()) {
            $m = BCND_Core::member_for_user(get_current_user_id());
            if (!$m || (int) $row['member_id'] !== (int) $m['id']) {
                return new WP_Error('bcnd_forbidden', 'Geen toegang', ['status' => 403]);
            }
        }
        if (empty($row['pdf_filename'])) { return new WP_Error('bcnd_not_found', 'Nog geen PDF gegenereerd', ['status' => 404]); }
        $path = trailingslashit(BCND_Database::private_dir()) . $row['pdf_filename'];
        if (!file_exists($path)) { return new WP_Error('bcnd_not_found', 'Bestand niet gevonden', ['status' => 404]); }
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="BCND_Jaarformulier_' . $row['year'] . '.pdf"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
