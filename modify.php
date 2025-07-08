<?php
date_default_timezone_set('Asia/Tokyo');

include 'db.php'; // 実際のDB接続を使用してください

// GETパラメータから予約IDを取得
$reservation_id = isset($_GET['reservation_id']) ? intval($_GET['reservation_id']) : 0;
if ($reservation_id <= 0) {
    exit('不正な予約IDです。');
}

// ★★★ データベース操作処理をここから追加・修正 ★★★
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // キャンセル処理
    if (isset($_POST['action']) && $_POST['action'] === 'cancel') {
        
        $stmt = $db->prepare("DELETE FROM reservations WHERE id = :reservation_id");
        $stmt->execute([':reservation_id' => $reservation_id]);
      
    } else {
        // 修正処理
        $equipment_id     = $_POST['equipment_id'];
        $reservation_date = $_POST['reservation_date'];
        $start_time       = $_POST['start_time'];
        $slot_count       = intval($_POST['slot_count']);
        $user_name        = $_POST['user_name'];
        $comment          = $_POST['comment'] ?? '';

        $startTimestamp = strtotime($reservation_date . ' ' . $start_time);
        $endTimestamp = $startTimestamp + ($slot_count * 30 * 60);
        $end_time = date('H:i:s', $endTimestamp);

        if ($end_time === '00:00:00' && $slot_count > 0) {
            $end_time = '24:00:00';
        }

        $baseTimestamp = strtotime($reservation_date . ' 00:00:00');
        $newStartSec = $startTimestamp - $baseTimestamp;
        $newEndSec = $endTimestamp - $baseTimestamp;

        if ($end_time === '24:00:00') {
            $newEndSec = 86400;
        }

        // 予約重複チェック（自身は除外）
        
        $overlapQuery = "SELECT * FROM reservations 
                         WHERE equipment_id = :equipment_id 
                           AND reservation_date = :reservation_date
                           AND id <> :reservation_id
                           AND (:newStartSec < IF(TIME_TO_SEC(end_time)=0 OR TIME_TO_SEC(end_time)=86400, 86400, TIME_TO_SEC(end_time))
                                AND :newEndSec > TIME_TO_SEC(start_time))";
        $stmt = $db->prepare($overlapQuery);
        $stmt->execute([
             ':equipment_id'     => $equipment_id,
             ':reservation_date' => $reservation_date,
             ':reservation_id'   => $reservation_id,
             ':newStartSec'      => $newStartSec,
             ':newEndSec'        => $newEndSec
        ]);
        $conflict = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($conflict) {
            echo '<script>alert("指定した時間帯はすでに予約が入っています。時間を変更してください。");</script>';
            // exitしないと後続のスクリプトが実行されてしまうため注意
            exit;
        }

        // 予約更新
        $stmt = $db->prepare(
          "UPDATE reservations
             SET reservation_date = :reservation_date,
                 start_time       = :start_time,
                 end_time         = :end_time,
                 user_name        = :user_name,
                 comment          = :comment
           WHERE id = :reservation_id"
        );
        $stmt->execute([
          ':reservation_date' => $reservation_date,
          ':start_time'       => $start_time,
          ':end_time'         => $end_time,
          ':user_name'        => $user_name,
          ':comment'          => $comment,
          ':reservation_id'   => $reservation_id
        ]);
        
    }
    // 処理完了後、親ウィンドウをリロード
    echo '<script>
            if (window.parent) {
                window.parent.location.reload();
            }
          </script>';
    exit;
}


$stmt = $db->prepare("SELECT * FROM reservations WHERE id = :reservation_id");
$stmt->execute([':reservation_id' => $reservation_id]);
$reservation = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$reservation) {
    exit('予約が見つかりませんでした。');
}

$reservation_date = $reservation['reservation_date'];
$start_time       = $reservation['start_time'];
$end_time         = $reservation['end_time'];
$user_name        = $reservation['user_name'];
$equipment_id     = $reservation['equipment_id'];
$comment          = $reservation['comment'];

$startTimestamp = strtotime($reservation_date . ' ' . $start_time);
$endTimestamp = strtotime($reservation_date . ' ' . $end_time);
if ($endTimestamp <= $startTimestamp) $endTimestamp += 24 * 3600;
$durationMinutes = ($endTimestamp - $startTimestamp) / 60;
$defaultSlotCount = $durationMinutes / 30;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>予約詳細</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --font-family-sans-jp: 'Noto Sans JP', 'Inter', sans-serif;
      --bg-color: #ffffff;
      --border-color: #ced4da;
      --input-bg: #f8f9fa;
      --text-color: #212529;
      --text-muted: #6c757d;
      --accent-color: #007bff;
      --accent-dark: #0056b3;
      --danger-color: #dc3545;
      --danger-dark: #b02a37;
      --white: #ffffff;
    }
    body {
      font-family: var(--font-family-sans-jp); color: var(--text-color);
      background-color: var(--bg-color); margin: 0; padding: 1.5rem 2rem;
    }
    .form-container { max-width: 500px; margin: 0 auto; }
    .form-header h1 {
      font-size: 1.8rem; font-weight: 700; text-align: center; margin-bottom: 2rem;
    }
    .form-group { margin-bottom: 1.25rem; }
    .form-group label {
      display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.95em;
    }
    .form-control {
      width: 100%; padding: 0.75rem; border: 1px solid var(--border-color);
      border-radius: 6px; font-size: 1rem; box-sizing: border-box;
      transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    .form-control:focus {
      border-color: var(--accent-color);
      box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); outline: 0;
    }
    textarea.form-control { min-height: 100px; resize: vertical; }
    .form-row { display: flex; gap: 1rem; }
    .form-row .form-group { flex: 1; }
    .readonly-display {
      background-color: var(--input-bg); border: 1px solid var(--border-color);
      padding: 0.75rem; border-radius: 6px; font-size: 1rem;
      font-weight: 700; color: var(--text-color); text-align: center;
    }
    .btn {
      width: 100%; padding: 0.8rem 1rem; font-size: 1.1rem; font-weight: 700;
      border: none; border-radius: 6px; cursor: pointer;
      margin-top: 1rem; transition: background-color 0.15s ease-in-out;
    }
    .btn-primary { color: var(--white); background-color: var(--accent-color); }
    .btn-primary:hover { background-color: var(--accent-dark); }
    .btn-danger { color: var(--white); background-color: var(--danger-color); margin-top: 0.5rem; }
    .btn-danger:hover { background-color: var(--danger-dark); }
  </style>
</head>
<body>
  <div class="form-container">
    <div class="form-header">
      <h1>予約詳細</h1>
    </div>
    <form method="post" id="modifyForm">
      <input type="hidden" name="equipment_id" value="<?= $equipment_id; ?>">
      <div class="form-group">
        <label for="reservation_date">予約日</label>
        <input type="date" class="form-control" name="reservation_date" id="reservation_date" value="<?= htmlspecialchars($reservation_date); ?>" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="start_time">開始時間</label>
          <input type="time" class="form-control" name="start_time" id="start_time" value="<?= substr($start_time, 0, 5); ?>" step="1800" required>
        </div>
        <div class="form-group">
          <label for="slot_count">利用時間</label>
          <select name="slot_count" id="slot_count" class="form-control" required></select>
        </div>
        <div class="form-group">
          <label>終了時間</label>
          <div id="end_time_display" class="readonly-display">&nbsp;</div>
        </div>
      </div>
      <div class="form-group">
        <label for="user_name">予約者名</label>
        <input type="text" class="form-control" name="user_name" id="user_name" value="<?= htmlspecialchars($user_name); ?>" required>
      </div>
      <div class="form-group">
        <label for="comment">コメント</label>
        <textarea name="comment" id="comment" class="form-control" rows="3"><?= htmlspecialchars($comment, ENT_QUOTES); ?></textarea>
      </div>
      <div class="form-group">
        <button type="submit" name="action" value="update" class="btn btn-primary">予約を修正する</button>
        <button type="submit" name="action" value="cancel" class="btn btn-danger" onclick="return confirm('本当にこの予約をキャンセルしますか？');">予約をキャンセルする</button>
      </div>
    </form>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const initialSlotCount = <?= $defaultSlotCount; ?>;
      const dateInput = document.getElementById("reservation_date");
      const startTimeInput = document.getElementById("start_time");
      const slotCountSelect = document.getElementById("slot_count");
      const endTimeDisplay = document.getElementById("end_time_display");

      function updateEndTime() {
        const dateVal = dateInput.value;
        const startTimeVal = startTimeInput.value;
        const slotCount = parseInt(slotCountSelect.value, 10);
        if (!dateVal || !startTimeVal || isNaN(slotCount)) {
          endTimeDisplay.textContent = "??:??";
          return;
        }
        const startDateTime = new Date(`${dateVal}T${startTimeVal}:00`);
        const endDateTime = new Date(startDateTime.getTime() + slotCount * 30 * 60 * 1000);
        let hours = endDateTime.getHours();
        let minutes = endDateTime.getMinutes();
        if (hours === 0 && minutes === 0 && startDateTime.getTime() < endDateTime.getTime()) {
            hours = "24";
        } else {
            hours = String(hours).padStart(2, '0');
        }
        minutes = String(minutes).padStart(2, '0');
        endTimeDisplay.textContent = `${hours}:${minutes}`;
      }

      function populateSlotOptions() {
        const startTimeVal = startTimeInput.value;
        if (!startTimeVal) return;
        const timeParts = startTimeVal.split(':');
        const startHours = parseInt(timeParts[0], 10);
        const startMinutes = parseInt(timeParts[1], 10);
        const totalStartMinutes = startHours * 60 + startMinutes;
        const remainingMinutes = 1440 - totalStartMinutes;
        const maxSlots = Math.max(1, Math.floor(remainingMinutes / 30));
        slotCountSelect.innerHTML = "";
        for (let i = 1; i <= maxSlots; i++) {
          const option = document.createElement("option");
          const duration = i * 0.5;
          option.value = i;
          option.text = `${duration.toFixed(1)} 時間 (${i} スロット)`;
          slotCountSelect.appendChild(option);
        }
        if (initialSlotCount && initialSlotCount <= maxSlots) {
            slotCountSelect.value = initialSlotCount;
        }
        updateEndTime();
      }
      startTimeInput.addEventListener("change", populateSlotOptions);
      dateInput.addEventListener("change", populateSlotOptions);
      slotCountSelect.addEventListener("change", updateEndTime);
      populateSlotOptions();
    });
  </script>
</body>
</html>