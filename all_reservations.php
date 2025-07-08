<?php
// all_reservations.php
include 'db.php'; 

date_default_timezone_set('Asia/Tokyo');
$now = new DateTime();

// 祝日リスト
$holidayList = [
  '2025-01-01', '2025-01-13', '2025-02-11', '2025-02-23', '2025-02-24', '2025-03-20', '2025-04-29', 
  '2025-05-03', '2025-05-04', '2025-05-05', '2025-05-06', '2025-07-21', '2025-08-11', '2025-09-15', 
  '2025-09-23', '2025-10-13', '2025-11-03', '2025-11-23', '2025-11-24', '2026-01-01', '2026-01-12', 
  '2026-02-11', '2026-02-23', '2026-03-20',
];

$weekOffset = isset($_GET['week_offset']) ? intval($_GET['week_offset']) : 0;
$ref = new DateTime();
if ($weekOffset !== 0) {
    $ref->modify(($weekOffset > 0 ? '+' : '') . $weekOffset . ' week');
}
$startOfWeek = (clone $ref)->modify('monday this week')->format('Y-m-d');
$endOfWeek   = (clone $ref)->modify('monday this week')->modify('+6 days')->format('Y-m-d');

$stmt = $db->prepare(
    "SELECT re.id AS reservation_id, r.name AS room_name, e.name AS equipment_name,
            re.reservation_date, re.start_time, re.end_time, re.user_name, re.comment
     FROM reservations re
     JOIN equipments e ON re.equipment_id = e.id
     JOIN rooms r ON e.room_id = r.id
     WHERE re.reservation_date BETWEEN ? AND ?
     ORDER BY re.reservation_date, re.start_time"
);
$stmt->execute([$startOfWeek, $endOfWeek]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$palette = [
    '#7CB342', '#5E35B1', '#FF7043', '#78909C', '#1E88E5',
    '#D81B60', '#FFC107', '#AB47BC', '#A1887F', '#00ACC1',
    '#EF5350', '#43A047', '#3949AB', '#BDBDBD', '#F4511E',
    '#6A1B9A', '#29B6F6', '#795548', '#9CCC65', '#0288D1',
    '#AD1457', '#7E57C2', '#546E7A', '#E53935', '#00838F',
    '#FFA726', '#1565C0', '#2E7D32', '#757575', '#8E24AA'
];
$equipmentColors = [];
$uniqueEquipments = array_unique(array_column($reservations, 'equipment_name'));
foreach ($uniqueEquipments as $i => $name) {
    $equipmentColors[$name] = $palette[$i % count($palette)];
}

$slotTimes = [];
$slot = DateTime::createFromFormat('H:i', '00:00');
$endSlot = DateTime::createFromFormat('H:i', '23:30');
while ($slot <= $endSlot) {
    $slotTimes[] = $slot->format('H:i');
    $slot->modify('+30 minutes');
}

$weekDates = [];
$start = new DateTime($startOfWeek);
for ($i = 0; $i < 7; $i++) {
    $weekDates[] = (clone $start)->modify("+{$i} days");
}
$weekdayNames = ['日', '月', '火', '水', '木', '金', '土'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>全装置予約（週表示）</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>
    :root {
      --font-family-sans-jp: 'Noto Sans JP', 'Inter', sans-serif;
      --bg-color: #f8f9fa;
      --border-color: #dee2e6;
      --header-bg: #ffffff;
      --time-slot-bg: #f1f3f5;
      --past-slot-bg: #e9ecef;
      --text-color: #212529;
      --accent-color: #007bff;
      --white: #ffffff;
      --shadow: 0 2px 4px rgba(0,0,0,0.05);
      --saturday-bg: #e3f2fd;
      --sunday-bg: #ffebee;
      --holiday-bg: #fce4ec;
    }
    html, body {
      height: 100%; margin: 0; padding: 0;
      font-family: var(--font-family-sans-jp);
      color: var(--text-color);
      background-color: var(--bg-color);
      overflow: hidden;
    }
    .page-container {
      display: flex;
      flex-direction: column;
      height: 100%;
    }
    .header-nav {
      flex-shrink: 0;
      display: flex; align-items: center; justify-content: space-between;
      height: 60px; background: var(--header-bg);
      padding: 0 20px; box-shadow: var(--shadow);
    }
    .header-nav .nav-links a {
      color: var(--accent-color); text-decoration: none; font-weight: 500;
    }
    .header-nav .current-week { font-size: 1.1em; font-weight: 500; color: var(--text-color); white-space: nowrap; }
    
    .table-wrapper {
      flex-grow: 1;
      overflow: auto;
      -webkit-overflow-scrolling: touch;
    }
    table {
      border-collapse: collapse;
      width: 100%;
    }
    th, td {
      border: 1px solid var(--border-color);
      padding: 6px 8px;
      height: 35px;
      vertical-align: top;
    }
    thead th {
      position: sticky;
      top: 0;
      background: var(--header-bg);
      z-index: 2;
    }
    .slot-time {
      position: sticky;
      left: 0;
      background: var(--time-slot-bg);
      z-index: 1;
    }
    thead th.slot-time {
      z-index: 3;
    }
    th.saturday { background-color: var(--saturday-bg); }
    th.sunday { background-color: var(--sunday-bg); }
    th.holiday { background-color: var(--holiday-bg); }

    td { position: relative; }
    td.past-slot { background: #e0e0e0; }
    .res-item {
      display: block; position: relative; margin-bottom: 4px; padding: 4px 6px;
      border-radius: 4px; color: var(--white); font-size: 0.85em; line-height: 1.4;
      cursor: pointer; transition: transform 0.1s ease;
    }
    .res-item:hover { transform: scale(1.02); }
    .res-item__content { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .res-item-room { font-weight: 500; }
    .res-item[data-comment]:not([data-comment='']):hover::after {
        content: attr(data-comment); position: absolute; bottom: 100%; left: 50%;
        transform: translateX(-50%); margin-bottom: 5px; padding: 8px 12px;
        width: max-content; max-width: 300px; background-color: #333; color: #fff;
        border-radius: 6px; font-size: 0.9em; line-height: 1.5; z-index: 10;
        white-space: pre-wrap; pointer-events: none; opacity: 0; animation: fadeIn 0.2s forwards;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translate(-50%, 5px); }
        to { opacity: 1; transform: translate(-50%, 0); }
    }
    @media (max-width: 768px) {
      /* ... 他のスマホ用スタイル ... */
      table {
        min-width: 900px; /* ★スマホの時だけ最小幅を指定。この数値を調整 */
      }
    }

  </style>
</head>
<body>
  <div class="page-container">
    <header class="header-nav">
      <div class="nav-links"> <a href="?week_offset=<?= $weekOffset - 1 ?>" title="前の週"> &laquo; 前週 </a> </div>
      <div class="current-week"> <?= htmlspecialchars($startOfWeek) ?> ～ <?= htmlspecialchars($endOfWeek) ?> </div>
      <div class="nav-links"> <a href="?week_offset=<?= $weekOffset + 1 ?>" title="次の週"> 翌週 &raquo; </a> </div>
    </header>
    
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th class="slot-time">時間</th>
            <?php foreach ($weekDates as $dt):
              $dateStr = $dt->format('Y-m-d');
              $dow = (int)$dt->format('w');
              $class = '';
              if ($dow === 6) $class = 'saturday';
              elseif ($dow === 0) $class = 'sunday';
              if (in_array($dateStr, $holidayList)) $class = 'holiday';
            ?>
            <th class="<?= $class ?>"><?= $dt->format('n/j') ?> (<?= $weekdayNames[$dow] ?>)</th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($slotTimes as $time): ?>
          <tr>
            <th class="slot-time"><?= $time ?></th>
            <?php foreach ($weekDates as $dt):
              $dateStr = $dt->format('Y-m-d');
              $cellDT = DateTime::createFromFormat('Y-m-d H:i', $dateStr.' '.$time);
              $cellClass = ($cellDT < $now) ? 'past-slot' : '';
              echo '<td class="'. $cellClass .'">';
              foreach ($reservations as $r) {
                if ($r['reservation_date'] === $dateStr) {
                  $resStart = DateTime::createFromFormat('H:i:s', $r['start_time']);
                  if (isset($r['end_time']) && ($r['end_time'] === '24:00:00' || $r['end_time'] === '24:00')) {
                      $resEnd = DateTime::createFromFormat('Y-m-d H:i:s', $r['reservation_date'] . ' 00:00:00');
                      if ($resEnd) $resEnd->modify('+1 day');
                  } else {
                      $resEnd = DateTime::createFromFormat('H:i:s', $r['end_time'] ?? '00:00:00');
                  }
                  $cellTime = DateTime::createFromFormat('H:i', $time);
                  if ($resStart === false || $resEnd === false || $cellTime === false) continue;
                  if ($resEnd <= $resStart) $resEnd->modify('+1 day');
                  if ($resStart <= $cellTime && $resEnd > $cellTime) {
                    $color = $equipmentColors[$r['equipment_name']] ?? '#6c757d';
                    $commentAttr = isset($r['comment']) ? htmlspecialchars($r['comment'], ENT_QUOTES) : '';
                    $link = "modify.php?reservation_id=" . $r['reservation_id'];
                    echo '<a href="'. $link .'" class="res-item openModal" data-comment="'. $commentAttr .'" style="background:'. htmlspecialchars($color) .'">';
                    echo   '<div class="res-item__content">';
                    echo     '<span class="res-item-room">' . htmlspecialchars($r['equipment_name']) . '</span>';
                    echo     ' (' . htmlspecialchars($r['user_name']) . ')';
                    echo   '</div>';
                    echo '</a>';
                  }
                }
              }
              echo '</td>';
            endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal fade" id="reservationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
      <div class="modal-content">
        <div class="modal-body" style="padding: 0;">
          <iframe id="modalIframe" src="" frameborder="0" style="width:100%; height:750px;"></iframe>
        </div>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
        const wrapper = document.querySelector('.table-wrapper');
        const tableHeader = document.querySelector('thead');
        const now = new Date();

        const scrollToCurrentTime = () => {
            const currentHour = now.getHours();
            const currentMinute = now.getMinutes();
            const targetHour = currentMinute < 30 ? currentHour : currentHour;
            const targetMinute = currentMinute < 30 ? 0 : 30;
            const targetTime = `${String(targetHour).padStart(2, '0')}:${String(targetMinute).padStart(2, '0')}`;
            
            const targetRow = Array.from(document.querySelectorAll('tbody tr')).find(row => {
                const th = row.querySelector('.slot-time');
                return th && th.textContent.trim() === targetTime;
            });

            if (targetRow) {
                const topPos = targetRow.offsetTop - tableHeader.offsetHeight;
                wrapper.scrollTop = topPos;
            }
        };

        const urlParams = new URLSearchParams(window.location.search);
        const weekOffset = parseInt(urlParams.get('week_offset') || '0', 10);
        if (weekOffset === 0) {
            window.onload = () => {
                scrollToCurrentTime();
            };
        }

        var modalTriggerElement = null;
        $(document).on('click', '.openModal', function(e) {
            e.preventDefault();
            modalTriggerElement = this; 
            $('#modalIframe').attr('src', $(this).attr('href'));
            $('#reservationModal').modal('show');
        });
        $('#reservationModal').on('hide.bs.modal', function () {
            if (modalTriggerElement) {
                $(modalTriggerElement).focus();
            }
            window.location.reload();
        });
    });
  </script>
</body>
</html>