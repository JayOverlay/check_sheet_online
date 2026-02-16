<?php
// scripts/daily_report.php

// 1. Load Configuration
require_once __DIR__ . '/../config/database.php';

// 2. Load PHPMailer
// Adjust path if you used composer, but here we assume manual download to libs/PHPMailer
require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 3. SMTP Configuration (Load from .env or edit here)
$smtp_host = getenv('SMTP_HOST') ?: 'lpn.hanabk.th.com';
$smtp_user = getenv('SMTP_USER') ?: 'anupongs@lpn.hanabk.th.com';
$smtp_pass = getenv('SMTP_PASS') ?: '312655';
$smtp_port = getenv('SMTP_PORT') ?: 587;
$smtp_from_email = getenv('SMTP_FROM_EMAIL') ?: $smtp_user;
$smtp_from_name = getenv('SMTP_FROM_NAME') ?: 'Hana Check Sheet System';

echo "Starting Daily Report Job at " . date('Y-m-d H:i:s') . "\n";

// 4. Fetch Users who have responsible families
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE responsible_family IS NOT NULL AND responsible_family != '' AND status = 'Active' AND email IS NOT NULL");
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

if (empty($users)) {
    echo "No users found with responsible families.\n";
    exit;
}

// 5. Iterate Users and Generate Reports
foreach ($users as $user) {
    $families_raw = $user['responsible_family'];
    // Split by comma and trim
    $families = array_map('trim', explode(',', $families_raw));

    // Filter empty values
    $families = array_filter($families);

    if (empty($families)) {
        continue;
    }

    echo "Processing report for user: {$user['full_name']} (Families: " . implode(', ', $families) . ")\n";

    // Prepare placeholders for SQL IN clause
    $placeholders = str_repeat('?,', count($families) - 1) . '?';

    // --- PART A: Machine Check Statistics ---
    // 1. Get All Active Machines for these families
    $machineSql = "SELECT id, machine_code, machine_name, family FROM machines WHERE status = 'Active' AND family IN ($placeholders) ORDER BY family, machine_code";
    $mStmt = $pdo->prepare($machineSql);
    $mStmt->execute($families);
    $all_machines = $mStmt->fetchAll();

    // 2. Get Machines Checked Today
    // We only care if it was checked at least once today
    $checkedSql = "
        SELECT DISTINCT m.id
        FROM check_sheets cs
        JOIN machines m ON cs.target_id = CONCAT('m_', m.id)
        WHERE DATE(cs.created_at) = CURDATE()
        AND m.family IN ($placeholders)
    ";
    $cStmt = $pdo->prepare($checkedSql);
    $cStmt->execute($families);
    $checked_ids = $cStmt->fetchAll(PDO::FETCH_COLUMN);

    // 3. Calculate Lists
    $pending_machines = [];
    $checked_count = count($checked_ids);
    $total_machines = count($all_machines);

    foreach ($all_machines as $m) {
        if (!in_array($m['id'], $checked_ids)) {
            $pending_machines[] = $m;
        }
    }
    $pending_count = count($pending_machines);

    // --- PART B: Downtime Reports Today ---
    // Join downtime -> machines (ref_type = 'machine')
    $downtimeSql = "
        SELECT 
            dt.*,
            m.machine_code,
            m.machine_name,
            m.family
        FROM downtime dt
        JOIN machines m ON dt.ref_id = m.id
        WHERE DATE(dt.reported_at) = CURDATE()
        AND dt.ref_type = 'machine'
        AND m.family IN ($placeholders)
        ORDER BY dt.reported_at DESC
    ";

    $downStmt = $pdo->prepare($downtimeSql);
    $downStmt->execute($families);
    $downtime_data = $downStmt->fetchAll();

    // If no data, maybe skip sending email? Or send "No activity" email?
    // User logic: If everything is checked and no downtime, maybe good to send "All Green"?
    // But if no machines at all, skip.
    if ($total_machines === 0) {
        echo "  -> No machines found in families. Skipping.\n";
        continue;
    }

    // --- Generate HTML Body ---
    $body = generateEmailBody($user, $families, $checked_count, $pending_count, $pending_machines, $downtime_data);

    // Save to file for debugging
    $debugFile = __DIR__ . '/../logs/debug_email_' . $user['username'] . '.html';
    file_put_contents($debugFile, $body);
    echo "  -> Debug HTML saved to: $debugFile\n";

    // --- Send Email ---
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_user;
        $mail->Password = $smtp_pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtp_port;
        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom($smtp_from_email, $smtp_from_name);
        $mail->addAddress($user['email'], $user['full_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Daily Check Sheet Report (' . date('d/m/Y') . ') - Family: ' . implode(', ', $families);
        $mail->Body = $body;
        $mail->AltBody = 'Please view this email in an HTML-compatible client.';

        $mail->send();
        echo "  -> Email sent successfully to {$user['email']}\n";
    } catch (Exception $e) {
        echo "  -> Message could not be sent. Mailer Error: {$mail->ErrorInfo}\n";
    }
}

// Helper Function for HTML Body
function generateEmailBody($user, $families, $checked_count, $pending_count, $pending_machines, $downtime)
{
    $date = date('d M Y');
    $dateThFull = date('d/m/Y');
    $family_str = implode(', ', $families);
    $total = $checked_count + $pending_count;
    $percent = $total > 0 ? round(($checked_count / $total) * 100) : 0;
    $downtime_count = count($downtime);

    // Link to website
    $baseUrl = getenv('BASE_URL') ?: 'http://localhost/check_sheet_online/';
    $dashboardUrl = $baseUrl . 'pages/machines.php';

    // Group pending machines by family
    $pending_by_family = [];
    foreach ($pending_machines as $m) {
        $pending_by_family[$m['family']][] = $m;
    }

    // Build pending rows
    $pendingRows = '';
    if (empty($pending_machines)) {
        $pendingRows = "
            <tr>
                <td colspan='4' style='padding: 30px; text-align: center; color: #059669; font-size: 16px;'>
                    <span style='font-size: 28px;'>&#10003;</span><br>
                    <strong>All machines have been checked!</strong><br>
                    <span style='font-size: 13px; color: #6b7280;'>Great work! No pending inspections.</span>
                </td>
            </tr>";
    } else {
        $no = 1;
        foreach ($pending_by_family as $fam => $machines) {
            $famCount = count($machines);
            $pendingRows .= "
                <tr>
                    <td colspan='4' style='background: #f0f4ff; padding: 8px 15px; font-weight: 700; color: #1e40af; font-size: 13px; border-left: 3px solid #3b82f6;'>
                        Family: $fam ($famCount machines)
                    </td>
                </tr>";
            foreach ($machines as $m) {
                $pendingRows .= "
                    <tr>
                        <td style='padding: 10px 15px; border-bottom: 1px solid #f3f4f6; text-align: center; color: #9ca3af; font-size: 12px;'>$no</td>
                        <td style='padding: 10px 15px; border-bottom: 1px solid #f3f4f6;'>
                            <span style='background: #fee2e2; color: #dc2626; padding: 3px 10px; border-radius: 4px; font-size: 12px; font-weight: 600;'>{$m['machine_code']}</span>
                        </td>
                        <td style='padding: 10px 15px; border-bottom: 1px solid #f3f4f6; font-size: 13px;'>{$m['machine_name']}</td>
                        <td style='padding: 10px 15px; border-bottom: 1px solid #f3f4f6; text-align: center;'>
                            <span style='background: #fef3c7; color: #b45309; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;'>PENDING</span>
                        </td>
                    </tr>";
                $no++;
            }
        }
    }

    // Build downtime rows
    $downtimeRows = '';
    if (empty($downtime)) {
        $downtimeRows = "
            <tr>
                <td colspan='5' style='padding: 20px; text-align: center; color: #6b7280; font-size: 13px;'>
                    No downtime or repair issues reported today.
                </td>
            </tr>";
    } else {
        foreach ($downtime as $row) {
            $time = date('H:i', strtotime($row['reported_at']));
            $statusColor = '#6b7280';
            $statusBg = '#f3f4f6';
            $s = $row['status'];
            if ($s === 'Reported') {
                $statusColor = '#dc2626';
                $statusBg = '#fee2e2';
            } elseif ($s === 'In Progress') {
                $statusColor = '#d97706';
                $statusBg = '#fef3c7';
            } elseif ($s === 'Technician Finished') {
                $statusColor = '#2563eb';
                $statusBg = '#dbeafe';
            } elseif ($s === 'Ready') {
                $statusColor = '#059669';
                $statusBg = '#d1fae5';
            }

            $downtimeRows .= "
                <tr>
                    <td style='padding: 10px 15px; border-bottom: 1px solid #f3f4f6; font-size: 13px; text-align: center;'>$time</td>
                    <td style='padding: 10px 15px; border-bottom: 1px solid #f3f4f6;'>
                        <span style='background: #e5e7eb; color: #374151; padding: 3px 10px; border-radius: 4px; font-size: 12px; font-weight: 600;'>{$row['machine_code']}</span>
                    </td>
                    <td style='padding: 10px 15px; border-bottom: 1px solid #f3f4f6; font-size: 13px;'>{$row['machine_name']}</td>
                    <td style='padding: 10px 15px; border-bottom: 1px solid #f3f4f6; font-size: 12px; color: #6b7280;'>" . mb_strimwidth($row['problem_description'], 0, 50, '...') . "</td>
                    <td style='padding: 10px 15px; border-bottom: 1px solid #f3f4f6; text-align: center;'>
                        <span style='background: $statusBg; color: $statusColor; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;'>$s</span>
                    </td>
                </tr>";
        }
    }

    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
    </head>
    <body style='margin: 0; padding: 0; background-color: #f1f5f9; font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif;'>

        <!-- Wrapper -->
        <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f1f5f9; padding: 30px 0;'>
            <tr>
                <td align='center'>
                    <table width='650' cellpadding='0' cellspacing='0' style='background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);'>
                        
                        <!-- Header -->
                        <tr>
                            <td style='background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #1e40af 100%); padding: 35px 30px; text-align: center;'>
                                <h1 style='margin: 0; color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: 0.5px;'>DAILY CHECK SHEET REPORT</h1>
                                <p style='margin: 8px 0 0; color: rgba(255,255,255,0.7); font-size: 14px;'>$date</p>
                            </td>
                        </tr>

                        <!-- Recipient Info -->
                        <tr>
                            <td style='padding: 25px 30px 15px;'>
                                <table width='100%' cellpadding='0' cellspacing='0' style='background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;'>
                                    <tr>
                                        <td style='padding: 18px 20px;'>
                                            <table width='100%'>
                                                <tr>
                                                    <td width='50%' style='font-size: 13px; color: #64748b;'>
                                                        <strong style='color: #334155;'>Recipient</strong><br>
                                                        {$user['full_name']}
                                                    </td>
                                                    <td width='50%' style='font-size: 13px; color: #64748b;'>
                                                        <strong style='color: #334155;'>Responsible Families</strong><br>
                                                        $family_str
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style='font-size: 13px; color: #64748b; padding-top: 10px;'>
                                                        <strong style='color: #334155;'>Report Date</strong><br>
                                                        $dateThFull
                                                    </td>
                                                    <td style='font-size: 13px; color: #64748b; padding-top: 10px;'>
                                                        <strong style='color: #334155;'>Total Machines</strong><br>
                                                        $total machines
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Summary Cards -->
                        <tr>
                            <td style='padding: 10px 30px 5px;'>
                                <table width='100%' cellpadding='0' cellspacing='0'>
                                    <tr>
                                        <!-- Checked Card -->
                                        <td width='32%' style='padding-right: 8px;'>
                                            <table width='100%' cellpadding='0' cellspacing='0' style='background: linear-gradient(135deg, #059669, #34d399); border-radius: 10px;'>
                                                <tr>
                                                    <td style='padding: 20px; text-align: center;'>
                                                        <div style='font-size: 36px; font-weight: 800; color: #ffffff;'>$checked_count</div>
                                                        <div style='font-size: 11px; color: rgba(255,255,255,0.85); text-transform: uppercase; letter-spacing: 1.5px; margin-top: 4px;'>Checked</div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                        <!-- Pending Card -->
                                        <td width='32%' style='padding: 0 4px;'>
                                            <table width='100%' cellpadding='0' cellspacing='0' style='background: linear-gradient(135deg, #dc2626, #f87171); border-radius: 10px;'>
                                                <tr>
                                                    <td style='padding: 20px; text-align: center;'>
                                                        <div style='font-size: 36px; font-weight: 800; color: #ffffff;'>$pending_count</div>
                                                        <div style='font-size: 11px; color: rgba(255,255,255,0.85); text-transform: uppercase; letter-spacing: 1.5px; margin-top: 4px;'>Pending</div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                        <!-- Downtime Card -->
                                        <td width='32%' style='padding-left: 8px;'>
                                            <table width='100%' cellpadding='0' cellspacing='0' style='background: linear-gradient(135deg, #d97706, #fbbf24); border-radius: 10px;'>
                                                <tr>
                                                    <td style='padding: 20px; text-align: center;'>
                                                        <div style='font-size: 36px; font-weight: 800; color: #ffffff;'>$downtime_count</div>
                                                        <div style='font-size: 11px; color: rgba(255,255,255,0.85); text-transform: uppercase; letter-spacing: 1.5px; margin-top: 4px;'>Downtime</div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Progress Bar -->
                        <tr>
                            <td style='padding: 15px 30px 5px;'>
                                <table width='100%' cellpadding='0' cellspacing='0'>
                                    <tr>
                                        <td style='font-size: 12px; color: #64748b; padding-bottom: 6px;'>
                                            Completion Rate: <strong style='color: #1e40af;'>$percent%</strong> ($checked_count / $total)
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <table width='100%' cellpadding='0' cellspacing='0' style='background: #e5e7eb; border-radius: 6px; overflow: hidden;'>
                                                <tr>
                                                    <td style='background: linear-gradient(90deg, #059669, #34d399); width: {$percent}%; height: 10px; border-radius: 6px;'></td>
                                                    <td style='height: 10px;'></td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Pending Machines Section -->
                        <tr>
                            <td style='padding: 25px 30px 10px;'>
                                <h2 style='margin: 0 0 15px; font-size: 16px; color: #1e293b; border-left: 4px solid #dc2626; padding-left: 12px;'>
                                    Pending Machines ($pending_count)
                                </h2>
                                <table width='100%' cellpadding='0' cellspacing='0' style='border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;'>
                                    <tr>
                                        <th style='padding: 12px 15px; background: #1e293b; color: #ffffff; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; width: 40px;'>#</th>
                                        <th style='padding: 12px 15px; background: #1e293b; color: #ffffff; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;'>Code</th>
                                        <th style='padding: 12px 15px; background: #1e293b; color: #ffffff; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;'>Machine Name</th>
                                        <th style='padding: 12px 15px; background: #1e293b; color: #ffffff; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: center;'>Status</th>
                                    </tr>
                                    $pendingRows
                                </table>
                            </td>
                        </tr>

                        <!-- Downtime Section -->
                        <tr>
                            <td style='padding: 25px 30px 10px;'>
                                <h2 style='margin: 0 0 15px; font-size: 16px; color: #1e293b; border-left: 4px solid #d97706; padding-left: 12px;'>
                                    Downtime &amp; Repair Issues ($downtime_count)
                                </h2>
                                <table width='100%' cellpadding='0' cellspacing='0' style='border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;'>
                                    <tr>
                                        <th style='padding: 12px 15px; background: #1e293b; color: #ffffff; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: center;'>Time</th>
                                        <th style='padding: 12px 15px; background: #1e293b; color: #ffffff; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;'>Code</th>
                                        <th style='padding: 12px 15px; background: #1e293b; color: #ffffff; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;'>Machine</th>
                                        <th style='padding: 12px 15px; background: #1e293b; color: #ffffff; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;'>Problem</th>
                                        <th style='padding: 12px 15px; background: #1e293b; color: #ffffff; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; text-align: center;'>Status</th>
                                    </tr>
                                    $downtimeRows
                                </table>
                            </td>
                        </tr>

                        <!-- CTA Button -->
                        <tr>
                            <td style='padding: 25px 30px;'>
                                <table width='100%' cellpadding='0' cellspacing='0'>
                                    <tr>
                                        <td align='center'>
                                            <a href='$dashboardUrl' style='display: inline-block; background: linear-gradient(135deg, #1e40af, #3b82f6); color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 8px; font-weight: 700; font-size: 14px; letter-spacing: 0.5px;'>
                                                View Full Dashboard
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style='background: #f8fafc; padding: 20px 30px; border-top: 1px solid #e2e8f0; text-align: center;'>
                                <p style='margin: 0; font-size: 12px; color: #94a3b8;'>
                                    <strong style='color: #64748b;'>Hana Check Sheet System</strong><br>
                                    This is an automated report. Please do not reply to this email.<br>
                                    Generated at " . date('d/m/Y H:i:s') . "
                                </p>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>

    </body>
    </html>
    ";

    return $html;
}
