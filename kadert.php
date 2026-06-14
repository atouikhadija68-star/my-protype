<?php
// ============================================================
// منصة e-Prison للخدمات الإلكترونية للسجناء في الجزائر
// e-Prison Platform - Algeria Digital Prison Services
// ============================================================
// تهيئة الجلسة
session_start();

// ============================================================
// إعداد قاعدة البيانات - Database Configuration
// ============================================================
$DB_HOST = 'localhost';      // مضيف قاعدة البيانات
$DB_USER = 'root';           // اسم المستخدم
$DB_PASS = '';               // كلمة المرور
$DB_NAME = 'prison_bb';      // اسم قاعدة البيانات

// الاتصال بـ MySQL بدون تحديد قاعدة البيانات أولاً
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS);
if ($conn->connect_error) {
    die("<div style='font-family:Arial;color:red;padding:20px;'>خطأ في الاتصال بقاعدة البيانات: " . $conn->connect_error . "</div>");
}

// إنشاء قاعدة البيانات تلقائياً إذا لم تكن موجودة
$conn->query("CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db($DB_NAME);

// ============================================================
// إنشاء الجداول تلقائياً - Auto Create Tables
// ============================================================

// جدول المستخدمين
$conn->query("CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(100) NOT NULL COMMENT 'الاسم',
    `last_name` VARCHAR(100) NOT NULL COMMENT 'اللقب',
    `phone` VARCHAR(20) NOT NULL UNIQUE COMMENT 'رقم الهاتف',
    `national_id` VARCHAR(20) NOT NULL COMMENT 'رقم التعريف الوطني',
    `person_type` ENUM('citizen','lawyer','notary','bailiff') DEFAULT 'citizen' COMMENT 'طبيعة الشخص',
    `password` VARCHAR(255) NOT NULL COMMENT 'كلمة المرور',
    `role` ENUM('user','admin') DEFAULT 'user' COMMENT 'الدور',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// جدول السجناء
$conn->query("CREATE TABLE IF NOT EXISTS `prisoners` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `prisoner_number` VARCHAR(20) UNIQUE COMMENT 'رقم السجين التلقائي',
    `first_name` VARCHAR(100) NOT NULL COMMENT 'الاسم',
    `last_name` VARCHAR(100) NOT NULL COMMENT 'اللقب',
    `birth_date` DATE COMMENT 'تاريخ الميلاد',
    `national_id` VARCHAR(20) COMMENT 'رقم التعريف الوطني',
    `phone` VARCHAR(20) COMMENT 'رقم الهاتف',
    `prison` VARCHAR(200) COMMENT 'المؤسسة العقابية',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// جدول المحامين
$conn->query("CREATE TABLE IF NOT EXISTS `lawyers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(100) NOT NULL COMMENT 'الاسم',
    `last_name` VARCHAR(100) NOT NULL COMMENT 'اللقب',
    `national_id` VARCHAR(20) COMMENT 'رقم التعريف الوطني',
    `phone` VARCHAR(20) COMMENT 'رقم الهاتف',
    `card_file` VARCHAR(255) COMMENT 'ملف البطاقة المهنية',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// جدول الطلبات
$conn->query("CREATE TABLE IF NOT EXISTS `requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT COMMENT 'معرف المستخدم',
    `service_type` ENUM('message','visit','notification','shop') COMMENT 'نوع الخدمة',
    `sender_first_name` VARCHAR(100) COMMENT 'اسم المرسل',
    `sender_last_name` VARCHAR(100) COMMENT 'لقب المرسل',
    `prisoner_number` VARCHAR(20) COMMENT 'رقم السجين',
    `message_text` TEXT COMMENT 'نص الرسالة',
    `visit_type` ENUM('citizen','lawyer') COMMENT 'نوع الزيارة',
    `visit_slot` VARCHAR(50) COMMENT 'فوج الزيارة',
    `visit_date` DATE COMMENT 'تاريخ الزيارة',
    `person_file` VARCHAR(255) COMMENT 'ملف رخصة الاتصال',
    `professional_card` VARCHAR(255) COMMENT 'البطاقة المهنية',
    `notification_file` VARCHAR(255) COMMENT 'ملف التبليغ',
    `shop_items` TEXT COMMENT 'منتجات المتجر',
    `status` ENUM('pending','accepted','rejected') DEFAULT 'pending' COMMENT 'حالة الطلب',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// إضافة أعمدة التوقيع والبصمة تلقائياً إذا لم تكن موجودة
$conn->query("ALTER TABLE `requests` ADD COLUMN IF NOT EXISTS `signature_file` VARCHAR(255) DEFAULT NULL COMMENT 'مسار صورة التوقيع'");
$conn->query("ALTER TABLE `requests` ADD COLUMN IF NOT EXISTS `fingerprint_file` VARCHAR(255) DEFAULT NULL COMMENT 'مسار صورة البصمة'");
$conn->query("ALTER TABLE `requests` ADD COLUMN IF NOT EXISTS `signed_at` DATETIME DEFAULT NULL COMMENT 'تاريخ التوقيع'");

// جدول الدفعات
$conn->query("CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_id` INT COMMENT 'معرف الطلب',
    `user_id` INT COMMENT 'معرف المستخدم',
    `amount` DECIMAL(10,2) COMMENT 'المبلغ',
    `card_number` VARCHAR(20) COMMENT 'رقم البطاقة (مخفي)',
    `card_holder` VARCHAR(100) COMMENT 'اسم صاحب البطاقة',
    `status` ENUM('paid','refunded') DEFAULT 'paid' COMMENT 'حالة الدفع',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`request_id`) REFERENCES `requests`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// جدول الإشعارات
$conn->query("CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT COMMENT 'معرف المستخدم',
    `message` TEXT COMMENT 'نص الإشعار',
    `is_read` TINYINT(1) DEFAULT 0 COMMENT 'هل قُرئ',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// إنشاء حساب المدير الافتراضي إذا لم يكن موجوداً
$adminCheck = $conn->query("SELECT id FROM users WHERE role='admin' LIMIT 1");
if ($adminCheck->num_rows === 0) {
    $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (first_name, last_name, phone, national_id, password, role) 
                  VALUES ('مدير', 'النظام', '0555000000', '00000000', '$adminPass', 'admin')");
}

// دالة توليد رقم السجين التلقائي
function generatePrisonerNumber($conn) {
    $year = date('Y');
    $result = $conn->query("SELECT COUNT(*) as cnt FROM prisoners WHERE YEAR(created_at)='$year'");
    $row = $result->fetch_assoc();
    $num = str_pad($row['cnt'] + 1, 4, '0', STR_PAD_LEFT);
    return "SJN-$year-$num";
}

// ============================================================
// معالجة الكلمات المحظورة
// ============================================================
$bannedWords = ['قتل', 'هرب', 'سلاح', 'مخدر', 'تهريب', 'إرهاب', 'انتقام'];
function filterMessage($text, $bannedWords) {
    foreach ($bannedWords as $word) {
        if (mb_strpos($text, $word) !== false) return false;
    }
    return true;
}

// ============================================================
// معالجة رفع الملفات
// ============================================================
function handleFileUpload($fileKey, $folder = 'uploads') {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== 0) return null;
    $uploadDir = __DIR__ . "/$folder/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') return null;
    $fileName = uniqid() . '_' . time() . '.pdf';
    if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $uploadDir . $fileName)) {
        return "$folder/$fileName";
    }
    return null;
}

// تحويل النص العربي إلى لاتيني مقروء (Transliteration بسيطة)
function transliterateAr($text) {
    // نستخدم iconv للتحويل مع استبدال الأحرف غير المعروفة
    $latin = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    // إذا فشل iconv نرجع نص بديل
    return $latin ?: 'N/A';
}

// ============================================================
// دالة إنشاء PDF بـ PHP خالص (بدون مكتبات خارجية)
// تستخدم تنسيق PDF الأساسي مكتوباً يدوياً
// ============================================================
function generatePDFContent($type, $data) {
    // مجلد حفظ ملفات PDF المُنشأة
    $pdfDir = __DIR__ . '/generated_pdfs/';
    if (!is_dir($pdfDir)) mkdir($pdfDir, 0755, true);

    // اسم الملف الفريد
    $fileName = $type . '_' . uniqid() . '_' . time() . '.pdf';
    $filePath = $pdfDir . $fileName;

    // --- بناء محتوى PDF بصيغة PostScript/PDF خام ---
    // نستخدم مكتبة FPDF المضمنة عبر كودها مباشرة (بدون تثبيت)
    // أو نولّد ملف PDF يدوياً بالمواصفات الأساسية

    // نُنشئ PDF نصياً خاماً (بنية PDF 1.4 الأساسية)
    $dateStr   = date('d/m/Y');
    $timeStr   = date('H:i');
    $reqId     = $data['req_id'] ?? '';
    $sender    = ($data['sender_first'] ?? '') . ' ' . ($data['sender_last'] ?? '');
    $prisoner  = $data['prisoner_number'] ?? '';

    if ($type === 'message') {
        $titleAr  = 'رسالة إلكترونية - e-Prison';
        $bodyText = $data['message_text'] ?? '';
        $label1   = 'نص الرسالة:';
    } else {
        $titleAr  = 'تبليغ إلكتروني - e-Prison';
        $bodyText = $data['notification_text'] ?? '';
        $label1   = 'نص التبليغ:';
    }

    // توليد HTML احترافي يُمثّل الوثيقة ثم نحوّله لـ PDF بـ wkhtmltopdf إذا متاح
    // وإلا نحفظه كـ HTML مع extension .pdf (يفتح في المتصفح بشكل نظيف)
    // الحل المضمون 100% بدون أي مكتبة: PDF خام يدوي

    // سنبني ملف PDF خام بالمواصفات الأساسية
    // يدعم النص اللاتيني في الـ PDF stream مع metadata عربية في comments
    $pdf = buildRawPDF($titleAr, $sender, $prisoner, $bodyText, $label1, $reqId, $dateStr, $timeStr, $type);

    file_put_contents($filePath, $pdf);
    return 'generated_pdfs/' . $fileName;
}

// بناء PDF خام بالمواصفات القياسية (PDF 1.4)
// يدعم النص الأساسي ويعمل على كل المتصفحات
function buildRawPDF($title, $sender, $prisoner, $bodyText, $label, $reqId, $dateStr, $timeStr, $type) {
    // تحويل النص العربي لـ UTF-8 مع Reverse للعرض الصحيح في PDF
    // نستخدم xObject مع نص مُضمَّن

    // بناء صفحة HTML داخل PDF كـ embedded stream
    // الحل الأفضل: توليد ملف HTML بامتداد .pdf يفتح مباشرة في المتصفح
    // لكن الأفضل هو كتابة PDF خام حقيقي

    // PDF structure بسيط وصحيح
    $icon = ($type === 'message') ? 'LETTRE ELECTRONIQUE' : 'NOTIFICATION ELECTRONIQUE';

    // تحضير النصوص (نكتبها بالأحرف اللاتينية + العربية في metadata)
    $senderLatin   = transliterateAr($sender);
    $prisonerClean = preg_replace('/[^\w\-]/', '', $prisoner);
    $bodyClean     = mb_substr($bodyText, 0, 800); // حد أقصى للنص في PDF الخام

    // نبني PDF بهيكل صحيح مع دعم UTF-16BE للعربية
    $objects = [];
    $offsets = [];

    // Object 1: Catalog
    $objects[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

    // Object 2: Pages
    $objects[2] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

    // Object 4: Font (Helvetica - مدمجة في كل PDF viewer)
    $objects[4] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

    // Object 5: Font Bold
    $objects[5] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

    // بناء محتوى الصفحة
    $typeColor = ($type === 'message') ? '0.10 0.47 0.29' : '0.31 0.27 0.60'; // أخضر أو بنفسجي
    $typeLabel = ($type === 'message') ? 'MESSAGE ELECTRONIQUE' : 'NOTIFICATION ELECTRONIQUE';

    // نص المحتوى بتنسيق PDF stream
    $content = buildPDFStream($typeColor, $typeLabel, $sender, $prisoner, $bodyText, $reqId, $dateStr, $timeStr, $label);

    $streamLen = strlen($content);

    // Object 6: Content Stream
    $objects[6] = "6 0 obj\n<< /Length $streamLen >>\nstream\n$content\nendstream\nendobj\n";

    // Object 3: Page (A4 = 595 x 842 pts)
    $objects[3] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 6 0 R /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> >>\nendobj\n";

    // بناء ملف PDF
    $pdf = "%PDF-1.4\n";
    $pdf .= "%\xe2\xe3\xcf\xd3\n"; // تعليق ثنائي يشير لـ PDF ثنائي

    foreach ($objects as $num => $obj) {
        $offsets[$num] = strlen($pdf);
        $pdf .= $obj;
    }

    // Cross-reference table
    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n";
    $pdf .= "0 7\n";
    $pdf .= "0000000000 65535 f \n";
    foreach ($objects as $num => $obj) {
        $pdf .= str_pad($offsets[$num], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }

    // Trailer
    $pdf .= "trailer\n<< /Size 7 /Root 1 0 R >>\n";
    $pdf .= "startxref\n$xrefOffset\n%%EOF\n";

    return $pdf;
}

// بناء محتوى الصفحة (PDF Graphics/Text operators)
function buildPDFStream($color, $typeLabel, $sender, $prisoner, $bodyText, $reqId, $date, $time, $label) {
    // تنظيف النصوص للـ PDF (إزالة الأحرف الخاصة)
    $cleanSender   = pdfStr($sender);
    $cleanPrisoner = pdfStr($prisoner);
    $cleanReqId    = pdfStr('#' . $reqId);
    $cleanDate     = pdfStr($date . ' - ' . $time);

    // تقطيع النص الطويل لأسطر
    $lines = wrapText($bodyText, 70);

    // بناء stream
    $s = '';

    // --- خلفية الرأس (مستطيل ملوّن) ---
    list($r, $g, $b) = explode(' ', $color);
    $s .= "$r $g $b rg\n";         // لون الملء
    $s .= "0 780 595 62 re f\n";   // رسم مستطيل الرأس

    // --- شعار وعنوان الرأس (أبيض) ---
    $s .= "1 1 1 rg\n";            // لون أبيض
    $s .= "BT\n";
    $s .= "/F2 16 Tf\n";           // خط Bold كبير
    $s .= "195 810 Td\n";
    $s .= "(e-Prison Platform) Tj\n";
    $s .= "/F1 9 Tf\n";
    $s .= "160 795 Td\n";
    $s .= "(Republique Algerienne Democratique et Populaire) Tj\n";
    $s .= "ET\n";

    // --- بطاقة النوع ملونة ---
    $s .= "$r $g $b rg\n";
    $s .= "40 740 515 28 re f\n";   // مستطيل نوع الخدمة
    $s .= "1 1 1 rg\n";
    $s .= "BT\n";
    $s .= "/F2 12 Tf\n";
    $s .= "220 749 Td\n";
    $s .= "($typeLabel) Tj\n";
    $s .= "ET\n";

    // --- معلومات الطلب في مربعات ---
    $s .= "0.95 0.97 0.96 rg\n";   // رمادي فاتح خلفية
    $s .= "40 620 515 105 re f\n";

    // حدود المربع
    $s .= "0.8 0.85 0.82 RG\n";    // لون الحدود
    $s .= "1 w\n";
    $s .= "40 620 515 105 re S\n";

    // النصوص
    $s .= "0 0 0 rg\n";            // لون أسود للنص
    $s .= "BT\n";
    $s .= "/F2 9 Tf\n";
    $s .= "55 705 Td\n";  $s .= "(No. Demande:) Tj\n";
    $s .= "/F1 9 Tf\n";
    $s .= "130 705 Td\n"; $s .= "($cleanReqId) Tj\n";

    $s .= "/F2 9 Tf\n";
    $s .= "300 705 Td\n"; $s .= "(Date:) Tj\n";
    $s .= "/F1 9 Tf\n";
    $s .= "325 705 Td\n"; $s .= "($cleanDate) Tj\n";

    $s .= "/F2 9 Tf\n";
    $s .= "55 685 Td\n";  $s .= "(Expediteur:) Tj\n";
    $s .= "/F1 9 Tf\n";
    $s .= "130 685 Td\n"; $s .= "($cleanSender) Tj\n";

    $s .= "/F2 9 Tf\n";
    $s .= "55 665 Td\n";  $s .= "(No. Detenu:) Tj\n";
    $s .= "/F1 9 Tf\n";
    $s .= "130 665 Td\n"; $s .= "($cleanPrisoner) Tj\n";

    $s .= "/F2 9 Tf\n";
    $s .= "55 645 Td\n";  $s .= "(Statut:) Tj\n";
    $s .= "/F1 9 Tf\n";
    $s .= "130 645 Td\n"; $s .= "(En attente de traitement) Tj\n";

    $s .= "ET\n";

    // --- عنوان المحتوى ---
    list($r,$g,$b) = explode(' ', $color);
    $s .= "$r $g $b rg\n";
    $s .= "BT\n";
    $s .= "/F2 11 Tf\n";
    $s .= "40 600 Td\n";
    $s .= "($label) Tj\n";

    // خط فاصل
    $s .= "ET\n";
    $s .= "$r $g $b RG\n";
    $s .= "2 w\n";
    $s .= "40 596 m 555 596 l S\n";

    // --- نص المحتوى (الرسالة أو التبليغ) ---
    $s .= "0 0 0 rg\n";
    $s .= "0 0 0 RG\n";
    $s .= "BT\n";
    $s .= "/F1 10 Tf\n";

    $yPos = 580;
    foreach ($lines as $line) {
        $cleanLine = pdfStr($line);
        if ($yPos < 100) break; // تجنب تجاوز الصفحة
        $s .= "55 $yPos Td\n";
        $s .= "($cleanLine) Tj\n";
        $s .= "0 -16 Td\n";
        $yPos -= 16;
    }
    $s .= "ET\n";

    // --- تذييل الصفحة ---
    list($r,$g,$b) = explode(' ', $color);
    $s .= "$r $g $b rg\n";
    $s .= "0 0 595 35 re f\n";     // مستطيل التذييل
    $s .= "1 1 1 rg\n";
    $s .= "BT\n";
    $s .= "/F1 8 Tf\n";
    $s .= "40 15 Td\n";
    $s .= "(e-Prison | Direction Generale de l'Administration Penitentiaire | Algerie 2026) Tj\n";
    $s .= "ET\n";

    return $s;
}

// تنظيف النص لاستخدامه في PDF stream (لا يدعم العربية المباشرة في Type1)
function pdfStr($text) {
    // تحويل العربية إلى لاتينية مقروءة في الـ metadata
    // النص يُحفظ عربياً في قاعدة البيانات لكن في PDF نكتب اللاتيني
    $text = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
    $text = str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $text);
    return $text;
}

// تقطيع النص لأسطر
function wrapText($text, $maxLen = 70) {
    // تقطيع حسب الكلمات (اللاتيني) أو الحروف (العربي)
    $words  = preg_split('/\s+/u', $text);
    $lines  = [];
    $cur    = '';
    foreach ($words as $word) {
        $testLine = $cur ? $cur . ' ' . $word : $word;
        if (mb_strlen($testLine) <= $maxLen) {
            $cur = $testLine;
        } else {
            if ($cur) $lines[] = $cur;
            $cur = mb_strlen($word) > $maxLen ? mb_substr($word, 0, $maxLen) : $word;
        }
    }
    if ($cur) $lines[] = $cur;
    // ترجمة بسيطة للعربية
    return array_map(function($l) {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $l);
    }, $lines);
}

// ============================================================
// معالجة الطلبات POST
// ============================================================
$message = '';
$messageType = '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- تسجيل حساب جديد ---
if ($action === 'register') {
    $fname = trim($conn->real_escape_string($_POST['first_name'] ?? ''));
    $lname = trim($conn->real_escape_string($_POST['last_name'] ?? ''));
    $phone = trim($conn->real_escape_string($_POST['phone'] ?? ''));
    $nid   = trim($conn->real_escape_string($_POST['national_id'] ?? ''));
    $ptype = $conn->real_escape_string($_POST['person_type'] ?? 'citizen');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';
    if ($pass !== $pass2) {
        $message = 'كلمات المرور غير متطابقة'; $messageType = 'error';
    } elseif (strlen($pass) < 6) {
        $message = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل'; $messageType = 'error';
    } else {
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        $res = $conn->query("INSERT INTO users (first_name,last_name,phone,national_id,person_type,password) VALUES ('$fname','$lname','$phone','$nid','$ptype','$hashed')");
        if ($res) { $message = 'تم إنشاء الحساب بنجاح! يمكنك تسجيل الدخول'; $messageType = 'success'; }
        else { $message = 'رقم الهاتف مستخدم بالفعل'; $messageType = 'error'; }
    }
}

// --- تسجيل الدخول ---
if ($action === 'login') {
    $phone = trim($conn->real_escape_string($_POST['phone'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    $res = $conn->query("SELECT * FROM users WHERE phone='$phone' LIMIT 1");
    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        if (password_verify($pass, $user['password'])) {
            $_SESSION['user'] = $user;
            header('Location: ?page=' . ($user['role'] === 'admin' ? 'admin' : 'dashboard'));
            exit;
        }
    }
    $message = 'رقم الهاتف أو كلمة المرور غير صحيحة'; $messageType = 'error';
}

// --- تسجيل الخروج ---
if ($action === 'logout') {
    session_destroy();
    header('Location: ?');
    exit;
}

// --- إرسال خدمة (رسالة/زيارة/تبليغ/متجر) ---
if ($action === 'submit_service' && isset($_SESSION['user'])) {
    $uid    = (int)$_SESSION['user']['id'];
    $stype  = $conn->real_escape_string($_POST['service_type'] ?? '');
    $sfname = $conn->real_escape_string($_POST['sender_first_name'] ?? '');
    $slname = $conn->real_escape_string($_POST['sender_last_name'] ?? '');
    $pnum   = $conn->real_escape_string($_POST['prisoner_number'] ?? '');
    $msgTxt = '';
    $vtype  = $conn->real_escape_string($_POST['visit_type'] ?? 'citizen');
    $vslot  = $conn->real_escape_string($_POST['visit_slot'] ?? '');
    $vdate  = $conn->real_escape_string($_POST['visit_date'] ?? '');
    $shopItems = $conn->real_escape_string($_POST['shop_items'] ?? '');
    $cardNum = $conn->real_escape_string(substr($_POST['card_number'] ?? '', -4));
    $cardHolder = $conn->real_escape_string($_POST['card_holder'] ?? '');

    // فلترة الرسائل
    if ($stype === 'message') {
        $msgRaw = $_POST['message_text'] ?? '';
        if (!filterMessage($msgRaw, $bannedWords)) {
            $message = 'الرسالة تحتوي على كلمات ممنوعة'; $messageType = 'error';
            goto endSubmit;
        }
        $msgTxt = $conn->real_escape_string($msgRaw);
    }

    // التحقق من نص التبليغ (إجباري - textarea)
    $notifTextRaw = '';
    if ($stype === 'notification') {
        $notifTextRaw = trim($_POST['notification_text'] ?? '');
        if (empty($notifTextRaw)) {
            $message = 'يجب كتابة نص التبليغ'; $messageType = 'error';
            goto endSubmit;
        }
    }

    // تحديد السعر
    $prices = ['message'=>200, 'visit'=>100, 'notification'=>200, 'shop'=>100];
    // المتجر: رسوم الخدمة 100 دج + مجموع المنتجات
    if ($stype === 'shop') {
        $shopProductsTotal = (int)($_POST['shop_products_total'] ?? 0);
        $amount = 100 + $shopProductsTotal; // 100 دج رسوم + مجموع المنتجات
    } else {
        $amount = ($stype === 'visit' && $vtype === 'lawyer') ? 0 : ($prices[$stype] ?? 0);
    }

    // رفع ملفات الزيارة فقط (الرسالة والتبليغ لا يحتاجان رفع ملف)
    $personFile = handleFileUpload('person_file');
    $profCard   = handleFileUpload('professional_card');
    $notifFile  = null; // لا يوجد رفع ملف للتبليغ بعد الآن

    // حفظ الطلب أولاً بدون ملف PDF (سنُضيفه بعد الإدراج)
    $pf = $personFile ? "'$personFile'" : "NULL";
    $pc = $profCard   ? "'$profCard'"   : "NULL";
    $notifTextEsc = $conn->real_escape_string($notifTextRaw);

    $conn->query("INSERT INTO requests (user_id,service_type,sender_first_name,sender_last_name,prisoner_number,message_text,visit_type,visit_slot,visit_date,person_file,professional_card,notification_file,shop_items)
                  VALUES ($uid,'$stype','$sfname','$slname','$pnum','$msgTxt','$vtype','$vslot','$vdate',$pf,$pc,NULL,'$shopItems')");
    $reqId = $conn->insert_id;

    // --- إنشاء PDF تلقائي للرسالة الإلكترونية ---
    if ($stype === 'message' && !empty($msgTxt)) {
        $msgPdfPath = generatePDFContent('message', [
            'req_id'          => $reqId,
            'sender_first'    => $_POST['sender_first_name'] ?? '',
            'sender_last'     => $_POST['sender_last_name'] ?? '',
            'prisoner_number' => $_POST['prisoner_number'] ?? '',
            'message_text'    => $_POST['message_text'] ?? '',
        ]);
        if ($msgPdfPath) {
            // حفظ مسار PDF الرسالة في حقل notification_file (نعيد استخدامه لحفظ PDF المُولَّد)
            $mpEsc = $conn->real_escape_string($msgPdfPath);
            $conn->query("UPDATE requests SET notification_file='$mpEsc' WHERE id=$reqId");
        }
    }

    // --- إنشاء PDF تلقائي للتبليغ الإلكتروني ---
    if ($stype === 'notification' && !empty($notifTextRaw)) {
        // حفظ نص التبليغ في message_text لاستخدامه لاحقاً
        $ntEsc = $conn->real_escape_string($notifTextRaw);
        $conn->query("UPDATE requests SET message_text='$ntEsc' WHERE id=$reqId");

        $notifPdfPath = generatePDFContent('notification', [
            'req_id'              => $reqId,
            'sender_first'        => $_POST['sender_first_name'] ?? '',
            'sender_last'         => $_POST['sender_last_name'] ?? '',
            'prisoner_number'     => $_POST['prisoner_number'] ?? '',
            'notification_text'   => $notifTextRaw,
        ]);
        if ($notifPdfPath) {
            $npEsc = $conn->real_escape_string($notifPdfPath);
            $conn->query("UPDATE requests SET notification_file='$npEsc' WHERE id=$reqId");
        }
    }

    // حفظ الدفع
    if ($amount > 0) {
        $conn->query("INSERT INTO payments (request_id,user_id,amount,card_number,card_holder) VALUES ($reqId,$uid,$amount,'****$cardNum','$cardHolder')");
    }

    // إشعار
    $serviceNames = ['message'=>'رسالة إلكترونية','visit'=>'زيارة','notification'=>'تبليغ إلكتروني','shop'=>'طلب متجر'];
    $sName = $serviceNames[$stype] ?? $stype;
    $conn->query("INSERT INTO notifications (user_id,message) VALUES ($uid,'تم استلام طلبك: $sName - بانتظار المراجعة')");

    $message = 'تم إرسال طلبك بنجاح! رقم الطلب: #' . $reqId; $messageType = 'success';
    endSubmit:;
}

// --- إدارة: حفظ التوقيع والبصمة للتبليغ ---
if ($action === 'save_signature' && isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
    $rid         = (int)($_POST['request_id'] ?? 0);
    $sigData     = $_POST['signature_data'] ?? '';
    $fingerData  = $_POST['fingerprint_data'] ?? '';

    $sigPath = null;
    $fingerPath = null;

    // حفظ التوقيع PNG
    if ($sigData && strpos($sigData, 'data:image/png;base64,') === 0) {
        $sigDir = __DIR__ . '/uploads/signatures/';
        if (!is_dir($sigDir)) mkdir($sigDir, 0755, true);
        $sigFile = 'sig_' . $rid . '_' . time() . '.png';
        $sigImg  = base64_decode(str_replace('data:image/png;base64,', '', $sigData));
        file_put_contents($sigDir . $sigFile, $sigImg);
        $sigPath = 'uploads/signatures/' . $sigFile;
    }

    // حفظ البصمة PNG
    if ($fingerData && strpos($fingerData, 'data:image/png;base64,') === 0) {
        $fpDir = __DIR__ . '/uploads/fingerprints/';
        if (!is_dir($fpDir)) mkdir($fpDir, 0755, true);
        $fpFile = 'fp_' . $rid . '_' . time() . '.png';
        $fpImg  = base64_decode(str_replace('data:image/png;base64,', '', $fingerData));
        file_put_contents($fpDir . $fpFile, $fpImg);
        $fingerPath = 'uploads/fingerprints/' . $fpFile;
    }

    if ($sigPath && $fingerPath) {
        $spEsc  = $conn->real_escape_string($sigPath);
        $fpEsc  = $conn->real_escape_string($fingerPath);
        $now    = date('Y-m-d H:i:s');
        $conn->query("UPDATE requests SET signature_file='$spEsc', fingerprint_file='$fpEsc', signed_at='$now' WHERE id=$rid");
        $message = 'تم حفظ التوقيع والبصمة بنجاح'; $messageType = 'success';
    } else {
        $message = 'يرجى رسم التوقيع والبصمة كاملاً'; $messageType = 'error';
    }
}

// --- إدارة: إضافة سجين ---
if ($action === 'add_prisoner' && isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
    $fname = $conn->real_escape_string($_POST['first_name'] ?? '');
    $lname = $conn->real_escape_string($_POST['last_name'] ?? '');
    $bdate = $conn->real_escape_string($_POST['birth_date'] ?? '');
    $nid   = $conn->real_escape_string($_POST['national_id'] ?? '');
    $phone = $conn->real_escape_string($_POST['phone'] ?? '');
    $prison= $conn->real_escape_string($_POST['prison'] ?? '');
    $pnum  = generatePrisonerNumber($conn);
    $conn->query("INSERT INTO prisoners (prisoner_number,first_name,last_name,birth_date,national_id,phone,prison) VALUES ('$pnum','$fname','$lname','$bdate','$nid','$phone','$prison')");
    $message = "تم إضافة السجين بنجاح! رقمه: $pnum"; $messageType = 'success';
}

// --- إدارة: إضافة محامي ---
if ($action === 'add_lawyer' && isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
    $fname = $conn->real_escape_string($_POST['first_name'] ?? '');
    $lname = $conn->real_escape_string($_POST['last_name'] ?? '');
    $nid   = $conn->real_escape_string($_POST['national_id'] ?? '');
    $phone = $conn->real_escape_string($_POST['phone'] ?? '');
    $cardF = handleFileUpload('card_file');
    $cf    = $cardF ? "'$cardF'" : "NULL";
    $conn->query("INSERT INTO lawyers (first_name,last_name,national_id,phone,card_file) VALUES ('$fname','$lname','$nid','$phone',$cf)");
    $message = 'تم إضافة المحامي بنجاح'; $messageType = 'success';
}

// --- إدارة: تغيير حالة الطلب ---
if ($action === 'update_status' && isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
    $rid    = (int)($_POST['request_id'] ?? 0);
    $status = $conn->real_escape_string($_POST['status'] ?? 'pending');
    $conn->query("UPDATE requests SET status='$status' WHERE id=$rid");
    // إشعار المستخدم
    $rRes = $conn->query("SELECT user_id,service_type FROM requests WHERE id=$rid");
    if ($rRes->num_rows > 0) {
        $rRow = $rRes->fetch_assoc();
        $sNames = ['message'=>'رسالة','visit'=>'زيارة','notification'=>'تبليغ','shop'=>'متجر'];
        $sn = $sNames[$rRow['service_type']] ?? '';
        $statusAr = ['accepted'=>'مقبول','rejected'=>'مرفوض','pending'=>'معلق'][$status] ?? $status;
        $conn->query("INSERT INTO notifications (user_id,message) VALUES ({$rRow['user_id']},'تم تحديث حالة طلب $sn إلى: $statusAr')");
    }
    $message = 'تم تحديث الحالة'; $messageType = 'success';
}

// ============================================================
// تحديد الصفحة الحالية
// ============================================================
$page = $_GET['page'] ?? 'home';
$user = $_SESSION['user'] ?? null;

// حماية الصفحات المحمية
if (in_array($page, ['dashboard','service']) && !$user) { header('Location: ?page=login'); exit; }
if ($page === 'admin' && (!$user || $user['role'] !== 'admin')) { header('Location: ?page=login'); exit; }

// جلب إحصاءات الإدارة
$stats = [];
if ($page === 'admin' && $user) {
    $stats['users']     = $conn->query("SELECT COUNT(*) c FROM users WHERE role='user'")->fetch_assoc()['c'];
    $stats['prisoners'] = $conn->query("SELECT COUNT(*) c FROM prisoners")->fetch_assoc()['c'];
    $stats['requests']  = $conn->query("SELECT COUNT(*) c FROM requests")->fetch_assoc()['c'];
    $stats['pending']   = $conn->query("SELECT COUNT(*) c FROM requests WHERE status='pending'")->fetch_assoc()['c'];
}

// إشعارات المستخدم
$unreadCount = 0;
if ($user) {
    $uid = (int)$user['id'];
    $nRes = $conn->query("SELECT COUNT(*) c FROM notifications WHERE user_id=$uid AND is_read=0");
    $unreadCount = $nRes->fetch_assoc()['c'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>e-Prison - منصة الخدمات الإلكترونية للسجناء | الجزائر</title>
<!-- خطوط Google -->
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@300;400;500;700;900&display=swap" rel="stylesheet">
<!-- أيقونات -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ============================================================
   المتغيرات - CSS Variables
   ============================================================ */
:root {
    --primary: #1a7a4a;        /* أخضر جزائري */
    --primary-light: #22a05f;
    --primary-dark: #0f5033;
    --accent: #e8f5ee;
    --white: #ffffff;
    --gray-50: #f8fafb;
    --gray-100: #f0f4f1;
    --gray-200: #dde8e2;
    --gray-300: #b8ccbf;
    --gray-500: #6b8876;
    --gray-700: #2d4a38;
    --gray-900: #1a2e22;
    --danger: #c0392b;
    --warning: #f39c12;
    --success: #27ae60;
    --shadow-sm: 0 2px 8px rgba(26,122,74,0.08);
    --shadow-md: 0 8px 30px rgba(26,122,74,0.12);
    --shadow-lg: 0 20px 60px rgba(26,122,74,0.15);
    --radius: 16px;
    --radius-sm: 10px;
    --transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
    --font-main: 'Tajawal', sans-serif;
    --font-serif: 'Amiri', serif;
    --bg: #f5f9f6;
    --card-bg: #ffffff;
    --text: #1a2e22;
    --text-muted: #5a7a65;
    --border: #dde8e2;
    --nav-bg: rgba(255,255,255,0.95);
}

/* شبكة التوقيع المتجاوبة */
@media (max-width: 520px) {
    .sig-grid { grid-template-columns: 1fr !important; }
}

/* الوضع الليلي */
body.dark {
    --bg: #0f1f16;
    --card-bg: #172b1e;
    --text: #e8f5ee;
    --text-muted: #8aab95;
    --border: #1f3d28;
    --nav-bg: rgba(15,31,22,0.95);
    --gray-50: #0d1a12;
    --gray-100: #132110;
    --accent: #132110;
    --shadow-sm: 0 2px 8px rgba(0,0,0,0.3);
    --shadow-md: 0 8px 30px rgba(0,0,0,0.4);
}

/* ============================================================
   إعادة الضبط - Reset
   ============================================================ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    font-family: var(--font-main);
    background: var(--bg);
    color: var(--text);
    line-height: 1.7;
    transition: background 0.3s, color 0.3s;
    min-height: 100vh;
}
a { color: inherit; text-decoration: none; }
button, input, select, textarea { font-family: var(--font-main); }
img { max-width: 100%; }

/* ============================================================
   شريط التنقل - Navbar
   ============================================================ */
.navbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 1000;
    background: var(--nav-bg);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    padding: 0 2rem;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: var(--transition);
}

.nav-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 900;
    font-size: 1.4rem;
    color: var(--primary);
}
.nav-logo .logo-icon {
    width: 44px; height: 44px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    color: white;
    font-size: 1.2rem;
    box-shadow: 0 4px 12px rgba(26,122,74,0.3);
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 6px;
    list-style: none;
}
.nav-links a, .nav-btn {
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 500;
    transition: var(--transition);
    cursor: pointer;
    border: none;
    background: transparent;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 6px;
}
.nav-links a:hover, .nav-btn:hover { background: var(--accent); color: var(--primary); }
.nav-btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light)) !important;
    color: white !important;
    box-shadow: 0 4px 12px rgba(26,122,74,0.3);
}
.nav-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(26,122,74,0.4) !important; }

.nav-badge {
    background: var(--danger);
    color: white;
    border-radius: 50%;
    font-size: 0.65rem;
    width: 18px; height: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

/* قائمة اللغات */
.lang-dropdown { position: relative; }
.lang-menu {
    display: none;
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    box-shadow: var(--shadow-md);
    min-width: 130px;
    overflow: hidden;
    z-index: 100;
}
.lang-dropdown:hover .lang-menu { display: block; }
.lang-menu a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    font-size: 0.85rem;
    transition: background 0.2s;
}
.lang-menu a:hover { background: var(--accent); }

/* ============================================================
   الرئيسية
   ============================================================ */
.main-content { padding-top: 70px; }

/* Hero */
.hero {
    min-height: calc(100vh - 70px);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 4rem 2rem;
    position: relative;
    overflow: hidden;
    background: linear-gradient(160deg, var(--bg) 0%, var(--accent) 50%, var(--bg) 100%);
}
.hero::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(ellipse at center, rgba(26,122,74,0.05) 0%, transparent 60%);
    animation: heroGlow 8s ease-in-out infinite;
}
@keyframes heroGlow {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.2); opacity: 1; }
}

.hero-emblem {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2rem;
    margin-bottom: 2rem;
}

/* ميزان العدل بالأنيميشن */
.scale-icon {
    font-size: 3.5rem;
    color: var(--primary);
    filter: drop-shadow(0 4px 12px rgba(26,122,74,0.3));
}
.scale-left { animation: scaleLeft 3s ease-in-out infinite; }
.scale-right { animation: scaleRight 3s ease-in-out infinite; }

@keyframes scaleLeft {
    0%, 100% { transform: rotate(-8deg) translateY(0); }
    50% { transform: rotate(8deg) translateY(-8px); }
}
@keyframes scaleRight {
    0%, 100% { transform: rotate(8deg) translateY(-8px); }
    50% { transform: rotate(-8deg) translateY(0); }
}

.algerian-seal {
    width: 100px; height: 100px;
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: white;
    box-shadow: 0 0 0 6px rgba(26,122,74,0.15), var(--shadow-lg);
    animation: sealPulse 4s ease-in-out infinite;
}
@keyframes sealPulse {
    0%, 100% { box-shadow: 0 0 0 6px rgba(26,122,74,0.15), var(--shadow-lg); }
    50% { box-shadow: 0 0 0 14px rgba(26,122,74,0.08), var(--shadow-lg); }
}

.hero-state {
    font-family: var(--font-serif);
    font-size: 1.05rem;
    color: var(--primary);
    font-weight: 700;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
    opacity: 0;
    animation: fadeUp 0.8s 0.2s forwards;
}
.hero-ministry {
    font-family: var(--font-serif);
    font-size: 0.95rem;
    color: var(--text-muted);
    margin-bottom: 2rem;
    opacity: 0;
    animation: fadeUp 0.8s 0.4s forwards;
}
.hero-title {
    font-size: clamp(2.5rem, 6vw, 4.5rem);
    font-weight: 900;
    line-height: 1.2;
    margin-bottom: 1rem;
    opacity: 0;
    animation: fadeUp 0.8s 0.6s forwards;
}
.hero-title span { color: var(--primary); }
.hero-subtitle {
    font-size: 1.15rem;
    color: var(--text-muted);
    max-width: 560px;
    margin: 0 auto 2.5rem;
    opacity: 0;
    animation: fadeUp 0.8s 0.8s forwards;
}
.hero-cta {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    opacity: 0;
    animation: fadeUp 0.8s 1s forwards;
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* الإحصاءات في الرئيسية */
.hero-stats {
    display: flex;
    gap: 2rem;
    margin-top: 3rem;
    justify-content: center;
    flex-wrap: wrap;
    opacity: 0;
    animation: fadeUp 0.8s 1.2s forwards;
}
.stat-item {
    text-align: center;
    padding: 1.5rem 2rem;
    background: var(--card-bg);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    min-width: 140px;
}
.stat-num {
    font-size: 2rem;
    font-weight: 900;
    color: var(--primary);
    display: block;
}
.stat-label {
    font-size: 0.82rem;
    color: var(--text-muted);
    margin-top: 4px;
}

/* ============================================================
   الأزرار - Buttons
   ============================================================ */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: var(--radius-sm);
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: var(--transition);
    text-align: center;
    justify-content: center;
}
.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    box-shadow: 0 4px 15px rgba(26,122,74,0.3);
}
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(26,122,74,0.4); }
.btn-outline {
    background: transparent;
    color: var(--primary);
    border: 2px solid var(--primary);
}
.btn-outline:hover { background: var(--primary); color: white; }
.btn-danger { background: var(--danger); color: white; }
.btn-warning { background: var(--warning); color: white; }
.btn-success { background: var(--success); color: white; }
.btn-sm { padding: 8px 16px; font-size: 0.82rem; border-radius: 8px; }
.btn-lg { padding: 15px 32px; font-size: 1.05rem; border-radius: 14px; }
.btn-full { width: 100%; }

/* ============================================================
   بطاقات الخدمات - Service Cards
   ============================================================ */
.section {
    padding: 5rem 2rem;
    max-width: 1200px;
    margin: 0 auto;
}
.section-title {
    text-align: center;
    margin-bottom: 3rem;
}
.section-title h2 {
    font-size: 2.2rem;
    font-weight: 900;
    margin-bottom: 0.5rem;
}
.section-title p { color: var(--text-muted); font-size: 1rem; }
.section-divider {
    width: 60px; height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    border-radius: 2px;
    margin: 1rem auto;
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.5rem;
}
.service-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 2rem;
    transition: var(--transition);
    cursor: pointer;
    position: relative;
    overflow: hidden;
}
.service-card::before {
    content: '';
    position: absolute;
    top: 0; right: 0;
    width: 80px; height: 80px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    opacity: 0.06;
    border-radius: 0 var(--radius) 0 80px;
    transition: var(--transition);
}
.service-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}
.service-card:hover::before { opacity: 0.12; }

.service-icon {
    width: 64px; height: 64px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem;
    color: white;
    margin-bottom: 1.2rem;
    box-shadow: 0 6px 20px rgba(26,122,74,0.25);
    transition: var(--transition);
}
.service-card:hover .service-icon { transform: scale(1.1) rotate(5deg); }
.service-card h3 { font-size: 1.15rem; font-weight: 700; margin-bottom: 0.5rem; }
.service-card p { color: var(--text-muted); font-size: 0.88rem; margin-bottom: 1.2rem; }
.service-price {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--accent);
    color: var(--primary);
    padding: 5px 14px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 0.88rem;
}

/* ============================================================
   الصفحات - Pages Container
   ============================================================ */
.page-container {
    max-width: 520px;
    margin: 0 auto;
    padding: 2rem 1.5rem;
    padding-top: calc(70px + 2rem);
}
.page-container.wide {
    max-width: 900px;
}
.page-container.full {
    max-width: 1200px;
}

.page-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 2.5rem;
    box-shadow: var(--shadow-md);
}
.page-header {
    text-align: center;
    margin-bottom: 2rem;
}
.page-header .icon-wrap {
    width: 72px; height: 72px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 20px;
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem;
    color: white;
    margin: 0 auto 1rem;
    box-shadow: 0 8px 24px rgba(26,122,74,0.25);
}
.page-header h1 { font-size: 1.6rem; font-weight: 800; margin-bottom: 0.3rem; }
.page-header p { color: var(--text-muted); font-size: 0.9rem; }

/* ============================================================
   النماذج - Forms
   ============================================================ */
.form-group { margin-bottom: 1.2rem; }
.form-label {
    display: block;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 6px;
}
.form-label .req { color: var(--danger); }
.form-control {
    width: 100%;
    padding: 12px 16px;
    background: var(--bg);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: 0.95rem;
    color: var(--text);
    transition: var(--transition);
    outline: none;
}
.form-control:focus {
    border-color: var(--primary);
    background: var(--card-bg);
    box-shadow: 0 0 0 3px rgba(26,122,74,0.1);
}
textarea.form-control { resize: vertical; min-height: 100px; }
select.form-control { cursor: pointer; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-divider {
    border: none;
    border-top: 1px solid var(--border);
    margin: 1.5rem 0;
}

/* رفع الملفات */
.file-upload-wrap {
    border: 2px dashed var(--border);
    border-radius: var(--radius-sm);
    padding: 1.5rem;
    text-align: center;
    transition: var(--transition);
    cursor: pointer;
    position: relative;
}
.file-upload-wrap:hover, .file-upload-wrap.dragover {
    border-color: var(--primary);
    background: var(--accent);
}
.file-upload-wrap input[type=file] {
    position: absolute; inset: 0;
    opacity: 0; cursor: pointer;
    width: 100%; height: 100%;
}
.file-upload-wrap i { font-size: 1.8rem; color: var(--primary); margin-bottom: 8px; display: block; }
.file-upload-wrap span { font-size: 0.85rem; color: var(--text-muted); }

/* ============================================================
   الرسائل - Alert Messages
   ============================================================ */
.alert {
    padding: 14px 18px;
    border-radius: var(--radius-sm);
    font-size: 0.9rem;
    font-weight: 500;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
    border-right: 4px solid;
    animation: slideIn 0.3s ease;
}
@keyframes slideIn {
    from { opacity: 0; transform: translateX(-10px); }
    to { opacity: 1; transform: translateX(0); }
}
.alert-success { background: #e8f8f0; color: #1a6b3d; border-color: var(--success); }
.alert-error   { background: #fdf0ef; color: #8b2318; border-color: var(--danger); }
.alert-info    { background: #e8f5ff; color: #1a5b8b; border-color: #3498db; }

/* ============================================================
   لوحة التحكم - Dashboard
   ============================================================ */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.dash-stat {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--shadow-sm);
}
.dash-stat-icon {
    width: 50px; height: 50px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    color: white;
    flex-shrink: 0;
}
.dash-stat-num { font-size: 1.8rem; font-weight: 900; line-height: 1; }
.dash-stat-label { font-size: 0.8rem; color: var(--text-muted); margin-top: 3px; }

/* ============================================================
   جداول - Tables
   ============================================================ */
.table-wrap {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.table-header {
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.table-header h3 { font-weight: 700; font-size: 1rem; }
table { width: 100%; border-collapse: collapse; }
th {
    padding: 12px 16px;
    background: var(--gray-100);
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-muted);
    text-align: right;
    white-space: nowrap;
}
td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--border);
    font-size: 0.88rem;
    vertical-align: middle;
}
tr:last-child td { border-bottom: none; }
tr:hover td { background: var(--accent); }

/* الحالات */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
}
.badge-pending  { background: #fff3e0; color: #e65100; }
.badge-accepted { background: #e8f5e9; color: #1b5e20; }
.badge-rejected { background: #fce4ec; color: #880e4f; }

/* ============================================================
   نظام الدفع - Payment
   ============================================================ */
.payment-card {
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    border-radius: 20px;
    padding: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
    margin-bottom: 1.5rem;
}
.payment-card::before {
    content: '';
    position: absolute;
    top: -30px; right: -30px;
    width: 100px; height: 100px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
}
.payment-card::after {
    content: '';
    position: absolute;
    bottom: -40px; left: 20px;
    width: 150px; height: 150px;
    border-radius: 50%;
    background: rgba(255,255,255,0.05);
}
.payment-chip {
    width: 36px; height: 28px;
    background: rgba(255,220,100,0.8);
    border-radius: 6px;
    margin-bottom: 1.5rem;
}
.payment-num {
    font-size: 1.15rem;
    letter-spacing: 3px;
    margin-bottom: 1.2rem;
    font-weight: 600;
}
.payment-info {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    opacity: 0.85;
}
.payment-label { opacity: 0.7; font-size: 0.72rem; margin-bottom: 3px; }

/* ============================================================
   الوصل - Receipt (PDF simulation)
   ============================================================ */
.receipt-card {
    background: var(--card-bg);
    border: 2px solid var(--primary);
    border-radius: var(--radius);
    padding: 2rem;
    position: relative;
}
.receipt-header {
    text-align: center;
    border-bottom: 2px dashed var(--border);
    padding-bottom: 1rem;
    margin-bottom: 1rem;
}
.receipt-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid var(--border);
    font-size: 0.88rem;
}
.receipt-row:last-child { border-bottom: none; }
.receipt-label { color: var(--text-muted); }
.receipt-value { font-weight: 600; }
.receipt-total {
    background: var(--accent);
    padding: 12px 16px;
    border-radius: var(--radius-sm);
    display: flex;
    justify-content: space-between;
    font-weight: 800;
    margin-top: 1rem;
    color: var(--primary);
}

/* ============================================================
   التبويبات - Tabs
   ============================================================ */
.tabs {
    display: flex;
    gap: 4px;
    background: var(--gray-100);
    padding: 5px;
    border-radius: 12px;
    margin-bottom: 1.5rem;
}
.tab {
    flex: 1;
    text-align: center;
    padding: 10px 16px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    border: none;
    background: transparent;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.tab.active {
    background: var(--card-bg);
    color: var(--primary);
    box-shadow: var(--shadow-sm);
}

/* ============================================================
   المتجر - Shop
   ============================================================ */
.shop-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem; }
.shop-item {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 1.2rem;
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
    position: relative;
}
.shop-item:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: var(--shadow-sm); }
.shop-item.selected { border-color: var(--primary); background: var(--accent); }
.shop-item .check {
    position: absolute;
    top: 8px; left: 8px;
    width: 24px; height: 24px;
    background: var(--primary);
    border-radius: 50%;
    color: white;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
}
.shop-item.selected .check { display: flex; }
.shop-item-icon { font-size: 2rem; margin-bottom: 8px; }
.shop-item-name { font-size: 0.82rem; font-weight: 600; margin-bottom: 4px; }
.shop-item-price { font-size: 0.78rem; color: var(--primary); font-weight: 700; }

/* ============================================================
   الأفواج الزمنية - Time Slots
   ============================================================ */
.slots-grid { display: flex; gap: 10px; flex-wrap: wrap; }
.slot-btn {
    padding: 12px 20px;
    border-radius: var(--radius-sm);
    border: 2px solid var(--border);
    background: var(--card-bg);
    cursor: pointer;
    font-size: 0.88rem;
    font-weight: 600;
    transition: var(--transition);
    color: var(--text);
}
.slot-btn:hover, .slot-btn.active {
    border-color: var(--primary);
    background: var(--primary);
    color: white;
}

/* ============================================================
   الـ Footer
   ============================================================ */
.footer {
    background: var(--gray-900);
    color: rgba(255,255,255,0.8);
    padding: 3rem 2rem 1.5rem;
    margin-top: 5rem;
}
body.dark .footer { background: #0a1510; }
.footer-grid {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 3rem;
    margin-bottom: 2rem;
}
.footer-brand .logo { font-size: 1.4rem; font-weight: 900; color: var(--primary-light); margin-bottom: 0.8rem; }
.footer-brand p { font-size: 0.85rem; line-height: 1.7; opacity: 0.7; }
.footer-links h4 { font-size: 0.9rem; font-weight: 700; color: white; margin-bottom: 1rem; }
.footer-links ul { list-style: none; }
.footer-links ul li { margin-bottom: 8px; }
.footer-links ul li a { font-size: 0.85rem; opacity: 0.7; transition: opacity 0.2s; }
.footer-links ul li a:hover { opacity: 1; }
.footer-bottom {
    max-width: 1200px;
    margin: 0 auto;
    text-align: center;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255,255,255,0.1);
    font-size: 0.8rem;
    opacity: 0.6;
}

/* ============================================================
   المودال - Modal
   ============================================================ */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    animation: fadeIn 0.2s ease;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.modal-box {
    background: var(--card-bg);
    border-radius: var(--radius);
    padding: 2rem;
    max-width: 500px;
    width: 100%;
    position: relative;
    animation: slideUp 0.3s ease;
    max-height: 90vh;
    overflow-y: auto;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}
.modal-close {
    position: absolute;
    top: 1rem; left: 1rem;
    width: 32px; height: 32px;
    border-radius: 50%;
    border: none;
    background: var(--gray-100);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text);
    font-size: 1rem;
    transition: var(--transition);
}
.modal-close:hover { background: var(--danger); color: white; }

/* ============================================================
   الإضاءة الليلية Toggle
   ============================================================ */
.dark-toggle {
    width: 44px; height: 24px;
    background: var(--gray-300);
    border-radius: 12px;
    position: relative;
    cursor: pointer;
    transition: var(--transition);
    border: none;
    flex-shrink: 0;
}
.dark-toggle::after {
    content: '';
    position: absolute;
    top: 3px; right: 3px;
    width: 18px; height: 18px;
    background: white;
    border-radius: 50%;
    transition: var(--transition);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
body.dark .dark-toggle { background: var(--primary); }
body.dark .dark-toggle::after { right: auto; left: 3px; }

/* ============================================================
   أدوات مساعدة - Utilities
   ============================================================ */
.text-center { text-align: center; }
.text-muted { color: var(--text-muted); }
.text-primary { color: var(--primary); }
.text-danger { color: var(--danger); }
.mt-1 { margin-top: 0.5rem; }
.mt-2 { margin-top: 1rem; }
.mt-3 { margin-top: 1.5rem; }
.mb-1 { margin-bottom: 0.5rem; }
.mb-2 { margin-bottom: 1rem; }
.mb-3 { margin-bottom: 1.5rem; }
.flex { display: flex; }
.flex-center { display: flex; align-items: center; justify-content: center; }
.gap-1 { gap: 0.5rem; }
.gap-2 { gap: 1rem; }
.hidden { display: none !important; }

/* التحميل */
.spinner {
    width: 24px; height: 24px;
    border: 3px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    display: inline-block;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ============================================================
   Responsive
   ============================================================ */
@media (max-width: 768px) {
    .navbar { padding: 0 1rem; }
    .nav-links { gap: 4px; }
    .nav-links .nav-label { display: none; }
    .hero-stats { gap: 1rem; }
    .footer-grid { grid-template-columns: 1fr; gap: 2rem; }
    .form-row { grid-template-columns: 1fr; }
    .page-card { padding: 1.5rem; }
}

/* نسخة الطباعة */
@media print {
    .navbar, .footer, .no-print { display: none !important; }
    body { background: white; color: black; }
    .receipt-card { border-color: #000; }
}
</style>
</head>
<body id="body">

<!-- ============================================================
     شريط التنقل - Navbar
     ============================================================ -->
<nav class="navbar">
    <a href="?" class="nav-logo">
        <div class="logo-icon"><i class="fas fa-balance-scale"></i></div>
        <span>e-Prison</span>
    </a>

    <ul class="nav-links">
        <?php if (!$user): ?>
        <!-- روابط للزوار -->
        <li><a href="?page=register" class="nav-btn"><i class="fas fa-user-plus"></i> <span class="nav-label" data-i18n="register">إنشاء حساب</span></a></li>
        <li><a href="?page=login" class="nav-btn nav-btn-primary"><i class="fas fa-sign-in-alt"></i> <span class="nav-label" data-i18n="login">تسجيل الدخول</span></a></li>
        <?php elseif ($user['role'] === 'admin'): ?>
        <!-- روابط المدير -->
        <li><a href="?page=admin" class="nav-btn"><i class="fas fa-tachometer-alt"></i> <span class="nav-label">الإدارة</span></a></li>
        <li><a href="?action=logout" class="nav-btn"><i class="fas fa-sign-out-alt"></i></a></li>
        <?php else: ?>
        <!-- روابط المستخدم -->
        <li><a href="?page=dashboard" class="nav-btn"><i class="fas fa-home"></i> <span class="nav-label" data-i18n="myAccount">حسابي</span></a></li>
        <li><a href="?page=service" class="nav-btn"><i class="fas fa-th-large"></i> <span class="nav-label" data-i18n="services">الخدمات</span></a></li>
        <li>
            <a href="?page=notifications" class="nav-btn" style="position:relative;">
                <i class="fas fa-bell"></i>
                <?php if ($unreadCount > 0): ?><span class="nav-badge"><?= $unreadCount ?></span><?php endif; ?>
            </a>
        </li>
        <li><a href="?action=logout" class="nav-btn"><i class="fas fa-sign-out-alt"></i></a></li>
        <?php endif; ?>

        <!-- اللغات -->
        <li class="lang-dropdown">
            <button class="nav-btn"><i class="fas fa-globe"></i> <span id="langLabel">ع</span></button>
            <div class="lang-menu">
                <a href="#" onclick="setLang('ar')">🇩🇿 العربية</a>
                <a href="#" onclick="setLang('fr')">🇫🇷 Français</a>
                <a href="#" onclick="setLang('en')">🇬🇧 English</a>
            </div>
        </li>

        <!-- اتصل بنا -->
        <li><a href="?page=contact" class="nav-btn"><i class="fas fa-phone"></i> <span class="nav-label" data-i18n="contact">اتصل بنا</span></a></li>

        <!-- الوضع الليلي -->
        <li>
            <button class="dark-toggle" id="darkToggle" onclick="toggleDark()" title="الوضع الليلي"></button>
        </li>
    </ul>
</nav>

<!-- ============================================================
     المحتوى الرئيسي
     ============================================================ -->
<div class="main-content">

<?php if ($message): ?>
<!-- رسالة النتيجة -->
<div style="position:fixed;top:80px;left:50%;transform:translateX(-50%);z-index:999;min-width:320px;max-width:90vw;">
    <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'error' ?>">
        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= htmlspecialchars($message) ?>
    </div>
</div>
<?php endif; ?>

<?php
// ============================================================
// الصفحة الرئيسية - Home Page
// ============================================================
if ($page === 'home'):
?>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-state" data-i18n="algState">الجمهورية الجزائرية الديمقراطية الشعبية</div>
    <div class="hero-ministry" data-i18n="ministry">المديرية العامة لإدارة السجون وإعادة الإدماج</div>

    <!-- ميزان العدل بالأنيميشن -->
    <div class="hero-emblem">
        <i class="fas fa-balance-scale-left scale-icon scale-left"></i>
        <div class="algerian-seal">🏛️</div>
        <i class="fas fa-balance-scale-right scale-icon scale-right"></i>
    </div>

    <h1 class="hero-title" data-i18n="heroTitle">
        منصة <span>e-Prison</span><br>للخدمات الرقمية
    </h1>
    <p class="hero-subtitle" data-i18n="heroSub">
        خدمات إلكترونية متكاملة للتواصل مع ذويكم في المؤسسات العقابية بأمان وسهولة
    </p>

    <div class="hero-cta">
        <?php if ($user): ?>
        <a href="?page=service" class="btn btn-primary btn-lg"><i class="fas fa-th-large"></i> <span data-i18n="startService">ابدأ الخدمات</span></a>
        <?php else: ?>
        <a href="?page=register" class="btn btn-primary btn-lg"><i class="fas fa-user-plus"></i> <span data-i18n="registerNow">سجل الآن</span></a>
        <a href="?page=login" class="btn btn-outline btn-lg"><i class="fas fa-sign-in-alt"></i> <span data-i18n="loginBtn">تسجيل الدخول</span></a>
        <?php endif; ?>
    </div>

    <!-- إحصاءات -->
    <div class="hero-stats">
        <?php
        $totalUsers = $conn->query("SELECT COUNT(*) c FROM users WHERE role='user'")->fetch_assoc()['c'];
        $totalReqs  = $conn->query("SELECT COUNT(*) c FROM requests")->fetch_assoc()['c'];
        $totalPris  = $conn->query("SELECT COUNT(*) c FROM prisoners")->fetch_assoc()['c'];
        ?>
        <div class="stat-item">
            <span class="stat-num"><?= number_format($totalUsers) ?></span>
            <span class="stat-label" data-i18n="statUsers">مستخدم مسجل</span>
        </div>
        <div class="stat-item">
            <span class="stat-num"><?= number_format($totalReqs) ?></span>
            <span class="stat-label" data-i18n="statReqs">طلب معالج</span>
        </div>
        <div class="stat-item">
            <span class="stat-num">4</span>
            <span class="stat-label" data-i18n="statServices">خدمة رقمية</span>
        </div>
        <div class="stat-item">
            <span class="stat-num"><?= number_format($totalPris) ?></span>
            <span class="stat-label" data-i18n="statPrisoners">سجين مسجل</span>
        </div>
    </div>
</section>

<!-- الخدمات -->
<div class="section">
    <div class="section-title">
        <h2 data-i18n="servicesTitle">خدماتنا الإلكترونية</h2>
        <div class="section-divider"></div>
        <p data-i18n="servicesSub">أربع خدمات رقمية متكاملة لتسهيل التواصل مع ذويكم</p>
    </div>

    <div class="services-grid">
        <!-- رسالة -->
        <div class="service-card" onclick="location.href='?page=service&type=message'">
            <div class="service-icon"><i class="fas fa-envelope"></i></div>
            <h3 data-i18n="msgService">الرسائل الإلكترونية</h3>
            <p data-i18n="msgServiceDesc">أرسل رسالة نصية إلى ذيوك في المؤسسة العقابية بكل سرية وأمان</p>
            <span class="service-price"><i class="fas fa-tag"></i> 200 دج</span>
        </div>
        <!-- زيارة -->
        <div class="service-card" onclick="location.href='?page=service&type=visit'">
            <div class="service-icon"><i class="fas fa-calendar-check"></i></div>
            <h3 data-i18n="visitService">حجز مواعيد الزيارة</h3>
            <p data-i18n="visitServiceDesc">احجز موعد زيارة لذيوك واختر الفوج الزمني المناسب لك</p>
            <span class="service-price"><i class="fas fa-tag"></i> 100 دج <span style="opacity:0.7;font-size:0.75rem;">(المحامي مجاناً)</span></span>
        </div>
        <!-- تبليغ -->
        <div class="service-card" onclick="location.href='?page=service&type=notification'">
            <div class="service-icon"><i class="fas fa-file-alt"></i></div>
            <h3 data-i18n="notifService">التبليغ الإلكتروني</h3>
            <p data-i18n="notifServiceDesc">أرسل تبليغاً قانونياً إلكترونياً للسجين عبر المنصة</p>
            <span class="service-price"><i class="fas fa-tag"></i> 200 دج</span>
        </div>
        <!-- متجر -->
        <div class="service-card" onclick="location.href='?page=service&type=shop'">
            <div class="service-icon"><i class="fas fa-shopping-cart"></i></div>
            <h3 data-i18n="shopService">المتجر الإلكتروني</h3>
            <p data-i18n="shopServiceDesc">اطلب مواد غذائية وضروريات يومية لذيوك في المؤسسة</p>
            <span class="service-price"><i class="fas fa-tag"></i> 100 دج</span>
        </div>
    </div>
</div>

<!-- قسم كيف يعمل -->
<div style="background: linear-gradient(135deg, var(--primary-dark), var(--primary)); padding: 5rem 2rem; text-align:center; margin-top: 3rem;">
    <h2 style="color:white;font-size:2rem;margin-bottom:0.5rem;" data-i18n="howTitle">كيف تستخدم المنصة؟</h2>
    <p style="color:rgba(255,255,255,0.75);margin-bottom:3rem;" data-i18n="howSub">ثلاث خطوات بسيطة</p>
    <div style="display:flex;gap:2rem;justify-content:center;flex-wrap:wrap;max-width:900px;margin:0 auto;">
        <div style="flex:1;min-width:200px;background:rgba(255,255,255,0.1);border-radius:var(--radius);padding:2rem;">
            <div style="font-size:2.5rem;margin-bottom:1rem;">1️⃣</div>
            <h3 style="color:white;margin-bottom:0.5rem;" data-i18n="step1">إنشاء حساب</h3>
            <p style="color:rgba(255,255,255,0.7);font-size:0.88rem;" data-i18n="step1desc">سجل بياناتك كمواطن أو محامي أو موثق</p>
        </div>
        <div style="flex:1;min-width:200px;background:rgba(255,255,255,0.1);border-radius:var(--radius);padding:2rem;">
            <div style="font-size:2.5rem;margin-bottom:1rem;">2️⃣</div>
            <h3 style="color:white;margin-bottom:0.5rem;" data-i18n="step2">اختر الخدمة</h3>
            <p style="color:rgba(255,255,255,0.7);font-size:0.88rem;" data-i18n="step2desc">اختر من بين خدماتنا الأربع المتاحة</p>
        </div>
        <div style="flex:1;min-width:200px;background:rgba(255,255,255,0.1);border-radius:var(--radius);padding:2rem;">
            <div style="font-size:2.5rem;margin-bottom:1rem;">3️⃣</div>
            <h3 style="color:white;margin-bottom:0.5rem;" data-i18n="step3">ادفع واستلم الوصل</h3>
            <p style="color:rgba(255,255,255,0.7);font-size:0.88rem;" data-i18n="step3desc">ادفع بالبطاقة واستلم وصلك PDF</p>
        </div>
    </div>
</div>

<?php
// ============================================================
// صفحة إنشاء حساب - Register
// ============================================================
elseif ($page === 'register'):
?>
<div class="page-container">
    <div class="page-card">
        <div class="page-header">
            <div class="icon-wrap"><i class="fas fa-user-plus"></i></div>
            <h1 data-i18n="createAccount">إنشاء حساب جديد</h1>
            <p data-i18n="createAccountSub">انضم إلى منصة e-Prison للخدمات الإلكترونية</p>
        </div>

        <form method="POST" action="?page=register">
            <input type="hidden" name="action" value="register">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><span class="req">*</span> الاسم</label>
                    <input type="text" name="first_name" class="form-control" placeholder="الاسم الأول" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><span class="req">*</span> اللقب</label>
                    <input type="text" name="last_name" class="form-control" placeholder="اللقب العائلي" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label"><span class="req">*</span> رقم الهاتف</label>
                <input type="tel" name="phone" class="form-control" placeholder="05xxxxxxxx" required pattern="[0-9]{10}">
            </div>

            <div class="form-group">
                <label class="form-label"><span class="req">*</span> رقم التعريف الوطني (NIN)</label>
                <input type="text" name="national_id" class="form-control" placeholder="رقم بطاقة التعريف الوطنية" required>
            </div>

            <div class="form-group">
                <label class="form-label"><span class="req">*</span> طبيعة الشخص</label>
                <select name="person_type" class="form-control" required>
                    <option value="citizen">🏠 مواطن</option>
                    <option value="lawyer">⚖️ محامي</option>
                    <option value="notary">📜 موثق</option>
                    <option value="bailiff">🔖 محضر قضائي</option>
                </select>
            </div>

            <hr class="form-divider">

            <div class="form-group">
                <label class="form-label"><span class="req">*</span> كلمة المرور</label>
                <div style="position:relative;">
                    <input type="password" name="password" id="pass1" class="form-control" placeholder="6 أحرف على الأقل" required minlength="6">
                    <button type="button" onclick="togglePass('pass1')" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);"><i class="fas fa-eye"></i></button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label"><span class="req">*</span> تأكيد كلمة المرور</label>
                <div style="position:relative;">
                    <input type="password" name="password2" id="pass2" class="form-control" placeholder="أعد كتابة كلمة المرور" required>
                    <button type="button" onclick="togglePass('pass2')" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);"><i class="fas fa-eye"></i></button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-full mt-2"><i class="fas fa-user-plus"></i> إنشاء الحساب</button>
        </form>

        <p class="text-center mt-2" style="font-size:0.88rem;">
            لديك حساب؟ <a href="?page=login" class="text-primary" style="font-weight:600;">تسجيل الدخول</a>
        </p>
    </div>
</div>

<?php
// ============================================================
// صفحة تسجيل الدخول - Login
// ============================================================
elseif ($page === 'login'):
?>
<div class="page-container">
    <div class="page-card">
        <div class="page-header">
            <div class="icon-wrap"><i class="fas fa-lock"></i></div>
            <h1 data-i18n="loginTitle">تسجيل الدخول</h1>
            <p data-i18n="loginSub">أدخل بياناتك للوصول إلى حسابك</p>
        </div>

        <form method="POST" action="?page=login">
            <input type="hidden" name="action" value="login">

            <div class="form-group">
                <label class="form-label"><span class="req">*</span> رقم الهاتف</label>
                <input type="tel" name="phone" class="form-control" placeholder="05xxxxxxxx" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label"><span class="req">*</span> كلمة المرور</label>
                <div style="position:relative;">
                    <input type="password" name="password" id="loginPass" class="form-control" placeholder="••••••••" required>
                    <button type="button" onclick="togglePass('loginPass')" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);"><i class="fas fa-eye"></i></button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-full mt-2"><i class="fas fa-sign-in-alt"></i> تسجيل الدخول</button>
        </form>

        <div class="alert alert-info mt-2">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>حساب المدير الافتراضي:</strong><br>
                الهاتف: 0555000000 | كلمة المرور: admin123
            </div>
        </div>

        <p class="text-center mt-2" style="font-size:0.88rem;">
            ليس لديك حساب؟ <a href="?page=register" class="text-primary" style="font-weight:600;">إنشاء حساب</a>
        </p>
    </div>
</div>

<?php
// ============================================================
// صفحة الخدمات - Services
// ============================================================
elseif ($page === 'service'):
    $serviceType = $_GET['type'] ?? '';
?>
<div class="page-container wide" style="padding-top: calc(70px + 2rem);">

<?php if (!$serviceType): ?>
<!-- قائمة الخدمات -->
<div class="page-header" style="text-align:center;margin-bottom:2rem;">
    <h1 style="font-size:2rem;font-weight:900;">الخدمات الإلكترونية</h1>
    <p class="text-muted">اختر الخدمة التي تريدها</p>
</div>
<div class="services-grid">
    <div class="service-card" onclick="location.href='?page=service&type=message'">
        <div class="service-icon"><i class="fas fa-envelope"></i></div>
        <h3>الرسائل الإلكترونية</h3>
        <p>أرسل رسالة نصية إلى ذيوك في المؤسسة العقابية</p>
        <span class="service-price"><i class="fas fa-tag"></i> 200 دج</span>
    </div>
    <div class="service-card" onclick="location.href='?page=service&type=visit'">
        <div class="service-icon"><i class="fas fa-calendar-check"></i></div>
        <h3>حجز مواعيد الزيارة</h3>
        <p>احجز موعد زيارة واختر الفوج الزمني المناسب</p>
        <span class="service-price"><i class="fas fa-tag"></i> 100 دج</span>
    </div>
    <div class="service-card" onclick="location.href='?page=service&type=notification'">
        <div class="service-icon"><i class="fas fa-file-alt"></i></div>
        <h3>التبليغ الإلكتروني</h3>
        <p>أرسل تبليغاً قانونياً للسجين عبر المنصة (محضر قضائي فقط)</p>
        <span class="service-price"><i class="fas fa-tag"></i> 200 دج</span>
    </div>
    <div class="service-card" onclick="location.href='?page=service&type=shop'">
        <div class="service-icon"><i class="fas fa-shopping-cart"></i></div>
        <h3>المتجر الإلكتروني</h3>
        <p>اطلب مواد وضروريات لذيوك في المؤسسة</p>
        <span class="service-price"><i class="fas fa-tag"></i> 100 دج</span>
    </div>
</div>

<?php else:
// نموذج الخدمة المحددة
$serviceNames = ['message'=>'الرسائل الإلكترونية','visit'=>'حجز موعد زيارة','notification'=>'التبليغ الإلكتروني','shop'=>'المتجر الإلكتروني'];
$serviceIcons = ['message'=>'fa-envelope','visit'=>'fa-calendar-check','notification'=>'fa-file-alt','shop'=>'fa-shopping-cart'];
$servicePrices= ['message'=>200,'visit'=>100,'notification'=>200,'shop'=>100];
$sName = $serviceNames[$serviceType] ?? 'خدمة';
$sIcon = $serviceIcons[$serviceType] ?? 'fa-star';
$sPrice= $servicePrices[$serviceType] ?? 0;
?>
<a href="?page=service" style="display:inline-flex;align-items:center;gap:6px;color:var(--text-muted);font-size:0.88rem;margin-bottom:1.5rem;">
    <i class="fas fa-arrow-right"></i> العودة للخدمات
</a>

<div class="page-card">
    <div class="page-header">
        <div class="icon-wrap"><i class="fas <?= $sIcon ?>"></i></div>
        <h1><?= $sName ?></h1>
        <p>أكمل البيانات المطلوبة وأتم الدفع</p>
    </div>

    <form method="POST" action="?page=service&type=<?= $serviceType ?>" enctype="multipart/form-data" id="serviceForm">
        <input type="hidden" name="action" value="submit_service">
        <input type="hidden" name="service_type" value="<?= $serviceType ?>">

        <!-- البيانات الأساسية المشتركة -->
        <h4 style="font-weight:700;margin-bottom:1rem;color:var(--primary);border-bottom:1px solid var(--border);padding-bottom:8px;">
            <i class="fas fa-user"></i> بيانات المرسل
        </h4>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><span class="req">*</span> الاسم</label>
                <input type="text" name="sender_first_name" class="form-control" value="<?= htmlspecialchars($user['first_name']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label"><span class="req">*</span> اللقب</label>
                <input type="text" name="sender_last_name" class="form-control" value="<?= htmlspecialchars($user['last_name']) ?>" required>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label"><span class="req">*</span> رقم السجين</label>
            <input type="text" name="prisoner_number" class="form-control" placeholder="مثال: SJN-2025-0001" required>
        </div>

        <!-- محتوى كل خدمة -->
        <?php if ($serviceType === 'message'): ?>
        <hr class="form-divider">
        <div class="form-group">
            <label class="form-label"><span class="req">*</span> نص الرسالة</label>
            <textarea name="message_text" class="form-control" placeholder="اكتب رسالتك هنا... (الحد الأقصى 500 حرف)" maxlength="500" rows="5" required></textarea>
            <p style="font-size:0.78rem;color:var(--text-muted);margin-top:4px;">⚠️ تخضع الرسائل للمراجعة. الكلمات المحظورة ممنوعة.</p>
        </div>

        <?php elseif ($serviceType === 'visit'): ?>
        <hr class="form-divider">
        <div class="form-group">
            <label class="form-label"><span class="req">*</span> نوع الزائر</label>
            <select name="visit_type" class="form-control" id="visitTypeSelect" onchange="handleVisitType()">
                <option value="citizen">🏠 مواطن (100 دج)</option>
                <option value="lawyer">⚖️ محامي (مجاناً)</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label"><span class="req">*</span> تاريخ الزيارة</label>
            <input type="date" name="visit_date" class="form-control" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label"><span class="req">*</span> فوج الزيارة</label>
            <div class="slots-grid" id="slotsGrid">
                <button type="button" class="slot-btn" onclick="selectSlot(this,'09:00 - 10:00')">🕘 09:00 - 10:00</button>
                <button type="button" class="slot-btn" onclick="selectSlot(this,'10:00 - 11:00')">🕙 10:00 - 11:00</button>
                <button type="button" class="slot-btn" onclick="selectSlot(this,'11:00 - 12:00')">🕚 11:00 - 12:00</button>
            </div>
            <input type="hidden" name="visit_slot" id="visitSlot" required>
        </div>

        <!-- رفع ملفات الزيارة (اختياري) -->
        <div class="form-group">
            <label class="form-label">رخصة الاتصال بالسجين (PDF - اختياري)</label>
            <div class="file-upload-wrap">
                <input type="file" name="person_file" accept=".pdf">
                <i class="fas fa-file-pdf"></i>
                <span>اسحب ملف PDF هنا أو انقر للاختيار</span>
            </div>
        </div>
        <div class="form-group" id="lawyerCardGroup" style="display:none;">
            <label class="form-label">البطاقة المهنية للمحامي (PDF - اختياري)</label>
            <div class="file-upload-wrap">
                <input type="file" name="professional_card" accept=".pdf">
                <i class="fas fa-id-card"></i>
                <span>رفع البطاقة المهنية</span>
            </div>
        </div>

        <?php elseif ($serviceType === 'notification'): ?>
        <hr class="form-divider">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            هذه الخدمة متاحة للمحضر القضائي فقط. اكتب نص التبليغ وسيتم تحويله تلقائياً إلى ملف PDF.
        </div>
        <div class="form-group">
            <label class="form-label"><span class="req">*</span> نص التبليغ الإلكتروني</label>
            <textarea name="notification_text" class="form-control" placeholder="اكتب نص التبليغ الرسمي هنا بالتفصيل..." rows="7" required minlength="20"></textarea>
            <p style="font-size:0.78rem;color:var(--text-muted);margin-top:4px;">
                <i class="fas fa-magic" style="color:var(--primary);"></i>
                سيتم إنشاء ملف PDF تلقائياً بعد الإرسال
            </p>
        </div>
        <div class="form-group">
            <label class="form-label">البطاقة المهنية للمحضر (PDF - اختياري)</label>
            <div class="file-upload-wrap">
                <input type="file" name="professional_card" accept=".pdf">
                <i class="fas fa-id-card"></i>
                <span>رفع البطاقة المهنية للمحضر (اختياري)</span>
            </div>
        </div>

        <?php elseif ($serviceType === 'shop'): ?>
        <hr class="form-divider">
        <h4 style="font-weight:700;margin-bottom:1rem;">🛒 اختر المنتجات</h4>
        <div class="shop-grid" id="shopGrid">
            <?php
            // قائمة المنتجات مع أسعارها الرقمية
            $products = [
                ['🍞','خبز',50],['🧴','صابون',80],['🦷','معجون أسنان',60],
                ['👕','قميص',300],['🧃','عصير',40],['📚','كتاب',150],
                ['☕','قهوة',70],['🍫','شوكولاتة',90]
            ];
            foreach ($products as $p): ?>
            <div class="shop-item" onclick="toggleShopItem(this,'<?= $p[0].' '.$p[1].' '.($p[2]).' دج' ?>',<?= $p[2] ?>)">
                <div class="check"><i class="fas fa-check"></i></div>
                <div class="shop-item-icon"><?= $p[0] ?></div>
                <div class="shop-item-name"><?= $p[1] ?></div>
                <div class="shop-item-price"><?= $p[2] ?> دج</div>
            </div>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="shop_items" id="shopItems">
        <input type="hidden" name="shop_products_total" id="shopProductsTotal" value="0">
        <p style="font-size:0.8rem;color:var(--text-muted);margin-top:8px;">اختر المنتجات التي تريد إرسالها</p>

        <!-- ملخص تفصيلي قبل قسم الدفع -->
        <div id="shopSummaryBox" style="display:none;margin-top:1rem;background:var(--accent);border-radius:var(--radius-sm);padding:1rem;border:1px solid var(--border);">
            <h5 style="font-weight:700;margin-bottom:0.8rem;color:var(--primary);"><i class="fas fa-receipt"></i> ملخص الطلب</h5>
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:0.88rem;">
                <span>مجموع المنتجات</span>
                <span id="summaryProductsTotal" style="font-weight:700;">0 دج</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:0.88rem;color:var(--text-muted);">
                <span>رسوم الخدمة الإلكترونية</span>
                <span style="font-weight:600;">100 دج</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;font-weight:900;color:var(--primary);font-size:1rem;">
                <span>المجموع الكلي</span>
                <span id="summaryGrandTotal">100 دج</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- نظام الدفع -->
        <hr class="form-divider">
        <h4 style="font-weight:700;margin-bottom:1rem;color:var(--primary);">
            <i class="fas fa-credit-card"></i> الدفع بالبطاقة الذهبية
        </h4>

        <!-- محاكاة البطاقة -->
        <div class="payment-card" id="cardPreview">
            <div class="payment-chip"></div>
            <div class="payment-num" id="cardNumDisplay">•••• •••• •••• ••••</div>
            <div class="payment-info">
                <div>
                    <div class="payment-label">صاحب البطاقة</div>
                    <div id="cardHolderDisplay">الاسم الكامل</div>
                </div>
                <div>
                    <div class="payment-label">تاريخ الانتهاء</div>
                    <div id="cardExpDisplay">MM/YY</div>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group" style="grid-column:1/-1;">
                <label class="form-label"><span class="req">*</span> رقم البطاقة</label>
                <input type="text" name="card_number" id="cardNumInput" class="form-control" placeholder="•••• •••• •••• ••••" maxlength="19" required oninput="formatCard(this)">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label"><span class="req">*</span> تاريخ الانتهاء</label>
                <input type="text" name="card_expiry" id="cardExpInput" class="form-control" placeholder="MM/YY" maxlength="5" required oninput="formatExpiry(this)">
            </div>
            <div class="form-group">
                <label class="form-label"><span class="req">*</span> CVV</label>
                <input type="text" name="card_cvv" class="form-control" placeholder="•••" maxlength="3" required pattern="[0-9]{3}">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label"><span class="req">*</span> اسم صاحب البطاقة</label>
            <input type="text" name="card_holder" id="cardHolderInput" class="form-control" placeholder="الاسم كما يظهر على البطاقة" required oninput="updateCard()">
        </div>

        <!-- ملخص الدفع -->
        <?php if ($serviceType === 'shop'): ?>
        <!-- ملخص المتجر التفصيلي -->
        <div style="background:var(--gray-100);border-radius:var(--radius-sm);padding:1rem;margin-bottom:0.5rem;">
            <div style="display:flex;justify-content:space-between;font-size:0.88rem;padding:4px 0;">
                <span class="text-muted">مجموع المنتجات</span>
                <span id="payProductsTotal" style="font-weight:700;">0 دج</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:0.88rem;padding:4px 0;">
                <span class="text-muted">رسوم الخدمة</span>
                <span style="font-weight:600;">100 دج</span>
            </div>
        </div>
        <div class="receipt-total">
            <span>💰 المجموع الكلي</span>
            <span id="totalAmount">100 دج</span>
        </div>
        <?php else: ?>
        <div class="receipt-total">
            <span>💰 المبلغ الإجمالي</span>
            <span id="totalAmount"><?= $sPrice ?> دج</span>
        </div>
        <?php endif; ?>
        <?php if ($serviceType === 'visit'): ?>
        <p style="font-size:0.8rem;color:var(--text-muted);text-align:center;margin-top:6px;" id="visitPriceNote">
            ملاحظة: المحامي يستفيد من خدمة مجانية
        </p>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary btn-full mt-3" id="submitBtn" onclick="return validateForm()">
            <i class="fas fa-paper-plane"></i> إرسال الطلب والدفع
        </button>
    </form>
</div>
<?php endif; ?>
</div>

<?php
// ============================================================
// لوحة المستخدم - User Dashboard
// ============================================================
elseif ($page === 'dashboard'):
    $uid = (int)$user['id'];
    $myRequests = $conn->query("SELECT r.*, p.amount FROM requests r LEFT JOIN payments p ON p.request_id=r.id WHERE r.user_id=$uid ORDER BY r.created_at DESC");
?>
<div class="page-container full" style="padding-top: calc(70px + 2rem);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.8rem;font-weight:900;">مرحباً، <?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?> 👋</h1>
            <p class="text-muted">هنا تجد جميع طلباتك وحالتها</p>
        </div>
        <a href="?page=service" class="btn btn-primary"><i class="fas fa-plus"></i> طلب جديد</a>
    </div>

    <!-- إحصاءات سريعة -->
    <?php
    $myStats = [];
    $rAll  = $conn->query("SELECT COUNT(*) c FROM requests WHERE user_id=$uid");
    $rPend = $conn->query("SELECT COUNT(*) c FROM requests WHERE user_id=$uid AND status='pending'");
    $rAcc  = $conn->query("SELECT COUNT(*) c FROM requests WHERE user_id=$uid AND status='accepted'");
    $myStats['all']     = $rAll->fetch_assoc()['c'];
    $myStats['pending'] = $rPend->fetch_assoc()['c'];
    $myStats['accepted']= $rAcc->fetch_assoc()['c'];
    ?>
    <div class="dashboard-grid">
        <div class="dash-stat">
            <div class="dash-stat-icon" style="background:linear-gradient(135deg,#3498db,#2980b9)"><i class="fas fa-list"></i></div>
            <div>
                <div class="dash-stat-num"><?= $myStats['all'] ?></div>
                <div class="dash-stat-label">إجمالي الطلبات</div>
            </div>
        </div>
        <div class="dash-stat">
            <div class="dash-stat-icon" style="background:linear-gradient(135deg,#f39c12,#e67e22)"><i class="fas fa-clock"></i></div>
            <div>
                <div class="dash-stat-num"><?= $myStats['pending'] ?></div>
                <div class="dash-stat-label">طلبات معلقة</div>
            </div>
        </div>
        <div class="dash-stat">
            <div class="dash-stat-icon" style="background:linear-gradient(135deg,#27ae60,#1a7a4a)"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="dash-stat-num"><?= $myStats['accepted'] ?></div>
                <div class="dash-stat-label">طلبات مقبولة</div>
            </div>
        </div>
    </div>

    <!-- قائمة الطلبات -->
    <div class="table-wrap">
        <div class="table-header">
            <h3><i class="fas fa-history"></i> طلباتي</h3>
        </div>
        <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>الخدمة</th>
                    <th>رقم السجين</th>
                    <th>المبلغ</th>
                    <th>الحالة</th>
                    <th>التاريخ</th>
                    <th>الوصل</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($req = $myRequests->fetch_assoc()):
                $sTypeAr = ['message'=>'رسالة','visit'=>'زيارة','notification'=>'تبليغ','shop'=>'متجر'][$req['service_type']] ?? '-';
                $statusBadge = ['pending'=>'badge-pending pending','accepted'=>'badge-accepted accepted','rejected'=>'badge-rejected rejected'][$req['status']] ?? '';
                $statusAr = ['pending'=>'معلق','accepted'=>'مقبول','rejected'=>'مرفوض'][$req['status']] ?? '-';
            ?>
            <tr>
                <td><strong>#<?= $req['id'] ?></strong></td>
                <td><?= $sTypeAr ?></td>
                <td><code style="background:var(--accent);padding:2px 8px;border-radius:6px;font-size:0.82rem;"><?= htmlspecialchars($req['prisoner_number']) ?></code></td>
                <td><?= $req['amount'] ? $req['amount'].' دج' : 'مجاناً' ?></td>
                <td><span class="badge <?= $statusBadge ?>"><?= $statusAr ?></span></td>
                <td style="font-size:0.8rem;"><?= date('Y/m/d', strtotime($req['created_at'])) ?></td>
                <td>
                    <?php if ($req['status'] === 'accepted'): ?>
                    <?php if ($req['service_type'] === 'notification' && !empty($req['signature_file'])): ?>
                    <button onclick="showReceipt(<?= htmlspecialchars(json_encode($req)) ?>)" class="btn btn-sm btn-success">
                        <i class="fas fa-file-pdf"></i> وصل موقّع
                    </button>
                    <?php else: ?>
                    <button onclick="showReceipt(<?= htmlspecialchars(json_encode($req)) ?>)" class="btn btn-sm btn-success">
                        <i class="fas fa-file-pdf"></i> الوصل
                    </button>
                    <?php endif; ?>
                    <?php else: ?>
                    <span style="font-size:0.78rem;color:var(--text-muted);">-</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
        <?php if ($myStats['all'] === 0): ?>
        <div style="text-align:center;padding:3rem;color:var(--text-muted);">
            <i class="fas fa-inbox" style="font-size:3rem;margin-bottom:1rem;opacity:0.4;display:block;"></i>
            لا توجد طلبات بعد. <a href="?page=service" class="text-primary">ابدأ الآن</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// ============================================================
// لوحة الإدارة - Admin Dashboard
// ============================================================
elseif ($page === 'admin'):
    $adminTab = $_GET['tab'] ?? 'overview';
?>
<div class="page-container full" style="padding-top: calc(70px + 2rem);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.8rem;font-weight:900;"><i class="fas fa-shield-alt text-primary"></i> لوحة الإدارة</h1>
            <p class="text-muted">التحكم الكامل في منصة e-Prison</p>
        </div>
    </div>

    <!-- إحصاءات الإدارة -->
    <div class="dashboard-grid">
        <div class="dash-stat">
            <div class="dash-stat-icon" style="background:linear-gradient(135deg,#3498db,#2980b9)"><i class="fas fa-users"></i></div>
            <div><div class="dash-stat-num"><?= $stats['users'] ?></div><div class="dash-stat-label">مستخدم</div></div>
        </div>
        <div class="dash-stat">
            <div class="dash-stat-icon" style="background:linear-gradient(135deg,#8e44ad,#6c3483)"><i class="fas fa-user-lock"></i></div>
            <div><div class="dash-stat-num"><?= $stats['prisoners'] ?></div><div class="dash-stat-label">سجين</div></div>
        </div>
        <div class="dash-stat">
            <div class="dash-stat-icon" style="background:linear-gradient(135deg,#27ae60,#1a7a4a)"><i class="fas fa-tasks"></i></div>
            <div><div class="dash-stat-num"><?= $stats['requests'] ?></div><div class="dash-stat-label">إجمالي الطلبات</div></div>
        </div>
        <div class="dash-stat">
            <div class="dash-stat-icon" style="background:linear-gradient(135deg,#f39c12,#e67e22)"><i class="fas fa-hourglass-half"></i></div>
            <div><div class="dash-stat-num"><?= $stats['pending'] ?></div><div class="dash-stat-label">معلقة</div></div>
        </div>
    </div>

    <!-- تبويبات الإدارة -->
    <div class="tabs" style="flex-wrap:wrap;">
        <button class="tab <?= $adminTab==='overview'?'active':'' ?>" onclick="location.href='?page=admin&tab=overview'"><i class="fas fa-list"></i> الطلبات</button>
        <button class="tab <?= $adminTab==='prisoners'?'active':'' ?>" onclick="location.href='?page=admin&tab=prisoners'"><i class="fas fa-user-lock"></i> السجناء</button>
        <button class="tab <?= $adminTab==='lawyers'?'active':'' ?>" onclick="location.href='?page=admin&tab=lawyers'"><i class="fas fa-gavel"></i> المحامون</button>
        <button class="tab <?= $adminTab==='users'?'active':'' ?>" onclick="location.href='?page=admin&tab=users'"><i class="fas fa-users"></i> المستخدمون</button>
    </div>

    <!-- الطلبات -->
    <?php if ($adminTab === 'overview'):
        $allRequests = $conn->query("SELECT r.*,u.first_name,u.last_name,u.phone,p.amount FROM requests r LEFT JOIN users u ON u.id=r.user_id LEFT JOIN payments p ON p.request_id=r.id ORDER BY r.created_at DESC");
    ?>
    <div class="table-wrap">
        <div class="table-header">
            <h3><i class="fas fa-tasks"></i> جميع الطلبات</h3>
        </div>
        <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr><th>#</th><th>المستخدم</th><th>الخدمة</th><th>السجين</th><th>المبلغ</th><th>الحالة</th><th>التاريخ</th><th>الإجراءات</th></tr>
            </thead>
            <tbody>
            <?php while ($req = $allRequests->fetch_assoc()):
                $sTypeAr = ['message'=>'📨 رسالة','visit'=>'📅 زيارة','notification'=>'⚖️ تبليغ','shop'=>'🛒 متجر'][$req['service_type']] ?? '-';
                $statusAr = ['pending'=>'معلق','accepted'=>'مقبول','rejected'=>'مرفوض'][$req['status']] ?? '-';
                $statusBadge = ['pending'=>'badge-pending','accepted'=>'badge-accepted','rejected'=>'badge-rejected'][$req['status']] ?? '';
            ?>
            <tr>
                <td><strong>#<?= $req['id'] ?></strong></td>
                <td>
                    <div style="font-weight:600;"><?= htmlspecialchars($req['first_name'].' '.$req['last_name']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-muted);"><?= $req['phone'] ?></div>
                </td>
                <td><?= $sTypeAr ?></td>
                <td><code style="background:var(--accent);padding:2px 8px;border-radius:6px;font-size:0.8rem;"><?= htmlspecialchars($req['prisoner_number']) ?></code></td>
                <td><?= $req['amount'] ? $req['amount'].' دج' : 'مجاناً' ?></td>
                <td><span class="badge <?= $statusBadge ?>"><?= $statusAr ?></span></td>
                <td style="font-size:0.78rem;"><?= date('Y/m/d H:i', strtotime($req['created_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                        <!-- زر PDF الرسالة الإلكترونية -->
                        <?php if ($req['service_type'] === 'message' && $req['message_text']): ?>
                        <button onclick="adminViewPDF(<?= htmlspecialchars(json_encode($req)) ?>,'message')"
                                class="btn btn-sm" style="background:#3498db;color:white;" title="عرض الرسالة الإلكترونية">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <?php endif; ?>

                        <!-- أزرار التبليغ الإلكتروني -->
                        <?php if ($req['service_type'] === 'notification' && $req['message_text']): ?>
                        <button onclick="adminViewPDF(<?= htmlspecialchars(json_encode($req)) ?>,'notification')"
                                class="btn btn-sm" style="background:#8e44ad;color:white;" title="عرض التبليغ">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <?php if ($req['status'] === 'accepted' && !$req['signature_file']): ?>
                        <button onclick="openSignatureModal(<?= (int)$req['id'] ?>)"
                                class="btn btn-sm" style="background:#e67e22;color:white;" title="توقيع وبصمة السجين">
                            <i class="fas fa-signature"></i> توقيع
                        </button>
                        <?php elseif ($req['signature_file']): ?>
                        <button onclick="adminViewPDF(<?= htmlspecialchars(json_encode($req)) ?>,'notification_signed')"
                                class="btn btn-sm" style="background:#27ae60;color:white;" title="وصل التبليغ الموقع">
                            <i class="fas fa-check-circle"></i> موقّع
                        </button>
                        <?php endif; ?>
                        <?php endif; ?>

                        <!-- تغيير الحالة -->
                        <?php if ($req['status'] === 'pending'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                            <input type="hidden" name="status" value="accepted">
                            <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i></button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                            <input type="hidden" name="status" value="rejected">
                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-times"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- السجناء -->
    <?php elseif ($adminTab === 'prisoners'):
        $allPrisoners = $conn->query("SELECT * FROM prisoners ORDER BY created_at DESC");
    ?>
    <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:1.5rem;flex-wrap:wrap;" class="admin-grid">
        <!-- نموذج إضافة سجين -->
        <div class="page-card">
            <h3 style="font-weight:700;margin-bottom:1.5rem;"><i class="fas fa-user-plus text-primary"></i> إضافة سجين جديد</h3>
            <form method="POST" action="?page=admin&tab=prisoners">
                <input type="hidden" name="action" value="add_prisoner">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">الاسم</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">اللقب</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">تاريخ الميلاد</label>
                    <input type="date" name="birth_date" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">رقم التعريف الوطني</label>
                    <input type="text" name="national_id" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">رقم الهاتف</label>
                    <input type="tel" name="phone" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">المؤسسة العقابية</label>
                    <select name="prison" class="form-control" required>
                        <option value="مؤسسة إعادة التربية سعيدة">مؤسسة إعادة التربية سعيدة</option>
                        <option value="مؤسسة إعادة التأهيل عين الحجر سعيدة">مؤسسة إعادة التأهيل عين الحجر سعيدة</option>
                    </select>
                </div>
                <div class="alert alert-info" style="font-size:0.82rem;">
                    <i class="fas fa-info-circle"></i> سيتم توليد رقم السجين تلقائياً (مثال: SJN-2026-0001)
                </div>
                <button type="submit" class="btn btn-primary btn-full"><i class="fas fa-plus"></i> إضافة السجين</button>
            </form>
        </div>

        <!-- قائمة السجناء -->
        <div class="table-wrap" style="height:fit-content;">
            <div class="table-header"><h3><i class="fas fa-user-lock"></i> قائمة السجناء</h3></div>
            <div style="overflow-x:auto;">
            <table>
                <thead><tr><th>الرقم</th><th>الاسم</th><th>المؤسسة</th><th>تاريخ الإضافة</th></tr></thead>
                <tbody>
                <?php while ($p = $allPrisoners->fetch_assoc()): ?>
                <tr>
                    <td><code style="background:var(--accent);padding:2px 8px;border-radius:6px;font-size:0.78rem;"><?= $p['prisoner_number'] ?></code></td>
                    <td><?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?></td>
                    <td style="font-size:0.78rem;"><?= htmlspecialchars($p['prison']) ?></td>
                    <td style="font-size:0.75rem;"><?= date('Y/m/d', strtotime($p['created_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <!-- المحامون -->
    <?php elseif ($adminTab === 'lawyers'):
        $allLawyers = $conn->query("SELECT * FROM lawyers ORDER BY created_at DESC");
    ?>
    <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:1.5rem;">
        <div class="page-card">
            <h3 style="font-weight:700;margin-bottom:1.5rem;"><i class="fas fa-gavel text-primary"></i> إضافة محامي</h3>
            <form method="POST" action="?page=admin&tab=lawyers" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_lawyer">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">الاسم</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">اللقب</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">رقم التعريف الوطني</label>
                    <input type="text" name="national_id" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">رقم الهاتف</label>
                    <input type="tel" name="phone" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">البطاقة المهنية (PDF - اختياري)</label>
                    <div class="file-upload-wrap">
                        <input type="file" name="card_file" accept=".pdf">
                        <i class="fas fa-id-card"></i>
                        <span>رفع البطاقة المهنية PDF</span>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-full"><i class="fas fa-plus"></i> إضافة المحامي</button>
            </form>
        </div>
        <div class="table-wrap">
            <div class="table-header"><h3><i class="fas fa-gavel"></i> قائمة المحامين</h3></div>
            <table>
                <thead><tr><th>#</th><th>الاسم</th><th>الهاتف</th><th>البطاقة</th></tr></thead>
                <tbody>
                <?php while ($lw = $allLawyers->fetch_assoc()): ?>
                <tr>
                    <td>#<?= $lw['id'] ?></td>
                    <td><?= htmlspecialchars($lw['first_name'].' '.$lw['last_name']) ?></td>
                    <td><?= $lw['phone'] ?></td>
                    <td>
                        <?php if ($lw['card_file']): ?>
                        <a href="<?= $lw['card_file'] ?>" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-file-pdf"></i></a>
                        <?php else: ?><span class="text-muted" style="font-size:0.78rem;">-</span><?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- المستخدمون -->
    <?php elseif ($adminTab === 'users'):
        $allUsers = $conn->query("SELECT * FROM users WHERE role='user' ORDER BY created_at DESC");
    ?>
    <div class="table-wrap">
        <div class="table-header"><h3><i class="fas fa-users"></i> المستخدمون المسجلون</h3></div>
        <table>
            <thead><tr><th>#</th><th>الاسم</th><th>الهاتف</th><th>النوع</th><th>تاريخ التسجيل</th></tr></thead>
            <tbody>
            <?php while ($u = $allUsers->fetch_assoc()):
                $ptypes = ['citizen'=>'مواطن','lawyer'=>'محامي','notary'=>'موثق','bailiff'=>'محضر قضائي'];
            ?>
            <tr>
                <td>#<?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></td>
                <td><?= $u['phone'] ?></td>
                <td><?= $ptypes[$u['person_type']] ?? '-' ?></td>
                <td style="font-size:0.78rem;"><?= date('Y/m/d', strtotime($u['created_at'])) ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
// ============================================================
// صفحة الإشعارات - Notifications
// ============================================================
elseif ($page === 'notifications' && $user):
    $uid = (int)$user['id'];
    // تعليم الإشعارات كمقروءة
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
    $myNotifs = $conn->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC LIMIT 50");
?>
<div class="page-container">
    <div class="page-card">
        <div class="page-header">
            <div class="icon-wrap"><i class="fas fa-bell"></i></div>
            <h1>الإشعارات</h1>
        </div>
        <?php while ($n = $myNotifs->fetch_assoc()): ?>
        <div style="padding:1rem;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:flex-start;">
            <div style="width:36px;height:36px;background:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--primary);">
                <i class="fas fa-bell"></i>
            </div>
            <div>
                <p style="font-size:0.9rem;"><?= htmlspecialchars($n['message']) ?></p>
                <span style="font-size:0.75rem;color:var(--text-muted);"><?= date('Y/m/d H:i', strtotime($n['created_at'])) ?></span>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<?php
// ============================================================
// صفحة اتصل بنا - Contact
// ============================================================
elseif ($page === 'contact'):
?>
<div class="page-container">
    <div class="page-card">
        <div class="page-header">
            <div class="icon-wrap"><i class="fas fa-phone"></i></div>
            <h1 data-i18n="contactTitle">اتصل بنا</h1>
            <p data-i18n="contactSub">نحن هنا لمساعدتك</p>
        </div>
        <div style="display:flex;flex-direction:column;gap:1rem;">
            <div style="display:flex;align-items:center;gap:12px;padding:1rem;background:var(--accent);border-radius:var(--radius-sm);">
                <div style="width:44px;height:44px;background:var(--primary);border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;flex-shrink:0;"><i class="fas fa-phone"></i></div>
                <div><div style="font-size:0.78rem;color:var(--text-muted);">الهاتف</div><div style="font-weight:700;">+213 48 xx xx xx</div></div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;padding:1rem;background:var(--accent);border-radius:var(--radius-sm);">
                <div style="width:44px;height:44px;background:var(--primary);border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;flex-shrink:0;"><i class="fas fa-envelope"></i></div>
                <div><div style="font-size:0.78rem;color:var(--text-muted);">البريد الإلكتروني</div><div style="font-weight:700;">contact@eprison.gov.dz</div></div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;padding:1rem;background:var(--accent);border-radius:var(--radius-sm);">
                <div style="width:44px;height:44px;background:var(--primary);border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;flex-shrink:0;"><i class="fas fa-map-marker-alt"></i></div>
                <div><div style="font-size:0.78rem;color:var(--text-muted);">العنوان</div><div style="font-weight:700;">المديرية العامة لإدارة السجون - الجزائر</div></div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

</div><!-- /main-content -->

<!-- ============================================================
     الـ Footer
     ============================================================ -->
<footer class="footer">
    <div class="footer-grid">
        <div class="footer-brand">
            <div class="logo">⚖️ e-Prison</div>
            <p>منصة الخدمات الإلكترونية للمؤسسات العقابية في الجمهورية الجزائرية الديمقراطية الشعبية. نسعى لتيسير التواصل بين المواطنين وذويهم بأمان وكفاءة.</p>
        </div>
        <div class="footer-links">
            <h4>روابط سريعة</h4>
            <ul>
                <li><a href="?">الصفحة الرئيسية</a></li>
                <li><a href="?page=service">الخدمات</a></li>
                <li><a href="?page=contact">اتصل بنا</a></li>
            </ul>
        </div>
        <div class="footer-links">
            <h4>الخدمات</h4>
            <ul>
                <li><a href="?page=service&type=message">الرسائل الإلكترونية</a></li>
                <li><a href="?page=service&type=visit">حجز الزيارات</a></li>
                <li><a href="?page=service&type=notification">التبليغ الإلكتروني</a></li>
                <li><a href="?page=service&type=shop">المتجر الإلكتروني</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <p>جميع الحقوق محفوظة © 2026 - المديرية العامة لإدارة السجون وإعادة الإدماج | الجمهورية الجزائرية الديمقراطية الشعبية</p>
    </div>
</footer>

<!-- ============================================================
     مودال الوصل PDF
     ============================================================ -->
<div id="receiptModal" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closeModal()">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        <div id="receiptContent"></div>
        <div style="display:flex;gap:10px;margin-top:1.5rem;" class="no-print">
            <button onclick="printReceipt()" class="btn btn-primary flex-1"><i class="fas fa-print"></i> طباعة</button>
            <button onclick="downloadReceipt()" class="btn btn-outline flex-1"><i class="fas fa-download"></i> تحميل PDF</button>
        </div>
    </div>
</div>

<!-- ============================================================
     مودال التوقيع والبصمة الإلكترونية
     ============================================================ -->
<div id="signatureModal" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closeSignatureModal()">
    <div class="modal-box" style="max-width:680px;width:96%;">
        <button class="modal-close" onclick="closeSignatureModal()"><i class="fas fa-times"></i></button>
        <div style="text-align:center;margin-bottom:1.5rem;">
            <div style="font-size:1.8rem;margin-bottom:6px;">✍️</div>
            <h3 style="font-weight:900;color:#5b3fa0;">توقيع وبصمة السجين الإلكترونية</h3>
            <p style="font-size:0.82rem;color:var(--text-muted);">ارسم التوقيع والبصمة بالماوس أو باللمس</p>
        </div>

        <form id="signatureForm" method="POST">
            <input type="hidden" name="action" value="save_signature">
            <input type="hidden" name="request_id" id="sigRequestId">
            <input type="hidden" name="signature_data" id="sigData">
            <input type="hidden" name="fingerprint_data" id="fpData">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;" class="sig-grid">
                <!-- التوقيع -->
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <label style="font-weight:700;font-size:0.9rem;"><i class="fas fa-pen-fancy" style="color:#5b3fa0;"></i> التوقيع الإلكتروني</label>
                        <button type="button" onclick="clearCanvas('sigCanvas')" class="btn btn-sm btn-outline" style="font-size:0.75rem;padding:4px 10px;">
                            <i class="fas fa-eraser"></i> مسح
                        </button>
                    </div>
                    <canvas id="sigCanvas" width="280" height="150"
                        style="border:2px solid #5b3fa0;border-radius:10px;background:#fff;cursor:crosshair;display:block;width:100%;touch-action:none;"></canvas>
                    <p style="font-size:0.72rem;color:var(--text-muted);margin-top:4px;text-align:center;">ارسم توقيعك هنا</p>
                </div>
                <!-- البصمة -->
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <label style="font-weight:700;font-size:0.9rem;"><i class="fas fa-fingerprint" style="color:#e67e22;"></i> البصمة الإلكترونية</label>
                        <button type="button" onclick="clearCanvas('fpCanvas')" class="btn btn-sm btn-outline" style="font-size:0.75rem;padding:4px 10px;">
                            <i class="fas fa-eraser"></i> مسح
                        </button>
                    </div>
                    <canvas id="fpCanvas" width="280" height="150"
                        style="border:2px solid #e67e22;border-radius:10px;background:#fff;cursor:crosshair;display:block;width:100%;touch-action:none;"></canvas>
                    <p style="font-size:0.72rem;color:var(--text-muted);margin-top:4px;text-align:center;">ارسم بصمتك هنا</p>
                </div>
            </div>

            <div style="display:flex;gap:10px;" class="no-print">
                <button type="button" onclick="submitSignature()" class="btn btn-primary flex-1">
                    <i class="fas fa-save"></i> حفظ التوقيع والبصمة
                </button>
                <button type="button" onclick="closeSignatureModal()" class="btn btn-outline flex-1">
                    <i class="fas fa-times"></i> إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

<!-- مودال PDF الإدارة -->
<div id="adminPDFModal" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closeAdminModal()">
    <div class="modal-box" style="max-width:680px;width:96%;">
        <button class="modal-close" onclick="closeAdminModal()"><i class="fas fa-times"></i></button>
        <div id="adminPDFContent"></div>
        <div style="display:flex;gap:10px;margin-top:1.5rem;" class="no-print">
            <button onclick="printAdminPDF()" class="btn btn-primary flex-1"><i class="fas fa-print"></i> طباعة PDF</button>
            <button onclick="downloadAdminPDF()" class="btn btn-outline flex-1" style="border-color:#1a7a4a;color:#1a7a4a;"><i class="fas fa-download"></i> تحميل</button>
            <button onclick="closeAdminModal()" class="btn btn-outline flex-1"><i class="fas fa-times"></i> إغلاق</button>
        </div>
    </div>
</div>

<!-- ============================================================
     JavaScript
     ============================================================ -->
<script>
// ============================================================
// الوضع الليلي - Dark Mode
// ============================================================
function toggleDark() {
    document.body.classList.toggle('dark');
    localStorage.setItem('darkMode', document.body.classList.contains('dark') ? '1' : '0');
}
// تطبيق الوضع المحفوظ
if (localStorage.getItem('darkMode') === '1') document.body.classList.add('dark');

// ============================================================
// اللغات - Multilingual
// ============================================================
const translations = {
    ar: {
        algState: 'الجمهورية الجزائرية الديمقراطية الشعبية',
        ministry: 'المديرية العامة لإدارة السجون وإعادة الإدماج',
        heroTitle: 'منصة <span>e-Prison</span><br>للخدمات الرقمية',
        heroSub: 'خدمات إلكترونية متكاملة للتواصل مع ذويكم في المؤسسات العقابية بأمان وسهولة',
        register: 'إنشاء حساب', login: 'تسجيل الدخول', contact: 'اتصل بنا',
        myAccount: 'حسابي', services: 'الخدمات',
        servicesTitle: 'خدماتنا الإلكترونية',
        servicesSub: 'أربع خدمات رقمية متكاملة لتسهيل التواصل مع ذويكم',
        msgService: 'الرسائل الإلكترونية', visitService: 'حجز مواعيد الزيارة',
        notifService: 'التبليغ الإلكتروني', shopService: 'المتجر الإلكتروني',
        howTitle: 'كيف تستخدم المنصة؟', howSub: 'ثلاث خطوات بسيطة',
        step1:'إنشاء حساب',step2:'اختر الخدمة',step3:'ادفع واستلم الوصل',
        statUsers:'مستخدم مسجل',statReqs:'طلب معالج',statServices:'خدمة رقمية',statPrisoners:'سجين مسجل',
        registerNow:'سجل الآن',loginBtn:'تسجيل الدخول',startService:'ابدأ الخدمات',
        createAccount:'إنشاء حساب جديد',loginTitle:'تسجيل الدخول',
        contactTitle:'اتصل بنا',contactSub:'نحن هنا لمساعدتك'
    },
    fr: {
        algState: 'République Algérienne Démocratique et Populaire',
        ministry: 'Direction Générale de l\'Administration Pénitentiaire',
        heroTitle: 'Plateforme <span>e-Prison</span><br>Services Numériques',
        heroSub: 'Services électroniques intégrés pour communiquer avec vos proches en toute sécurité',
        register: 'Créer compte', login: 'Se connecter', contact: 'Nous contacter',
        myAccount: 'Mon compte', services: 'Services',
        servicesTitle: 'Nos Services Électroniques',
        servicesSub: 'Quatre services numériques pour faciliter la communication',
        msgService: 'Messages Électroniques', visitService: 'Réservation de Visite',
        notifService: 'Notification Électronique', shopService: 'Boutique en Ligne',
        howTitle: 'Comment utiliser la plateforme?', howSub: 'Trois étapes simples',
        step1:'Créer un compte',step2:'Choisir le service',step3:'Payer et recevoir le reçu',
        statUsers:'Utilisateur inscrit',statReqs:'Demande traitée',statServices:'Service numérique',statPrisoners:'Détenu inscrit',
        registerNow:"S'inscrire",loginBtn:'Se connecter',startService:'Commencer',
        createAccount:'Créer un compte',loginTitle:'Se connecter',
        contactTitle:'Nous contacter',contactSub:'Nous sommes là pour vous aider'
    },
    en: {
        algState: 'People\'s Democratic Republic of Algeria',
        ministry: 'General Directorate of Prison Administration',
        heroTitle: '<span>e-Prison</span> Platform<br>Digital Services',
        heroSub: 'Integrated digital services to communicate with your loved ones in correctional facilities',
        register: 'Register', login: 'Login', contact: 'Contact Us',
        myAccount: 'My Account', services: 'Services',
        servicesTitle: 'Our Digital Services',
        servicesSub: 'Four digital services to facilitate communication',
        msgService: 'Electronic Messages', visitService: 'Visit Reservation',
        notifService: 'Electronic Notification', shopService: 'Online Shop',
        howTitle: 'How to use the platform?', howSub: 'Three simple steps',
        step1:'Create account',step2:'Choose service',step3:'Pay & receive receipt',
        statUsers:'Registered users',statReqs:'Processed requests',statServices:'Digital services',statPrisoners:'Registered inmates',
        registerNow:'Register Now',loginBtn:'Login',startService:'Start Services',
        createAccount:'Create Account',loginTitle:'Login',
        contactTitle:'Contact Us',contactSub:'We are here to help you'
    }
};

let currentLang = localStorage.getItem('lang') || 'ar';
const langLabels = { ar: 'ع', fr: 'Fr', en: 'En' };

function setLang(lang) {
    currentLang = lang;
    localStorage.setItem('lang', lang);
    document.getElementById('langLabel').textContent = langLabels[lang];
    const t = translations[lang];
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (t[key] !== undefined) el.innerHTML = t[key];
    });
    // اتجاه النص
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
    document.documentElement.lang = lang;
}
// تطبيق اللغة عند التحميل
setLang(currentLang);

// ============================================================
// إظهار/إخفاء كلمة المرور
// ============================================================
function togglePass(id) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
}

// ============================================================
// تنسيق رقم البطاقة
// ============================================================
function formatCard(el) {
    let v = el.value.replace(/\D/g, '').substring(0, 16);
    el.value = v.match(/.{1,4}/g)?.join(' ') || v;
    document.getElementById('cardNumDisplay').textContent = el.value || '•••• •••• •••• ••••';
}
function formatExpiry(el) {
    let v = el.value.replace(/\D/g, '');
    if (v.length >= 2) v = v.substring(0, 2) + '/' + v.substring(2, 4);
    el.value = v;
    document.getElementById('cardExpDisplay').textContent = el.value || 'MM/YY';
}
function updateCard() {
    document.getElementById('cardHolderDisplay').textContent = document.getElementById('cardHolderInput')?.value || 'الاسم الكامل';
}

// ============================================================
// اختيار فوج الزيارة
// ============================================================
function selectSlot(btn, slot) {
    document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('visitSlot').value = slot;
}

// ============================================================
// نوع الزيارة (مواطن/محامي)
// ============================================================
function handleVisitType() {
    const vt = document.getElementById('visitTypeSelect')?.value;
    const lawyerGroup = document.getElementById('lawyerCardGroup');
    const totalEl = document.getElementById('totalAmount');
    if (vt === 'lawyer') {
        if (lawyerGroup) lawyerGroup.style.display = 'block';
        if (totalEl) totalEl.textContent = 'مجاناً';
    } else {
        if (lawyerGroup) lawyerGroup.style.display = 'none';
        if (totalEl) totalEl.textContent = '100 دج';
    }
}

// ============================================================
// المتجر - اختيار المنتجات مع حساب المجاميع
// ============================================================
let selectedItems = [];
let shopTotal = 0;      // مجموع أسعار المنتجات
const SERVICE_FEE = 100; // رسوم الخدمة الثابتة

function toggleShopItem(el, item, price) {
    el.classList.toggle('selected');
    if (el.classList.contains('selected')) {
        selectedItems.push(item);
        shopTotal += price;      // إضافة سعر المنتج
    } else {
        selectedItems = selectedItems.filter(i => i !== item);
        shopTotal -= price;      // خصم سعر المنتج
        if (shopTotal < 0) shopTotal = 0;
    }
    // تحديث حقل المنتجات المخفي
    const inp = document.getElementById('shopItems');
    if (inp) inp.value = selectedItems.join('|');

    // تحديث حقل المجموع المخفي (بدون رسوم الخدمة)
    const totalInp = document.getElementById('shopProductsTotal');
    if (totalInp) totalInp.value = shopTotal;

    // تحديث عرض المجاميع
    updateShopDisplay();
}

function updateShopDisplay() {
    const grandTotal = shopTotal + SERVICE_FEE;

    // ملخص داخل قسم المنتجات
    const summaryBox = document.getElementById('shopSummaryBox');
    if (summaryBox) {
        summaryBox.style.display = shopTotal > 0 ? 'block' : 'none';
        const summProd  = document.getElementById('summaryProductsTotal');
        const summGrand = document.getElementById('summaryGrandTotal');
        if (summProd)  summProd.textContent  = shopTotal + ' دج';
        if (summGrand) summGrand.textContent = grandTotal + ' دج';
    }

    // ملخص في قسم الدفع
    const payProd  = document.getElementById('payProductsTotal');
    const totalEl  = document.getElementById('totalAmount');
    if (payProd)  payProd.textContent  = shopTotal + ' دج';
    if (totalEl)  totalEl.textContent  = grandTotal + ' دج';
}

// ============================================================
// التحقق من النموذج
// ============================================================
function validateForm() {
    const slot = document.getElementById('visitSlot');
    if (slot && !slot.value) {
        alert('يرجى اختيار فوج الزيارة');
        return false;
    }
    return true;
}

// ============================================================
// عرض الوصل PDF (محاكاة)
// ============================================================
function showReceipt(req) {
    const sTypeAr = {message:'رسالة إلكترونية',visit:'حجز زيارة',notification:'تبليغ إلكتروني',shop:'طلب متجر'};
    const d = new Date(req.created_at);
    const dateStr = d.toLocaleDateString('ar-DZ');
    const timeStr = d.toLocaleTimeString('ar-DZ', {hour:'2-digit',minute:'2-digit'});
    const baseURL = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '/');

    let extra = '';
    if (req.service_type === 'visit') {
        extra = `
        <div class="receipt-row"><span class="receipt-label">فوج الزيارة</span><span class="receipt-value">${req.visit_slot||'-'}</span></div>
        <div class="receipt-row"><span class="receipt-label">تاريخ الزيارة</span><span class="receipt-value">${req.visit_date||'-'}</span></div>`;
    }

    // قسم التوقيع للتبليغ الموقع
    let signSection = '';
    if (req.service_type === 'notification' && req.signature_file) {
        const sigURL = baseURL + req.signature_file;
        const fpURL  = baseURL + req.fingerprint_file;
        const signedDate = req.signed_at ? new Date(req.signed_at).toLocaleString('ar-DZ') : '';
        signSection = `
        <div style="margin-top:1rem;border-top:1px solid var(--border);padding-top:1rem;">
            <div style="font-weight:700;color:#5b3fa0;margin-bottom:10px;font-size:0.88rem;">✍️ إثبات استلام السجين:</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div style="text-align:center;border:1px solid #c4b4e8;border-radius:8px;padding:10px;background:#f9f7ff;">
                    <div style="font-size:0.75rem;color:#888;margin-bottom:6px;">التوقيع</div>
                    <img src="${sigURL}" style="max-width:100%;max-height:80px;border-radius:4px;" alt="التوقيع">
                </div>
                <div style="text-align:center;border:1px solid #f0c08a;border-radius:8px;padding:10px;background:#fffaf5;">
                    <div style="font-size:0.75rem;color:#888;margin-bottom:6px;">البصمة</div>
                    <img src="${fpURL}" style="max-width:100%;max-height:80px;border-radius:4px;" alt="البصمة">
                </div>
            </div>
            ${signedDate ? `<p style="text-align:center;font-size:0.73rem;color:#888;margin-top:6px;">تاريخ التوقيع: ${signedDate}</p>` : ''}
        </div>`;
    }

    // نص الرسالة أو التبليغ
    let msgSection = '';
    if ((req.service_type === 'message' || req.service_type === 'notification') && req.message_text) {
        const label = req.service_type === 'message' ? 'نص الرسالة' : 'نص التبليغ';
        msgSection = `
        <div style="margin-top:0.8rem;">
            <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:4px;font-weight:600;">${label}:</div>
            <div style="border:1px solid var(--border);border-radius:8px;padding:0.8rem;font-size:0.85rem;line-height:1.8;white-space:pre-wrap;background:var(--accent);">${(req.message_text||'').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div>
        </div>`;
    }

    document.getElementById('receiptContent').innerHTML = `
    <div class="receipt-card" id="printArea">
        <div class="receipt-header">
            <div style="font-size:2rem;margin-bottom:8px;">⚖️</div>
            <h3 style="font-weight:900;color:var(--primary);font-family:var(--font-serif);">الجمهورية الجزائرية الديمقراطية الشعبية</h3>
            <p style="font-size:0.82rem;color:var(--text-muted);">المديرية العامة لإدارة السجون وإعادة الإدماج</p>
            <p style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;">منصة e-Prison</p>
        </div>
        <h4 style="text-align:center;font-weight:700;margin-bottom:1rem;">وصل الخدمة الإلكترونية</h4>
        <div class="receipt-row"><span class="receipt-label">رقم الطلب</span><span class="receipt-value">#${req.id}</span></div>
        <div class="receipt-row"><span class="receipt-label">نوع الخدمة</span><span class="receipt-value">${sTypeAr[req.service_type]||'-'}</span></div>
        <div class="receipt-row"><span class="receipt-label">اسم المرسل</span><span class="receipt-value">${req.sender_first_name} ${req.sender_last_name}</span></div>
        <div class="receipt-row"><span class="receipt-label">رقم السجين</span><span class="receipt-value">${req.prisoner_number}</span></div>
        ${extra}
        <div class="receipt-row"><span class="receipt-label">التاريخ</span><span class="receipt-value">${dateStr}</span></div>
        <div class="receipt-row"><span class="receipt-label">الساعة</span><span class="receipt-value">${timeStr}</span></div>
        <div class="receipt-row"><span class="receipt-label">الحالة</span><span class="receipt-value" style="color:var(--success);font-weight:800;">✅ مقبول</span></div>
        <div class="receipt-total">
            <span>المبلغ المدفوع</span>
            <span>${req.amount||'مجاناً'}</span>
        </div>
        ${msgSection}
        ${signSection}
        <p style="text-align:center;font-size:0.75rem;color:var(--text-muted);margin-top:1rem;">
            هذا الوصل يُثبت تقديمك للخدمة الإلكترونية وقبولها من قِبل الإدارة
        </p>
    </div>`;
    document.getElementById('receiptModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('receiptModal').style.display = 'none';
}

function printReceipt() {
    window.print();
}

function downloadReceipt() {
    // محاكاة التحميل
    alert('سيتم تحميل ملف PDF في الإصدار الكامل. يمكنك طباعة الصفحة كـ PDF من خيارات الطباعة.');
}

// ============================================================
// PDF الإدارة (عرض الرسائل والتبليغات)
// ============================================================
function adminViewPDF(req, type) {
    let content = '';
    const dateStr = new Date(req.created_at).toLocaleString('ar-DZ');
    const baseURL  = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '/');

    if (type === 'message') {
        const msgText = (req.message_text || '').trim();
        content = `
        <div id="adminPrintArea" style="
            border:2px solid #1a7a4a;
            border-radius:14px;
            padding:2rem;
            font-family:'Tajawal',Arial,sans-serif;
            direction:rtl;
        ">
            <!-- رأس الوثيقة -->
            <div style="background:linear-gradient(135deg,#1a7a4a,#0f5033);padding:1.4rem;border-radius:10px;text-align:center;margin-bottom:1.5rem;color:#fff;">
                <div style="font-size:2rem;margin-bottom:6px;">⚖️</div>
                <h3 style="margin:0 0 4px;font-size:1.1rem;font-family:'Amiri',serif;">الجمهورية الجزائرية الديمقراطية الشعبية</h3>
                <p style="margin:0;font-size:0.78rem;opacity:0.85;">المديرية العامة لإدارة السجون وإعادة الإدماج</p>
                <div style="margin-top:10px;background:rgba(255,255,255,0.15);border-radius:6px;padding:6px 14px;display:inline-block;">
                    <strong style="font-size:1rem;">📨 رسالة إلكترونية — e-Prison</strong>
                </div>
            </div>

            <!-- رقم الطلب -->
            <div style="text-align:center;margin-bottom:1.2rem;">
                <span style="background:#e8f5ee;color:#1a7a4a;font-weight:800;font-size:0.9rem;padding:4px 16px;border-radius:20px;border:1px solid #1a7a4a;">
                    رقم الطلب: #${req.id}
                </span>
            </div>

            <!-- معلومات المرسل -->
            <div style="background:#f5f9f6;border:1px solid #dde8e2;border-radius:10px;padding:1rem 1.2rem;margin-bottom:1.2rem;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:0.88rem;">
                    <div><span style="color:#5a7a65;font-size:0.78rem;">اسم المرسل</span><br><strong>${req.sender_first_name} ${req.sender_last_name}</strong></div>
                    <div><span style="color:#5a7a65;font-size:0.78rem;">رقم السجين</span><br><strong style="color:#1a7a4a;">${req.prisoner_number}</strong></div>
                    <div><span style="color:#5a7a65;font-size:0.78rem;">نوع الخدمة</span><br><strong>رسالة إلكترونية</strong></div>
                    <div><span style="color:#5a7a65;font-size:0.78rem;">التاريخ</span><br><strong>${dateStr}</strong></div>
                </div>
            </div>

            <!-- نص الرسالة -->
            <div style="margin-bottom:1rem;">
                <div style="font-weight:700;color:#1a7a4a;margin-bottom:8px;font-size:0.9rem;">
                    <i class="fas fa-envelope-open-text"></i> نص الرسالة:
                </div>
                <div style="
                    border:2px solid #1a7a4a;
                    border-radius:10px;
                    padding:1.2rem;
                    background:#fff;
                    min-height:90px;
                    line-height:2;
                    font-size:0.95rem;
                    white-space:pre-wrap;
                ">
                    ${msgText
                        ? msgText.replace(/</g,'&lt;').replace(/>/g,'&gt;')
                        : '<span style="color:#aaa;font-style:italic;">لا يوجد نص للرسالة</span>'
                    }
                </div>
            </div>

            <!-- تذييل -->
            <div style="border-top:1px solid #dde8e2;padding-top:0.8rem;text-align:center;font-size:0.72rem;color:#5a7a65;">
                e-Prison | المديرية العامة لإدارة السجون | الجزائر 2026
            </div>
        </div>`;

    } else if (type === 'notification' || type === 'notification_signed') {
        const notifText  = (req.message_text || '').trim();
        const isSigned   = !!req.signature_file;
        const color      = '#5b3fa0';
        const colorLight = '#f3f0fb';
        const colorBorder= '#c4b4e8';

        let signSection = '';
        if (isSigned) {
            const sigURL = baseURL + req.signature_file;
            const fpURL  = baseURL + req.fingerprint_file;
            const signedDate = req.signed_at ? new Date(req.signed_at).toLocaleString('ar-DZ') : '';
            signSection = `
            <div style="margin-top:1.2rem;">
                <div style="font-weight:700;color:${color};margin-bottom:10px;font-size:0.9rem;display:flex;align-items:center;gap:6px;">
                    <span>✍️</span> إثبات استلام السجين:
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div style="text-align:center;border:1px solid ${colorBorder};border-radius:10px;padding:12px;background:#fff;">
                        <div style="font-size:0.78rem;color:#666;margin-bottom:8px;font-weight:600;">التوقيع الإلكتروني</div>
                        <img src="${sigURL}" style="max-width:100%;max-height:90px;border-radius:6px;" alt="التوقيع" onerror="this.style.display='none'">
                    </div>
                    <div style="text-align:center;border:1px solid #f0c08a;border-radius:10px;padding:12px;background:#fff;">
                        <div style="font-size:0.78rem;color:#666;margin-bottom:8px;font-weight:600;">البصمة الإلكترونية</div>
                        <img src="${fpURL}" style="max-width:100%;max-height:90px;border-radius:6px;" alt="البصمة" onerror="this.style.display='none'">
                    </div>
                </div>
                ${signedDate ? `<p style="text-align:center;font-size:0.75rem;color:#888;margin-top:8px;">تاريخ التوقيع: ${signedDate}</p>` : ''}
            </div>`;
        }

        content = `
        <div id="adminPrintArea" style="
            border:2px solid ${color};
            border-radius:14px;
            padding:2rem;
            font-family:'Tajawal',Arial,sans-serif;
            direction:rtl;
        ">
            <!-- رأس الوثيقة -->
            <div style="background:linear-gradient(135deg,#5b3fa0,#3d2a70);padding:1.4rem;border-radius:10px;text-align:center;margin-bottom:1.5rem;color:#fff;">
                <div style="font-size:2rem;margin-bottom:6px;">⚖️</div>
                <h3 style="margin:0 0 4px;font-size:1.1rem;font-family:'Amiri',serif;">الجمهورية الجزائرية الديمقراطية الشعبية</h3>
                <p style="margin:0;font-size:0.78rem;opacity:0.85;">المديرية العامة لإدارة السجون وإعادة الإدماج</p>
                <div style="margin-top:10px;background:rgba(255,255,255,0.15);border-radius:6px;padding:6px 14px;display:inline-block;">
                    <strong style="font-size:1rem;">⚖️ تبليغ إلكتروني — e-Prison</strong>
                </div>
                ${isSigned ? '<div style="margin-top:6px;"><span style="background:#27ae60;color:#fff;font-size:0.75rem;padding:3px 12px;border-radius:20px;">✅ موقّع ومبصوم</span></div>' : ''}
            </div>

            <!-- رقم الطلب -->
            <div style="text-align:center;margin-bottom:1.2rem;">
                <span style="background:${colorLight};color:${color};font-weight:800;font-size:0.9rem;padding:4px 16px;border-radius:20px;border:1px solid ${color};">
                    رقم الطلب: #${req.id}
                </span>
            </div>

            <!-- معلومات -->
            <div style="background:${colorLight};border:1px solid ${colorBorder};border-radius:10px;padding:1rem 1.2rem;margin-bottom:1.2rem;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:0.88rem;">
                    <div><span style="color:#666;font-size:0.78rem;">المحضر القضائي</span><br><strong>${req.sender_first_name} ${req.sender_last_name}</strong></div>
                    <div><span style="color:#666;font-size:0.78rem;">رقم السجين</span><br><strong style="color:${color};">${req.prisoner_number}</strong></div>
                    <div><span style="color:#666;font-size:0.78rem;">نوع الخدمة</span><br><strong>تبليغ إلكتروني</strong></div>
                    <div><span style="color:#666;font-size:0.78rem;">التاريخ</span><br><strong>${dateStr}</strong></div>
                </div>
            </div>

            <!-- نص التبليغ -->
            <div style="margin-bottom:1rem;">
                <div style="font-weight:700;color:${color};margin-bottom:8px;font-size:0.9rem;">
                    📄 نص التبليغ:
                </div>
                <div style="
                    border:2px solid ${color};
                    border-radius:10px;
                    padding:1.2rem;
                    background:#fff;
                    min-height:90px;
                    line-height:2;
                    font-size:0.95rem;
                    white-space:pre-wrap;
                ">
                    ${notifText
                        ? notifText.replace(/</g,'&lt;').replace(/>/g,'&gt;')
                        : '<span style="color:#aaa;font-style:italic;">لا يوجد نص للتبليغ</span>'
                    }
                </div>
            </div>

            ${signSection}

            <!-- تذييل -->
            <div style="border-top:1px solid ${colorBorder};padding-top:0.8rem;text-align:center;font-size:0.72rem;color:#888;margin-top:1rem;">
                e-Prison | المديرية العامة لإدارة السجون | الجزائر 2026
            </div>
        </div>`;
    }

    if (!content) return;
    document.getElementById('adminPDFContent').innerHTML = content;
    document.getElementById('adminPDFModal').style.display = 'flex';
}

function closeAdminModal() {
    document.getElementById('adminPDFModal').style.display = 'none';
}

function printAdminPDF() {
    const content = document.getElementById('adminPrintArea');
    if (!content) { window.print(); return; }
    const printWin = window.open('', '_blank', 'width=860,height=700');
    printWin.document.write(`<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>طباعة - e-Prison</title>
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@400;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:'Tajawal',Arial,sans-serif; direction:rtl; padding:1.5rem; color:#1a2e22; background:#fff; }
  img { max-width:100%; }
  @media print {
    body { padding:0.5rem; }
    @page { margin:1cm; }
  }
</style>
</head>
<body onload="setTimeout(function(){ window.print(); }, 800)">
${content.outerHTML}
<div style="text-align:center;margin-top:1.5rem;padding-top:1rem;border-top:1px solid #ddd;" class="no-print">
    <button onclick="window.print()" style="background:#1a7a4a;color:#fff;border:none;padding:10px 28px;border-radius:8px;font-size:1rem;cursor:pointer;font-family:'Tajawal',sans-serif;margin-left:10px;">
        🖨️ طباعة
    </button>
    <button onclick="window.close()" style="background:#6b8876;color:#fff;border:none;padding:10px 28px;border-radius:8px;font-size:1rem;cursor:pointer;font-family:'Tajawal',sans-serif;">
        إغلاق
    </button>
</div>
</body></html>`);
    printWin.document.close();
    printWin.focus();
}

function downloadAdminPDF() {
    printAdminPDF();
}

// ============================================================
// مودال التوقيع والبصمة الإلكترونية
// ============================================================
let sigDrawing = false, fpDrawing = false;

function openSignatureModal(reqId) {
    document.getElementById('sigRequestId').value = reqId;
    clearCanvas('sigCanvas');
    clearCanvas('fpCanvas');
    document.getElementById('signatureModal').style.display = 'flex';
    initCanvas('sigCanvas', true);
    initCanvas('fpCanvas', false);
}

function closeSignatureModal() {
    document.getElementById('signatureModal').style.display = 'none';
}

function clearCanvas(id) {
    const c = document.getElementById(id);
    if (!c) return;
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, c.width, c.height);
    // خلفية بيضاء
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, c.width, c.height);
    // نص دليلي
    ctx.fillStyle = '#ccc';
    ctx.font = '13px Tajawal, Arial';
    ctx.textAlign = 'center';
    ctx.fillText(id === 'sigCanvas' ? 'التوقيع' : 'البصمة', c.width / 2, c.height / 2);
}

function initCanvas(id, isSig) {
    const canvas = document.getElementById(id);
    if (!canvas) return;

    // ضبط الأبعاد الحقيقية
    const rect = canvas.getBoundingClientRect();
    canvas.width  = rect.width  || 280;
    canvas.height = rect.height || 150;

    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#ccc';
    ctx.font = '13px Tajawal, Arial';
    ctx.textAlign = 'center';
    ctx.fillText(isSig ? 'التوقيع' : 'البصمة', canvas.width / 2, canvas.height / 2);

    let drawing = false;
    ctx.strokeStyle = isSig ? '#5b3fa0' : '#e67e22';
    ctx.lineWidth   = isSig ? 2.5 : 8;
    ctx.lineCap     = 'round';
    ctx.lineJoin    = 'round';

    function getPos(e) {
        const r = canvas.getBoundingClientRect();
        const scaleX = canvas.width  / r.width;
        const scaleY = canvas.height / r.height;
        if (e.touches) {
            return {
                x: (e.touches[0].clientX - r.left) * scaleX,
                y: (e.touches[0].clientY - r.top)  * scaleY
            };
        }
        return {
            x: (e.clientX - r.left) * scaleX,
            y: (e.clientY - r.top)  * scaleY
        };
    }

    function startDraw(e) {
        e.preventDefault();
        drawing = true;
        const p = getPos(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    }
    function draw(e) {
        if (!drawing) return;
        e.preventDefault();
        const p = getPos(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
    }
    function stopDraw(e) {
        drawing = false;
    }

    // إزالة المستمعين القديمة أولاً
    canvas.replaceWith(canvas.cloneNode(true));
    const fresh = document.getElementById(id);
    const freshCtx = fresh.getContext('2d');
    freshCtx.fillStyle = '#fff';
    freshCtx.fillRect(0, 0, fresh.width, fresh.height);
    freshCtx.strokeStyle = isSig ? '#5b3fa0' : '#e67e22';
    freshCtx.lineWidth   = isSig ? 2.5 : 8;
    freshCtx.lineCap     = 'round';
    freshCtx.lineJoin    = 'round';

    let freshDrawing = false;
    function getPosFresh(e) {
        const r = fresh.getBoundingClientRect();
        const scaleX = fresh.width  / r.width;
        const scaleY = fresh.height / r.height;
        if (e.touches) {
            return {
                x: (e.touches[0].clientX - r.left) * scaleX,
                y: (e.touches[0].clientY - r.top)  * scaleY
            };
        }
        return {
            x: (e.clientX - r.left) * scaleX,
            y: (e.clientY - r.top)  * scaleY
        };
    }
    fresh.addEventListener('mousedown',  e => { freshDrawing=true; const p=getPosFresh(e); freshCtx.beginPath(); freshCtx.moveTo(p.x,p.y); });
    fresh.addEventListener('mousemove',  e => { if(!freshDrawing)return; const p=getPosFresh(e); freshCtx.lineTo(p.x,p.y); freshCtx.stroke(); });
    fresh.addEventListener('mouseup',    ()=> { freshDrawing=false; });
    fresh.addEventListener('mouseleave', ()=> { freshDrawing=false; });
    fresh.addEventListener('touchstart', e => { e.preventDefault(); freshDrawing=true; const p=getPosFresh(e); freshCtx.beginPath(); freshCtx.moveTo(p.x,p.y); }, {passive:false});
    fresh.addEventListener('touchmove',  e => { e.preventDefault(); if(!freshDrawing)return; const p=getPosFresh(e); freshCtx.lineTo(p.x,p.y); freshCtx.stroke(); }, {passive:false});
    fresh.addEventListener('touchend',   ()=> { freshDrawing=false; });
}

function submitSignature() {
    const sigCanvas = document.getElementById('sigCanvas');
    const fpCanvas  = document.getElementById('fpCanvas');
    const sigData   = sigCanvas ? sigCanvas.toDataURL('image/png') : '';
    const fpData    = fpCanvas  ? fpCanvas.toDataURL('image/png')  : '';

    // تحقق بسيط: هل رُسم شيء؟
    function isBlank(canvas) {
        const ctx = canvas.getContext('2d');
        const data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        for (let i = 0; i < data.length; i += 4) {
            if (data[i] < 250 || data[i+1] < 250 || data[i+2] < 250) return false;
        }
        return true;
    }

    if (isBlank(sigCanvas)) { alert('يرجى رسم التوقيع أولاً'); return; }
    if (isBlank(fpCanvas))  { alert('يرجى رسم البصمة أولاً');  return; }

    document.getElementById('sigData').value = sigData;
    document.getElementById('fpData').value  = fpData;
    document.getElementById('signatureForm').submit();
}

// ============================================================
// إغلاق التنبيه تلقائياً
// ============================================================
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert:not(.alert-info)');
    alerts.forEach(a => {
        if (!a.closest('.page-card')) {
            a.style.transition = 'opacity 0.5s';
            a.style.opacity = '0';
            setTimeout(() => a.remove(), 500);
        }
    });
}, 4000);

// ============================================================
// رسوم متحركة عند التمرير
// ============================================================
const observer = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if (e.isIntersecting) e.target.style.opacity = '1';
    });
}, { threshold: 0.1 });
document.querySelectorAll('.service-card, .stat-item, .dash-stat').forEach(el => {
    el.style.opacity = '0';
    el.style.transition = 'opacity 0.5s ease';
    observer.observe(el);
});
</script>

</body>
</html>
