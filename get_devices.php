<?php
// get_devices.php
include 'db.php';

// room_id バリデーション
if (empty($_GET['room_id']) || !ctype_digit($_GET['room_id'])) {
    http_response_code(400);
    exit('不正なリクエストです');
}
$room_id = (int)$_GET['room_id'];

// equipments テーブルから該当ルームの装置を取得
$stmt = $db->prepare("
    SELECT id, name, description
      FROM equipments
     WHERE room_id = :room_id
");
$stmt->bindValue(':room_id', $room_id, PDO::PARAM_INT);
$stmt->execute();
$equipments = $stmt->fetchAll();

if (empty($equipments)) {
    echo '<p class="text-muted">この部屋にはまだ装置が登録されていません。</p>';
    exit;
}

// 装置リストを <ul> で出力
echo '<ul class="list-group">';
foreach ($equipments as $e) {
    printf(
        '<li class="device-item list-group-item" data-equipment-id="%d">%s<br><small class="text-muted">%s</small></li>',
        $e['id'],
        htmlspecialchars($e['name'], ENT_QUOTES),
        htmlspecialchars($e['description'], ENT_QUOTES)
    );
}
echo '</ul>';
