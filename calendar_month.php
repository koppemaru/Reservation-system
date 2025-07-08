<?php
date_default_timezone_set('Asia/Tokyo');

// --- ヘルパー関数 ---
function darken_color($hex, $percent = 20) {
    if (empty($hex)) return '#cccccc';
    $hex = ltrim($hex, '#');
    if (strlen($hex) != 6) return '#cccccc';
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $r = max(0, floor($r * (1 - ($percent / 100))));
    $g = max(0, floor($g * (1 - ($percent / 100))));
    $b = max(0, floor($b * (1 - ($percent / 100))));
    return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT)
           . str_pad(dechex($g), 2, '0', STR_PAD_LEFT)
           . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
}

// --- パラメータと日付の準備 ---
$equipment_id = isset($_GET['equipment_id']) ? intval($_GET['equipment_id']) : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$currentDate = new DateTime($month . "-01");
$startDate = $currentDate->format('Y-m-d');
$lastDate  = $currentDate->format('Y-m-t');

include 'db.php';

// --- DBから各種情報を取得 ---
$stmt = $db->prepare("SELECT name FROM equipments WHERE id = :id");
$stmt->execute([':id' => $equipment_id]);
$equipmentName = $stmt->fetchColumn() ?: "不明な装置";

$sql = "SELECT id, reservation_date, start_time, end_time, user_name, comment FROM reservations WHERE equipment_id = :equipment_id AND reservation_date BETWEEN :start_date AND :end_date";
$stmt = $db->prepare($sql);
$stmt->execute([':equipment_id' => $equipment_id, ':start_date' => $startDate, ':end_date' => $lastDate]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 色分け用の配列を準備 ---
$palette = [
    '#fdecc9', '#a8d8ea', '#d4eac8', '#f0a6ca', '#c8b6ff',
    '#77b5d9', '#e5b9b5', '#b3d9b1', '#fffac2', '#a9d6e5',
    '#d3cdd7', '#ffb5a7', '#f9cb9c', '#a0d9d0', '#e8e0d5',
    '#d2bde4', '#f6eac2', '#8ecae6', '#f8b8d4', '#ffe5d9'
];

$uniqueUsers = array_unique(array_column($reservations, 'user_name'));
$userColors = [];
$i = 0;
foreach ($uniqueUsers as $user) {
    $userColors[$user] = $palette[$i % count($palette)];
    $i++;
}

// --- 予約データを高速に参照できる形式に変換 ---
$reservationSlots = [];
foreach ($reservations as $res) {
    $startTs = strtotime($res['reservation_date'] . ' ' . $res['start_time']);
    $endTs   = strtotime($res['reservation_date'] . ' ' . $res['end_time']);
    
    // 24時までの予約を正しく処理
    if ($res['end_time'] === '24:00:00' || $res['end_time'] === '24:00') {
      $endTs = strtotime($res['reservation_date'] . ' 23:59:59') + 1;
    } elseif ($endTs <= $startTs) {
      $endTs += 24 * 3600;
    }
    
    for ($t = $startTs; $t < $endTs; $t += 30 * 60) {
        if ($t < strtotime($res['reservation_date'] . ' 24:00:00')) {
            $key = $res['reservation_date'] . '_' . date('H:i', $t);
            // 予約の最初のスロットにのみ、colspan（結合セル数）を計算して保存
            if ($t == $startTs) {
                $res['colspan'] = (($endTs - $startTs) / 60) / 30;
            }
            $reservationSlots[$key] = $res;
        }
    }
}

// --- 日付・祝日関連の準備 ---
$now = new DateTime();
$weekNameJP = ['日','月','火','水','木','金','土'];
$holidayList = [
    '2025-01-01', '2025-01-13', '2025-02-11', '2025-02-23', '2025-02-24', '2025-03-20',
    '2025-04-29', '2025-05-03', '2025-05-04', '2025-05-05', '2025-05-06', '2025-07-21',
    '2025-08-11', '2025-09-15', '2025-09-23', '2025-10-13', '2025-11-03', '2025-11-23', 
    '2025-11-24', '2026-01-01', '2026-01-12', '2026-02-11', '2026-02-23', '2026-03-20',
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($equipmentName) ?> の予約カレンダー</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,400,0,0" rel="stylesheet" />
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>
    :root {
      --font-family-sans-jp: 'Noto Sans JP', 'Inter', sans-serif;
      --border-color: #dee2e6;
      --text-color: #212529;
      --accent-color: #007bff;
      --past-bg: #f1f3f5;
      --saturday-bg: #e3f2fd;
      --sunday-bg: #ffebee;
      --holiday-bg: #fce4ec;
    }
    html, body {
      height: 100%; margin: 0; padding: 0;
      font-family: var(--font-family-sans-jp);
      color: var(--text-color);
      background-color: #f8f9fa;
    }
    .page-wrapper {
      height: 100%;
      overflow: auto;
    }
    .calendar-container {
      background: #fff;
      padding: 1.5rem;
    }
    .calendar-header h1 { font-size: 1.8rem; font-weight: 700; }
    .calendar-nav a {
      color: var(--accent-color); text-decoration: none; font-weight: 500;
      padding: 0.5rem 1rem; border-radius: 6px;
    }
    .calendar-nav a:hover { background-color: #f1f3f5; }
    .calendar-table {
      width: 100%;
      table-layout: fixed;
      border-collapse: separate;
      border-spacing: 0;
      margin-top: 1.5rem;
    }
    @media (max-width: 768px) {
        .calendar-table {
            min-width: 1200px;
        }
    }
    .calendar-table th, .calendar-table td {
      border: 1px solid var(--border-color); padding: 0; text-align: center;
      vertical-align: middle; font-size: 0.8rem;
    }
    .date-cell {
      width: 80px; min-width: 80px;
      position: sticky;
      left: 0;
      background: #fff;
      z-index: 2;
    }
    .date-cell.saturday { background-color: var(--saturday-bg); }
    .date-cell.sunday { background-color: var(--sunday-bg); }
    .date-cell.holiday { background-color: var(--holiday-bg); }

    .time-header-cell {
      position: sticky;
      top: 0;
      z-index: 3;
      background-color: #f9f9f9;
    }
    .time-header-cell.date-cell {
      z-index: 4;
    }
    .time-header-cell.hour-mark, .time-slot.hour-mark { border-left: 1px solid #adb5bd; }
    .time-slot { height: 60px; }
    .slot-content { display: block; width: 100%; height: 100%; text-decoration: none; }
    .slot-past { background-color: var(--past-bg); }
    .slot-empty a {
      color: #adb5bd; display: flex; align-items: center; justify-content: center;
      opacity: 0.2; transition: opacity 0.2s ease;
    }
    .time-slot:hover .slot-empty a { opacity: 1; }
    .slot-reserved a {
      color: #333; font-weight: 500; padding: 0.3rem 0.5rem;
      text-align: left; font-size: 0.8em; line-height: 1.4;
      display: flex; flex-direction: column; justify-content: center; overflow: hidden;
    }
    .slot-reserved .user-name { font-weight: 700; }
    .slot-reserved .comment {
      font-weight: 400; font-size: 0.9em; white-space: nowrap;
      overflow: hidden; text-overflow: ellipsis; opacity: 0.8;
    }
  </style>
</head>
<body>
  <div class="page-wrapper">
    <div class="calendar-container">
      <div class="calendar-header text-center mb-3">
        <h1><?= htmlspecialchars($equipmentName) ?></h1>
        <div class="calendar-nav">
          <a href="?equipment_id=<?= $equipment_id ?>&month=<?= (clone $currentDate)->modify('-1 month')->format('Y-m') ?>" target="content-frame">&laquo; 前月</a>
          <strong class="mx-3" style="font-size: 1.2rem;"><?= $currentDate->format('Y年n月') ?></strong>
          <a href="?equipment_id=<?= $equipment_id ?>&month=<?= (clone $currentDate)->modify('+1 month')->format('Y-m') ?>" target="content-frame">翌月 &raquo;</a>
        </div>
      </div>
      <table class="calendar-table">
          <thead>
            <tr>
              <th class="date-cell time-header-cell">日付</th>
              <?php for ($i = 0; $i < 48; $i++):
                $time = date('H:i', strtotime("00:00") + $i * 30 * 60);
                $isHourMark = substr($time, 3, 2) == "00";
                $display = $isHourMark ? ltrim(date('H', strtotime($time)), "0") . '時' : '';
              ?>
                <th class="time-header-cell <?= $isHourMark ? 'hour-mark' : '' ?>"><?= $display ?></th>
              <?php endfor; ?>
            </tr>
          </thead>
          <tbody>
            <?php
              $day = new DateTime($startDate);
              while ($day->format('Y-m-d') <= $lastDate) {
                $dateStr = $day->format('Y-m-d');
                $weekday = (int)$day->format('w');
                $dayLabel = $day->format('j') . '<br><small>(' . $weekNameJP[$weekday] . ')</small>';
                
                $dayClass = '';
                if ($weekday === 6) $dayClass = 'saturday';
                elseif ($weekday === 0) $dayClass = 'sunday';
                if (in_array($dateStr, $holidayList)) $dayClass = 'holiday';
            ?>
              <tr>
                <td class="date-cell <?= $dayClass ?>"><?= $dayLabel ?></td>
                <?php
                  $i = 0;
                  while ($i < 48) {
                    $slotTime = date('H:i', strtotime("00:00") + $i * 30 * 60);
                    $key = $dateStr . '_' . $slotTime;
                    $cellDateTime = new DateTime($dateStr . ' ' . $slotTime);
                    $isHourMark = substr($slotTime, 3, 2) == "00";
                    $colspan = 1;
                    $content = '';
                    $cellClass = '';
                    
                    if (isset($reservationSlots[$key])) {
                        $res = $reservationSlots[$key];
                        $colspan = $res['colspan'] ?? 1;
                        
                        $link = "modify.php?reservation_id=" . $res['id'];
                        $bgColor = $userColors[$res['user_name']] ?? '#e9ecef';
                        $borderColor = darken_color($bgColor, 20);
                        
                        $userName = htmlspecialchars($res['user_name'], ENT_QUOTES);
                        $comment = htmlspecialchars($res['comment'], ENT_QUOTES);
                        
                        $content = "<a href='{$link}' class='openModal slot-content' style='background-color:{$bgColor}; border-left: 4px solid {$borderColor};'>"
                                 . "<span class='user-name'>{$userName}</span>"
                                 . "<span class='comment'>{$comment}</span>"
                                 . "</a>";
                        $cellClass = 'slot-reserved';
                    } else {
                        if ($cellDateTime < $now) {
                            $cellClass = 'slot-past';
                        } else {
                            $link = "reserve.php?equipment_id={$equipment_id}&date={$dateStr}&start={$slotTime}";
                            $content = "<a href='{$link}' class='openModal slot-content'><span class='material-symbols-outlined'>add</span></a>";
                            $cellClass = 'slot-empty';
                        }
                    }
                    
                    echo "<td class='time-slot {$cellClass} " . ($isHourMark ? 'hour-mark' : '') . "' colspan='{$colspan}'>{$content}</td>";
                    $i += $colspan;
                  }
                ?>
              </tr>
            <?php $day->modify('+1 day'); } ?>
          </tbody>
      </table>
    </div>
  </div>

  <div class="modal fade" id="reservationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
      <div class="modal-content">
        <div class="modal-body" style="padding: 0;">
          <iframe id="modalIframe" src="" frameborder="0" style="width:100%; height:800px;"></iframe>
        </div>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script>
    $(function () {
      var modalTriggerElement = null;
      $(document).on('click', '.openModal', function(e) {
        e.preventDefault();
        modalTriggerElement = this; 
        $('#modalIframe').attr('src', $(this).attr('href'));
        $('#reservationModal').modal('show');
      });
      $('#reservationModal').on('hide.bs.modal', function () {
        if (modalTriggerElement) { $(modalTriggerElement).focus(); }
        // モーダルを閉じた後に親ページをリロードして変更を反映
        window.parent.location.reload();
      });
    });
  </script>
</body>
</html>