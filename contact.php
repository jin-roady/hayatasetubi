<?php
error_reporting(0);
date_default_timezone_set('Asia/Tokyo');

mb_language("Japanese");
mb_internal_encoding("UTF-8");

// ================================
// 設定（ここを自分のGmailに変更）
// ================================
$toAdmin   = "hayata.s.k827@gmail.com"; // 受信したいGmail
$from      = "no-reply@hayata-setsubi.jp";      // さくらのドメインに合わせた送信元
$siteName  = "早田設備株式会社";
$thanksUrl = "thanks.html";

$subjectAdmin = "【早田設備】お問い合わせがありました";
$subjectUser  = "【早田設備】お問い合わせありがとうございます（自動返信）";

$maxTotalSize = 10 * 1024 * 1024;
$allowedExt = array('jpg','jpeg','png','gif','webp','pdf');

// ハニーポット対策
if (!empty($_POST['website'])) { exit; }

// 必須チェック
if (empty($_POST['name']) || empty($_POST['email']) || empty($_POST['message']) || empty($_POST['privacy'])) {
  exit("必須項目が入力されていません。");
}

$name    = htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8');
$email   = htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars($_POST['message'], ENT_QUOTES, 'UTF-8');
$tel  = isset($_POST['tel'])  ? htmlspecialchars($_POST['tel'],  ENT_QUOTES, 'UTF-8') : '';
$type = isset($_POST['type']) ? htmlspecialchars($_POST['type'], ENT_QUOTES, 'UTF-8') : '';
$now = date("Y-m-d H:i:s");

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { exit("メールアドレスの形式が正しくありません。"); }

// 本文作成（管理者宛）
$bodyAdmin = "{$siteName} お問い合わせフォームより\n\n"
  . "【お名前】\n{$name}\n\n"
  . "【メールアドレス】\n{$email}\n\n"
  . "【電話番号】\n{$tel}\n\n"
  . "【お問い合わせ種別】\n{$type}\n\n"
  . "【お問い合わせ内容】\n{$message}\n\n"
  . "--------------------------------\n"
  . "送信日時：{$now}\n";

// 本文作成（お客様宛）
$bodyUser = "{$name} 様\n\n"
  . "この度は、{$siteName}へお問い合わせいただきありがとうございます。\n"
  . "以下の内容でお問い合わせを受け付けました。\n\n"
  . "--------------------------------\n"
  . "【お問い合わせ内容】\n{$message}\n"
  . "--------------------------------\n\n"
  . "内容を確認のうえ、担当者よりご連絡いたします。\n\n"
  . "{$siteName}\n"
  . "TEL：0858-22-7571\n"
  . "送信日時：{$now}\n";

$encodedFromName = mb_encode_mimeheader($siteName);
$headersAdmin  = "From: {$encodedFromName} <{$from}>\r\n" . "Reply-To: {$email}\r\n";
$headersUser   = "From: {$encodedFromName} <{$from}>\r\n" . "Reply-To: {$toAdmin}\r\n";

// 添付ファイルの処理
$attachments = array();
$totalSize = 0;
if (isset($_FILES['files']['name'])) {
  if (!is_array($_FILES['files']['name'])) {
    $_FILES['files'] = array(
      'name' => array($_FILES['files']['name']),
      'tmp_name' => array($_FILES['files']['tmp_name']),
      'error' => array($_FILES['files']['error']),
      'size' => array($_FILES['files']['size']),
    );
  }
  foreach ($_FILES['files']['name'] as $i => $fn) {
    if (empty($fn) || !empty($_FILES['files']['error'][$i])) continue;
    $tmp = $_FILES['files']['tmp_name'][$i];
    $size = (int)$_FILES['files']['size'][$i];
    $totalSize += $size;
    if ($totalSize > $maxTotalSize) exit("ファイルサイズが大きすぎます。");
    $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) exit("許可されていない形式です。");
    $attachments[] = array('name' => preg_replace('/[^\w\.\-\(\)\[\]\s]/u', '_', $fn), 'tmp' => $tmp);
  }
}

// ================================
// 送信関数（修正版）
// ================================
function sendCustomMail($to, $subjectUtf8, $bodyUtf8, $headersBase, $attachments, $fromAddr) {
  $subject = mb_convert_encoding($subjectUtf8, "ISO-2022-JP", "UTF-8");
  $body    = mb_convert_encoding($bodyUtf8,    "ISO-2022-JP", "UTF-8");
  $addParams = "-f " . $fromAddr; // 送信元を明示

  if (empty($attachments)) {
    return mb_send_mail($to, $subject, $body, $headersBase, $addParams);
  }

  $boundary = "----=_Part_" . md5(uniqid((string)mt_rand(), true));
  $headers  = $headersBase . "MIME-Version: 1.0\r\n" . "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

  $mailBody  = "--{$boundary}\r\n" . "Content-Type: text/plain; charset=ISO-2022-JP\r\n" . "Content-Transfer-Encoding: 7bit\r\n\r\n" . $body . "\r\n";

  foreach ($attachments as $a) {
    $fileData = file_get_contents($a['tmp']);
    if ($fileData === false) continue;
    $encoded = chunk_split(base64_encode($fileData));
    $mailBody .= "--{$boundary}\r\n" . "Content-Type: application/octet-stream; name=\"{$a['name']}\"\r\n" . "Content-Transfer-Encoding: base64\r\n" . "Content-Disposition: attachment; filename=\"{$a['name']}\"\r\n\r\n" . $encoded . "\r\n";
  }
  $mailBody .= "--{$boundary}--\r\n";

  // 第4引数にマルチパート用ヘッダー、第5引数に -f パラメータ
  return mb_send_mail($to, $subject, $mailBody, $headers, $addParams);
}

// 実行
$okAdmin = sendCustomMail($toAdmin, $subjectAdmin, $bodyAdmin, $headersAdmin, $attachments, $from);
if (!$okAdmin) { exit("送信に失敗しました。"); }

sendCustomMail($email, $subjectUser, $bodyUser, $headersUser, array(), $from);

header("Location: {$thanksUrl}");
exit;